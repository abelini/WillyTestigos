<?php

declare(strict_types=1);

namespace Willy\Tests;

use GdImage;

use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefill;
use function imagejpeg;
use function is_int;

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

        $image = imagecreatetruecolor($width, $height);

        if (!$image instanceof GdImage) {
            return;
        }

        $color = imagecolorallocate($image, $red, $green, $blue);

        if (!is_int($color)) {
            return;
        }

        imagefill($image, 0, 0, $color);
        imagejpeg($image, $path, 85);
    }
}
