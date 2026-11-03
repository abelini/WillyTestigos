<?php

declare(strict_types=1);

namespace Willy\Exception;

use RuntimeException;

class AppException extends RuntimeException
{
    public function httpStatus(): int
    {
        return 500;
    }

    public function errorCode(): string
    {
        return 'app_error';
    }
}
