<?php

declare(strict_types=1);

namespace Willy\Tests;

use Willy\Support\UploadGuard;

final class FixedUploadGuard implements UploadGuard
{
    public function __construct(private readonly bool $isUploaded)
    {
    }

    public function isUploaded(string $path): bool
    {
        return $this->isUploaded;
    }
}
