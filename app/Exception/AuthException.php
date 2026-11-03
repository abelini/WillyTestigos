<?php

declare(strict_types=1);

namespace Willy\Exception;

class AuthException extends AppException
{
    public function httpStatus(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'auth_error';
    }
}
