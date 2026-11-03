<?php

declare(strict_types=1);

namespace Willy\Domain;

final class PdfResult
{
    public function __construct(
        public readonly string $path,
        public readonly int $pages,
        public readonly int $size,
        public readonly string $sha256,
    ) {
    }
}
