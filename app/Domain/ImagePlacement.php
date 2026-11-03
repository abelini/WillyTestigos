<?php

declare(strict_types=1);

namespace Willy\Domain;

final class ImagePlacement
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly float $height,
    ) {
    }
}
