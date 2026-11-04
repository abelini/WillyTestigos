<?php

declare(strict_types=1);

namespace Willy\Tests;

use GdImage;
use RuntimeException;

use function imagecolorallocate;
use function imagecolorat;
use function imagecolorsforindex;
use function imagecreatetruecolor;
use function imagefill;
use function imagejpeg;

final class ImageFixture
{
    public static function jpeg(string $path, int $width, int $height, int $red = 40, int $green = 90, int $blue = 150): void
    {
        if ($width < 1 || $height < 1) {
            return;
        }

        if ($red < 0 || $red > 255 || $green < 0 || $green > 255 || $blue < 0 || $blue > 255) {
            return;
        }

        $image = self::image($width, $height, $red, $green, $blue);

        imagejpeg($image, $path, 85);
    }

    public static function image(int $width, int $height, int $red, int $green, int $blue): GdImage
    {
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Las dimensiones de la imagen de prueba no son válidas.');
        }

        if ($red < 0 || $red > 255 || $green < 0 || $green > 255 || $blue < 0 || $blue > 255) {
            throw new RuntimeException('El color de prueba no es válido.');
        }

        $image = imagecreatetruecolor($width, $height);

        if (!$image instanceof GdImage) {
            throw new RuntimeException('No se pudo crear la imagen de prueba.');
        }

        $color = imagecolorallocate($image, $red, $green, $blue);

        if ($color === false) {
            throw new RuntimeException('No se pudo asignar el color de prueba.');
        }

        imagefill($image, 0, 0, $color);

        return $image;
    }

    /**
     * @return array{red: int, green: int, blue: int}
     */
    public static function rgbAt(GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);

        if ($color === false) {
            throw new RuntimeException('No se pudo leer el color de la imagen de prueba.');
        }

        $parts = imagecolorsforindex($image, $color);

        return [
            'red' => (int) $parts['red'],
            'green' => (int) $parts['green'],
            'blue' => (int) $parts['blue'],
        ];
    }
}
