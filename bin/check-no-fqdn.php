<?php

declare(strict_types=1);

namespace Willy\Bin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function dirname;
use function file_get_contents;
use function is_array;
use function is_dir;
use function sort;
use function token_get_all;

$root = dirname(__DIR__);
$directories = [
    $root . '/app',
    $root . '/public',
    $root . '/bin',
    $root . '/tests',
];

$files = [];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

$violations = [];

foreach ($files as $file) {
    $source = file_get_contents($file);

    if ($source === false) {
        fwrite(STDERR, "No se pudo leer {$file}" . PHP_EOL);
        exit(1);
    }

    /** @var list<array{0: int, 1: string, 2: int}|string> $tokens */
    $tokens = token_get_all($source);
    $line = 1;

    foreach ($tokens as $token) {
        if (is_array($token)) {
            $line = $token[2];

            if ($token[0] === T_NAME_FULLY_QUALIFIED) {
                $violations[] = "{$file}:{$line} usa FQDN: {$token[1]}";
            }
        }

        $text = is_array($token) ? $token[1] : $token;
        $line += substr_count($text, "\n");
    }
}

if (count($violations) > 0) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . PHP_EOL);
    }

    fwrite(STDERR, 'Se encontraron ' . count($violations) . ' FQDN. Usa imports con use.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'OK: sin nombres totalmente calificados.' . PHP_EOL);
