<?php

declare(strict_types=1);

namespace Willy\Exception;

final class ValidationException extends AppException
{
    public function httpStatus(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'validation_error';
    }
}
