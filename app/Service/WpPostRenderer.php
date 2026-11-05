<?php

declare(strict_types=1);

namespace Willy\Service;

use GdImage;
use Willy\Domain\WpPost;
use Willy\Exception\ProcessingException;
use Willy\Support\Config;
use Willy\Support\FileSystem;

use function array_slice;
use function count;
use function dirname;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefrompng;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagefill;
use function imagefilledrectangle;
use function imagejpeg;
use function imagesx;
use function imagesy;
use function imagettfbbox;
use function imagettftext;
use function intdiv;
use function is_array;
use function is_int;
use function max;
use function mb_str_split;
use function mb_strtoupper;
use function min;
use function preg_split;
use function round;
use function trim;

/**
 * Dibuja una entrada de WordPress como si fuera una captura de iPhone.
 *
 * El lienzo mide 390x844 puntos CSS y se dibuja a escala 3 para que el
 * resultado tenga la misma densidad que una captura real (1170x2532).
 */
final class WpPostRenderer
{
    private const SCALE = 3;

    private const WIDTH = 390;
    private const HEIGHT = 844;

    private const RED_HEIGHT = 34;
    private const LOGO_TOP = 74;
    private const LOGO_HEIGHT = 46;

    private const DATE_BASELINE = 204;
    private const DATE_SIZE = 12;
    private const TITLE_BASELINE = 232;
    private const TITLE_SIZE = 22;
    private const TITLE_LINE = 30;
    private const TITLE_MAX_LINES = 4;
    private const CATEGORY_GAP = 28;
    private const CATEGORY_SIZE = 12;
    private const IMAGE_GAP = 14;
    private const IMAGE_HEIGHT = 232;
    private const BODY_GAP = 20;
    private const BODY_SIZE = 15;
    private const BODY_LINE = 23;
    private const PADDING = 18;

    private const FONT_TITLE = 'oswald-bold.ttf';
    private const FONT_BODY = 'roboto-regular.ttf';
    private const FONT_BOLD = 'roboto-bold.ttf';

    /** @var list<int> */
    private const RED = [186, 59, 48];
    /** @var list<int> */
    private const BLACK = [14, 14, 14];
    /** @var list<int> */
    private const WHITE = [255, 255, 255];
    /** @var list<int> */
    private const GRAY = [152, 152, 152];
    /** @var list<int> */
    private const BODY = [32, 32, 32];
    /** @var list<int> */
    private const PLACEHOLDER = [228, 228, 228];

    public function __construct(
        private readonly Config $config,
        private readonly FileSystem $files,
    ) {
    }

    public function render(WpPost $post, string $imageBytes, string $destination): void
    {
        $titleLines = $this->titleLines($post);
        $category = mb_strtoupper($post->category);

        $lastTitleBaseline = self::TITLE_BASELINE + ((count($titleLines) - 1) * self::TITLE_LINE);
        $categoryBaseline = $lastTitleBaseline + self::CATEGORY_GAP;
        $imageTop = $category === ''
            ? $lastTitleBaseline + self::IMAGE_GAP
            : $categoryBaseline + self::IMAGE_GAP;

        $canvas = $this->canvas($imageTop);

        $this->drawLogo($canvas);
        $this->drawHamburger($canvas);
        $this->drawText($canvas, $post->dateLabel(), self::FONT_BOLD, self::DATE_SIZE, self::DATE_BASELINE, self::GRAY);

        foreach ($titleLines as $index => $line) {
            $this->drawText(
                $canvas,
                $line,
                self::FONT_TITLE,
                self::TITLE_SIZE,
                self::TITLE_BASELINE + ($index * self::TITLE_LINE),
                self::WHITE,
            );
        }

        if ($category !== '') {
            $this->drawText($canvas, $category, self::FONT_BOLD, self::CATEGORY_SIZE, $categoryBaseline, self::RED);
        }

        $this->drawImage($canvas, $imageBytes, $imageTop);
        $this->drawBody($canvas, $post->content, $imageTop + self::IMAGE_HEIGHT + self::BODY_GAP);

        $this->files->ensureDirectory(dirname($destination));

        if (!imagejpeg($canvas, $destination, $this->config->jpegQuality)) {
            throw new ProcessingException('No se pudo guardar la captura generada.');
        }
    }

    /**
     * @return list<string>
     */
    private function titleLines(WpPost $post): array
    {
        $lines = $this->wrap(
            $post->title,
            self::FONT_TITLE,
            self::TITLE_SIZE,
            (self::WIDTH * self::SCALE) - (2 * self::PADDING * self::SCALE),
        );

        if (count($lines) <= self::TITLE_MAX_LINES) {
            return $lines;
        }

        $lines = array_slice($lines, 0, self::TITLE_MAX_LINES);
        $last = count($lines) - 1;
        $lines[$last] = $this->fit(
            $lines[$last] . ' …',
            self::FONT_TITLE,
            self::TITLE_SIZE,
            (self::WIDTH * self::SCALE) - (2 * self::PADDING * self::SCALE),
        );

        return $lines;
    }

    private function canvas(int $imageTop): GdImage
    {
        $canvas = imagecreatetruecolor(self::WIDTH * self::SCALE, self::HEIGHT * self::SCALE);

        if (!$canvas instanceof GdImage) {
            throw new ProcessingException('No se pudo crear el lienzo de la captura.');
        }

        imagefill($canvas, 0, 0, $this->color($canvas, self::WHITE));
        imagefilledrectangle(
            $canvas,
            0,
            0,
            self::WIDTH * self::SCALE,
            $imageTop * self::SCALE,
            $this->color($canvas, self::BLACK),
        );
        imagefilledrectangle(
            $canvas,
            0,
            0,
            self::WIDTH * self::SCALE,
            self::RED_HEIGHT * self::SCALE,
            $this->color($canvas, self::RED),
        );

        return $canvas;
    }

    private function drawHamburger(GdImage $canvas): void
    {
        $color = $this->color($canvas, self::WHITE);

        foreach ([12, 18, 24] as $top) {
            imagefilledrectangle(
                $canvas,
                340 * self::SCALE,
                $top * self::SCALE,
                372 * self::SCALE,
                ($top + 2) * self::SCALE,
                $color,
            );
        }
    }

    private function drawLogo(GdImage $canvas): void
    {
        $path = $this->root() . '/public/assets/logo-proyecto-z.png';

        if (!$this->files->isFile($path)) {
            return;
        }

        $logo = imagecreatefrompng($path);

        if (!$logo instanceof GdImage) {
            return;
        }

        $sourceWidth = imagesx($logo);
        $sourceHeight = imagesy($logo);

        $targetHeight = self::LOGO_HEIGHT * self::SCALE;
        $targetWidth = (int) round($targetHeight * ($sourceWidth / $sourceHeight));
        $left = intdiv((self::WIDTH * self::SCALE) - $targetWidth, 2);

        imagecopyresampled(
            $canvas,
            $logo,
            $left,
            self::LOGO_TOP * self::SCALE,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
    }

    private function drawBody(GdImage $canvas, string $content, int $top): void
    {
        $lines = $this->wrap(
            $content,
            self::FONT_BODY,
            self::BODY_SIZE,
            (self::WIDTH * self::SCALE) - (2 * self::PADDING * self::SCALE),
        );

        $baseline = $top + self::BODY_SIZE;

        foreach ($lines as $line) {
            if ($baseline > self::HEIGHT - 8) {
                break;
            }

            $this->drawText($canvas, $line, self::FONT_BODY, self::BODY_SIZE, $baseline, self::BODY);
            $baseline += self::BODY_LINE;
        }
    }

    private function drawImage(GdImage $canvas, string $bytes, int $top): void
    {
        $width = self::WIDTH * self::SCALE;
        $height = self::IMAGE_HEIGHT * self::SCALE;

        if ($bytes !== '') {
            $image = imagecreatefromstring($bytes);

            if ($image instanceof GdImage) {
                $sourceWidth = imagesx($image);
                $sourceHeight = imagesy($image);

                $scale = max($width / $sourceWidth, $height / $sourceHeight);
                $cropWidth = min($sourceWidth, (int) round($width / $scale));
                $cropHeight = min($sourceHeight, (int) round($height / $scale));
                $sourceX = intdiv($sourceWidth - $cropWidth, 2);
                $sourceY = intdiv($sourceHeight - $cropHeight, 2);

                imagecopyresampled(
                    $canvas,
                    $image,
                    0,
                    $top * self::SCALE,
                    $sourceX,
                    $sourceY,
                    $width,
                    $height,
                    $cropWidth,
                    $cropHeight,
                );

                return;
            }
        }

        imagefilledrectangle(
            $canvas,
            0,
            $top * self::SCALE,
            $width,
            ($top + self::IMAGE_HEIGHT) * self::SCALE,
            $this->color($canvas, self::PLACEHOLDER),
        );
    }

    /**
     * @param list<int> $rgb
     */
    private function drawText(
        GdImage $canvas,
        string $text,
        string $font,
        int $size,
        int $baseline,
        array $rgb,
    ): void {
        if ($text === '') {
            return;
        }

        $path = $this->root() . '/resources/fonts/' . $font;

        if (!$this->files->isFile($path)) {
            throw new ProcessingException('Falta la fuente ' . $font . ' para generar la captura.');
        }

        $drawn = imagettftext(
            $canvas,
            $size * self::SCALE,
            0,
            self::PADDING * self::SCALE,
            $baseline * self::SCALE,
            $this->color($canvas, $rgb),
            $path,
            $text,
        );

        if ($drawn === false) {
            throw new ProcessingException('No se pudo dibujar el texto de la captura.');
        }
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text));

        if (!is_array($words)) {
            return [];
        }

        $path = $this->root() . '/resources/fonts/' . $font;
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if ($current === '' || $this->textWidth($candidate, $path, $size) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function fit(string $text, string $font, int $size, int $maxWidth): string
    {
        $path = $this->root() . '/resources/fonts/' . $font;

        if ($this->textWidth($text, $path, $size) <= $maxWidth) {
            return $text;
        }

        $suffix = '…';
        $result = '';

        foreach (mb_str_split($text) as $char) {
            if ($this->textWidth($result . $char . $suffix, $path, $size) > $maxWidth) {
                break;
            }

            $result .= $char;
        }

        return $result === '' ? $suffix : $result . $suffix;
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        if ($text === '' || !$this->files->isFile($font)) {
            return 0;
        }

        $box = imagettfbbox($size * self::SCALE, 0, $font, $text);

        if ($box === false) {
            return 0;
        }

        $left = $box[0];
        $right = $box[2];
        $leftBottom = $box[4];
        $rightBottom = $box[6];

        if (!is_int($left) || !is_int($right) || !is_int($leftBottom) || !is_int($rightBottom)) {
            return 0;
        }

        return max($left, $right, $leftBottom, $rightBottom) - min($left, $right, $leftBottom, $rightBottom);
    }

    /**
     * Raíz del proyecto; las fuentes y el logo son recursos del código, no
     * del directorio de estado.
     */
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @param list<int> $rgb
     */
    private function color(GdImage $canvas, array $rgb): int
    {
        $red = max(0, min(255, $rgb[0]));
        $green = max(0, min(255, $rgb[1]));
        $blue = max(0, min(255, $rgb[2]));

        $color = imagecolorallocate($canvas, $red, $green, $blue);

        if ($color === false) {
            throw new ProcessingException('No se pudo asignar un color en la captura.');
        }

        return $color;
    }
}
