<?php

declare(strict_types=1);

namespace Willy\Exception;

final class DeliveryException extends AppException
{
    public function httpStatus(): int
    {
        return 502;
    }

    public function errorCode(): string
    {
        return 'delivery_error';
    }
}
