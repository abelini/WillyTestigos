<?php

declare(strict_types=1);

namespace Willy\Exception;

final class ProcessingException extends AppException
{
    public function errorCode(): string
    {
        return 'processing_error';
    }
}
