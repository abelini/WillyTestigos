<?php

declare(strict_types=1);

namespace Willy\Service;

use finfo;
use GdImage;
use SplFileInfo;
use Willy\Domain\ImageInfo;
use Willy\Domain\ImageType;
use Willy\Domain\ProcessedImage;
use Willy\Exception\ProcessingException;
use Willy\Exception\ValidationException;
use Willy\Support\Config;
use Willy\Support\FileSystem;

use function dirname;
use function getimagesize;
use function imagecolorallocate;
use function imagecopyresampled;
use function imagecreatefromstring;
use function imagecreatetruecolor;
use function imagefill;
use function imagejpeg;
use function imagesx;
use function imagesy;
use function is_int;
use function is_string;
use function max;
use function min;
use function round;

final class ImageProcessor
{
    private const MAX_SIDE = 20000;
    private const MAX_OUTPUT_SIDE = 1100;

    public function __construct(
        private readonly FileSystem $files,
        private readonly Config $config,
        private readonly DeviceFrame $frame,
    ) {
    }

    public function inspect(string $path): ImageInfo
    {
        $info = new SplFileInfo($path);

        if (!$info->isFile()) {
            throw new ValidationException('La captura no existe o no es un archivo.');
        }

        $size = $info->getSize();

        if (!is_int($size) || $size <= 0) {
            throw new ValidationException('La captura está vacía.');
        }

        if ($size > $this->config->maxFileBytes()) {
            throw new ValidationException('La captura supera el tamaño máximo permitido.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (!is_string($mime)) {
            throw new ValidationException('No se pudo determinar el tipo de la captura.');
        }

        $type = ImageType::fromMime($mime);
        $dimensions = getimagesize($path);

        if ($dimensions === false) {
            throw new ValidationException('La captura no es una imagen válida.');
        }

        $width = $dimensions[0];
        $height = $dimensions[1];

        if ($width <= 0 || $height <= 0) {
            throw new ValidationException('La captura tiene dimensiones inválidas.');
        }

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new ValidationException('La captura tiene dimensiones demasiado grandes.');
        }

        if (($width * $height) > $this->config->maxPixels()) {
            throw new ValidationException('La captura tiene demasiados píxeles.');
        }

        return new ImageInfo($width, $height, $type->mime(), $size);
    }

    public function process(string $source, string $destination): ProcessedImage
    {
        $this->inspect($source);
        $contents = $this->files->read($source);
        $image = imagecreatefromstring($contents);

        if (!$image instanceof GdImage) {
            throw new ProcessingException('No se pudo leer la imagen para procesarla.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $scale = min(1.0, self::MAX_OUTPUT_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$canvas instanceof GdImage) {
            throw new ProcessingException('No se pudo crear el lienzo de la imagen.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if (!is_int($white)) {
            throw new ProcessingException('No se pudo preparar el fondo de la imagen.');
        }

        imagefill($canvas, 0, 0, $white);

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        $this->files->ensureDirectory(dirname($destination));

        $framed = $this->frame->apply($canvas);

        if (!imagejpeg($framed, $destination, $this->config->jpegQuality)) {
            throw new ProcessingException('No se pudo guardar la imagen procesada.');
        }

        return new ProcessedImage($destination, imagesx($framed), imagesy($framed));
    }
}
