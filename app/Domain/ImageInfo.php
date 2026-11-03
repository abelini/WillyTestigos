<?php

declare(strict_types=1);

namespace Willy\Domain;

final class ImageInfo
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly string $mime,
        public readonly int $size,
    ) {
    }

    public function pixels(): int
    {
        return $this->width * $this->height;
    }
}
