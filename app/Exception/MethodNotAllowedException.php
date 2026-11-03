<?php

declare(strict_types=1);

namespace Willy\Exception;

final class MethodNotAllowedException extends AppException
{
    public function httpStatus(): int
    {
        return 405;
    }

    public function errorCode(): string
    {
        return 'method_not_allowed';
    }
}
