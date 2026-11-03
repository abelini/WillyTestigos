<?php

declare(strict_types=1);

namespace Willy\Exception;

final class RateLimitException extends AuthException
{
    public function httpStatus(): int
    {
        return 429;
    }

    public function errorCode(): string
    {
        return 'rate_limit_error';
    }
}
