<?php

declare(strict_types=1);

namespace Willy\Exception;

class ConflictException extends AppException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'conflict';
    }
}
