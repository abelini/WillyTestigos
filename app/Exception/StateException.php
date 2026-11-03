<?php

declare(strict_types=1);

namespace Willy\Exception;

final class StateException extends ConflictException
{
    public function errorCode(): string
    {
        return 'state_error';
    }
}
