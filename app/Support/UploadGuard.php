<?php

declare(strict_types=1);

namespace Willy\Support;

/**
 * Verifica que una ruta proviene de una subida HTTP real del servidor.
 */
interface UploadGuard
{
    public function isUploaded(string $path): bool;
}
