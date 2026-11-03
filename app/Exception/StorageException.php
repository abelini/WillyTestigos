<?php

declare(strict_types=1);

namespace Willy\Exception;

final class StorageException extends AppException
{
    public function errorCode(): string
    {
        return 'storage_error';
    }
}
