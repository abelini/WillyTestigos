<?php

declare(strict_types=1);

namespace Willy\Http;

use function header;
use function http_response_code;

final class ResponseEmitter
{
    public function emit(Response $response): void
    {
        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->body;

        $after = $response->after;
        $after();
    }
}
