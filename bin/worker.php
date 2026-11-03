<?php

declare(strict_types=1);

use Throwable;
use Willy\Container;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'];

if (
    !is_array($arguments)
    || !array_key_exists(1, $arguments)
    || !is_string($arguments[1])
    || $arguments[1] === ''
) {
    fwrite(STDERR, 'Uso: php bin/worker.php <job-id>' . PHP_EOL);
    exit(1);
}

try {
    $container = Container::create(dirname(__DIR__));
    $container->processor->process($arguments[1]);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
