<?php

declare(strict_types=1);

namespace Willy\Service;

use GdImage;

use function imagecolorallocate;
use function imagecopy;
use function imagecreatetruecolor;
use function imagefill;
use function imagefilledellipse;
use function imagefilledrectangle;
use function imagesx;
use function imagesy;
use function intdiv;
use function max;
use function min;
use function round;
use function sqrt;

/**
 * Dibuja un marco de iPhone alrededor de una captura vertical.
 *
 * El marco se compone sobre blanco para que el JPEG resultante no necesite
 * canal alfa y FPDF pueda incrustarlo sin conversiones.
 */
final class DeviceFrame
{
    private const MIN_WIDTH = 120;

    private const BEZEL_RATIO = 0.020;
    private const OUTER_RADIUS_RATIO = 0.130;
    private const BUTTON_DEPTH_RATIO = 0.012;

    private const BODY_RED = 12;
    private const BODY_GREEN = 12;
    private const BODY_BLUE = 16;

    /** @var list<array{float, float}> */
    private const LEFT_BUTTONS = [
        [0.150, 0.040],
        [0.235, 0.075],
        [0.330, 0.075],
    ];

    private const POWER_BUTTON_CENTER = 0.275;
    private const POWER_BUTTON_LENGTH = 0.110;

    /**
     * Devuelve una copia con marco. Si la imagen no es vertical o es demasiado
     * pequeña, devuelve la misma instancia sin modificarla.
     */
    public function apply(GdImage $screen): GdImage
    {
        $screenWidth = imagesx($screen);
        $screenHeight = imagesy($screen);

        if ($screenWidth < self::MIN_WIDTH || $screenHeight <= $screenWidth) {
            return $screen;
        }

        $bezel = max(3, (int) round($screenWidth * self::BEZEL_RATIO));
        $depth = max(2, (int) round($screenWidth * self::BUTTON_DEPTH_RATIO));
        $outerRadius = max($bezel + 2, (int) round($screenWidth * self::OUTER_RADIUS_RATIO));
        $innerRadius = max(2, $outerRadius - $bezel);

        $bodyWidth = $screenWidth + (2 * $bezel);
        $bodyHeight = $screenHeight + (2 * $bezel);
        $canvasWidth = $bodyWidth + (2 * $depth);

        $canvas = imagecreatetruecolor($canvasWidth, $bodyHeight);

        if (!$canvas instanceof GdImage) {
            return $screen;
        }

        $body = imagecolorallocate($canvas, self::BODY_RED, self::BODY_GREEN, self::BODY_BLUE);
        $paper = imagecolorallocate($canvas, 255, 255, 255);

        if ($body === false || $paper === false) {
            return $screen;
        }

        imagefill($canvas, 0, 0, $paper);

        $bodyX = $depth;
        $bodyY = 0;

        $this->fillRoundedRectangle($canvas, $bodyX, $bodyY, $bodyWidth, $bodyHeight, $outerRadius, $body);
        $this->drawButtons($canvas, $bodyHeight, $canvasWidth, $depth, $body);

        $screenX = $bodyX + $bezel;
        $screenY = $bodyY + $bezel;

        $this->copyRoundedScreen($canvas, $screen, $screenX, $screenY, $screenWidth, $screenHeight, $innerRadius);

        return $canvas;
    }

    /**
     * Copia la captura recortando sus esquinas. Las zonas no copiadas dejan ver
     * el cuerpo negro dentro del teléfono y el papel blanco fuera de él, sin
     * pintar nunca fuera del contorno redondeado.
     */
    private function copyRoundedScreen(
        GdImage $canvas,
        GdImage $screen,
        int $x,
        int $y,
        int $width,
        int $height,
        int $radius,
    ): void {
        $radius = min($radius, intdiv($width, 2), intdiv($height, 2));

        if ($radius < 1) {
            imagecopy($canvas, $screen, $x, $y, 0, 0, $width, $height);

            return;
        }

        for ($offset = 0; $offset < $radius; $offset++) {
            $inset = $this->cornerInset($radius, $offset);

            $this->copyScreenRow($canvas, $screen, $x, $y, $width, $offset, $inset);
            $this->copyScreenRow($canvas, $screen, $x, $y, $width, $height - 1 - $offset, $inset);
        }

        $middleHeight = $height - (2 * $radius);

        if ($middleHeight > 0) {
            imagecopy($canvas, $screen, $x, $y + $radius, 0, $radius, $width, $middleHeight);
        }
    }

    private function copyScreenRow(
        GdImage $canvas,
        GdImage $screen,
        int $x,
        int $y,
        int $width,
        int $row,
        int $inset,
    ): void {
        $segmentWidth = $width - (2 * $inset);

        if ($segmentWidth < 1) {
            return;
        }

        imagecopy($canvas, $screen, $x + $inset, $y + $row, $inset, $row, $segmentWidth, 1);
    }

    private function cornerInset(int $radius, int $offset): int
    {
        $distance = $radius - $offset - 0.5;
        $half = (int) round(sqrt(($radius * $radius) - ($distance * $distance)));

        return max(0, $radius - $half);
    }

    private function drawButtons(GdImage $canvas, int $bodyHeight, int $canvasWidth, int $depth, int $color): void
    {
        foreach (self::LEFT_BUTTONS as $button) {
            $length = max(4, (int) round($bodyHeight * $button[1]));
            $top = (int) round(($bodyHeight * $button[0]) - ($length / 2));
            $this->fillRoundedRectangle($canvas, 0, $top, $depth, $length, intdiv($depth, 2), $color);
        }

        $powerLength = max(4, (int) round($bodyHeight * self::POWER_BUTTON_LENGTH));
        $powerTop = (int) round(($bodyHeight * self::POWER_BUTTON_CENTER) - ($powerLength / 2));
        $this->fillRoundedRectangle(
            $canvas,
            $canvasWidth - $depth,
            $powerTop,
            $depth,
            $powerLength,
            intdiv($depth, 2),
            $color,
        );
    }

    private function fillRoundedRectangle(
        GdImage $canvas,
        int $x,
        int $y,
        int $width,
        int $height,
        int $radius,
        int $color,
    ): void {
        if ($width < 1 || $height < 1) {
            return;
        }

        $radius = min($radius, intdiv($width, 2), intdiv($height, 2));

        if ($radius < 1) {
            imagefilledrectangle($canvas, $x, $y, $x + $width - 1, $y + $height - 1, $color);

            return;
        }

        imagefilledrectangle($canvas, $x + $radius, $y, $x + $width - $radius - 1, $y + $height - 1, $color);
        imagefilledrectangle($canvas, $x, $y + $radius, $x + $width - 1, $y + $height - $radius - 1, $color);

        $diameter = $radius * 2;

        imagefilledellipse($canvas, $x + $radius, $y + $radius, $diameter, $diameter, $color);
        imagefilledellipse($canvas, $x + $width - $radius - 1, $y + $radius, $diameter, $diameter, $color);
        imagefilledellipse($canvas, $x + $radius, $y + $height - $radius - 1, $diameter, $diameter, $color);
        imagefilledellipse(
            $canvas,
            $x + $width - $radius - 1,
            $y + $height - $radius - 1,
            $diameter,
            $diameter,
            $color,
        );
    }
}
