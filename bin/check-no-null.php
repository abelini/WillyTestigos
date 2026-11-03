<?php

declare(strict_types=1);

namespace Willy\Bin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;
use function sort;
use function strtolower;
use function substr_count;
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

    foreach ($tokens as $index => $token) {
        if (is_array($token)) {
            $line = $token[2];
            $id = $token[0];
            $text = $token[1];
        } else {
            $id = 0;
            $text = $token;
        }

        if (is_array($token)) {
            if ($id === T_COALESCE) {
                $violations[] = "{$file}:{$line} usa ??";
            }

            if ($id === T_COALESCE_EQUAL) {
                $violations[] = "{$file}:{$line} usa ??=";
            }

            if ($id === T_ISSET) {
                $violations[] = "{$file}:{$line} usa isset";
            }

            if ($id === T_NULLSAFE_OBJECT_OPERATOR) {
                $violations[] = "{$file}:{$line} usa ?->";
            }

            if ($id === T_STRING) {
                $lower = strtolower($text);

                if ($lower === 'null') {
                    $violations[] = "{$file}:{$line} usa null";
                }

                if ($lower === 'is_null') {
                    $violations[] = "{$file}:{$line} usa is_null";
                }
            }
        }

        if ($text === '?') {
            $previous = previousSignificant($tokens, $index);
            $next = nextSignificant($tokens, $index);

            if ($previous >= 0 && $next >= 0) {
                $previousToken = $tokens[$previous];
                $nextToken = $tokens[$next];

                if (is_array($nextToken) && isTypeToken($nextToken[0]) && isNullableContext($previousToken)) {
                    $violations[] = "{$file}:{$line} usa un tipo nullable";
                }
            }
        }

        $line += substr_count($text, "\n");
    }
}

if (count($violations) > 0) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . PHP_EOL);
    }

    fwrite(STDERR, 'Se encontraron ' . count($violations) . ' violaciones de la regla no-null.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'OK: sin null, ??, isset, is_null ni tipos nullable.' . PHP_EOL);

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function previousSignificant(array $tokens, int $index): int
{
    for ($i = $index - 1; $i >= 0; $i--) {
        if (!isIgnorable($tokens[$i])) {
            return $i;
        }
    }

    return -1;
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function nextSignificant(array $tokens, int $index): int
{
    $total = count($tokens);

    for ($i = $index + 1; $i < $total; $i++) {
        if (!isIgnorable($tokens[$i])) {
            return $i;
        }
    }

    return -1;
}

/**
 * @param array{0: int, 1: string, 2: int}|string $token
 */
function isIgnorable(array|string $token): bool
{
    if (is_string($token)) {
        return false;
    }

    return in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function isTypeToken(int $id): bool
{
    return in_array($id, [
        T_STRING,
        T_ARRAY,
        T_CALLABLE,
        T_STATIC,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ], true);
}

/**
 * @param array{0: int, 1: string, 2: int}|string $token
 */
function isNullableContext(array|string $token): bool
{
    if (is_string($token)) {
        return in_array($token, ['(', ',', ':'], true);
    }

    return in_array($token[0], [
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_STATIC,
        T_READONLY,
        T_VAR,
    ], true);
}
