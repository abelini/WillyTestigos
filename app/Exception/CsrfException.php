<?php

declare(strict_types=1);

namespace Willy\Exception;

final class CsrfException extends AuthException
{
    public function httpStatus(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'csrf_error';
    }
}
