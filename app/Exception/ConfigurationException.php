<?php

declare(strict_types=1);

namespace Willy\Exception;

final class ConfigurationException extends AppException
{
    public function errorCode(): string
    {
        return 'configuration_error';
    }
}
