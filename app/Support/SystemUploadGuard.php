<?php

declare(strict_types=1);

namespace Willy\Support;

use function is_uploaded_file;

final class SystemUploadGuard implements UploadGuard
{
    public function isUploaded(string $path): bool
    {
        return is_uploaded_file($path);
    }
}
