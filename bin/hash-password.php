<?php

declare(strict_types=1);

namespace Willy\Bin;

use Throwable;
use Willy\Support\PasswordHasher;

use function array_key_exists;
use function dirname;
use function fgets;
use function fwrite;
use function is_array;
use function is_string;
use function strlen;
use function trim;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'];
$password = '';

if (is_array($arguments) && array_key_exists(1, $arguments) && is_string($arguments[1])) {
    $password = $arguments[1];
} else {
    fwrite(STDOUT, 'Contraseña: ');
    $line = fgets(STDIN);
    $password = is_string($line) ? trim($line) : '';
}

if (strlen($password) < 8) {
    fwrite(STDERR, 'La contraseña debe tener al menos 8 caracteres.' . PHP_EOL);
    exit(1);
}

try {
    $hash = (new PasswordHasher())->hash($password);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, $hash . PHP_EOL);
fwrite(STDERR, 'Copia el hash en APP_PASSWORD_HASH dentro de .env.' . PHP_EOL);

exit(0);
