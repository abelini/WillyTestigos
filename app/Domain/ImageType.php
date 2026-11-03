<?php

declare(strict_types=1);

namespace Willy\Domain;

use Willy\Exception\ValidationException;

enum ImageType: string
{
    case Jpeg = 'jpeg';
    case Png = 'png';

    public static function fromMime(string $mime): self
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => self::Jpeg,
            'image/png' => self::Png,
            default => throw new ValidationException('Solo se permiten capturas JPEG o PNG.'),
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
        };
    }

    public function mime(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
        };
    }
}
