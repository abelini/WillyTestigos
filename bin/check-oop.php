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
use function is_dir;
use function is_string;
use function sort;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function token_get_all;

$root = dirname(__DIR__);
$directories = [
    $root . '/app',
    $root . '/public',
];

$exact = [
    'date' => [],
    'gmdate' => [],
    'time' => [],
    'strtotime' => [],
    'mktime' => [],
    'checkdate' => [],
    'random_bytes' => [],
    'bin2hex' => [],
    'glob' => [],
    'scandir' => [],
    'opendir' => [],
    'readdir' => [],
    'file_get_contents' => [],
    'file_put_contents' => [],
    'htmlspecialchars' => ['app/Support/View.php'],
    'hash_file' => ['app/Support/FileSystem.php'],
    'disk_free_space' => ['app/Support/FileSystem.php'],
    'mkdir' => ['app/Support/FileSystem.php'],
    'rename' => ['app/Support/FileSystem.php'],
    'unlink' => ['app/Support/FileSystem.php'],
    'rmdir' => ['app/Support/FileSystem.php'],
    'copy' => ['app/Support/FileSystem.php'],
    'chmod' => ['app/Support/FileSystem.php'],
    'fopen' => ['app/Support/FileSystem.php', 'app/Support/Json.php'],
    'flock' => ['app/Support/FileSystem.php', 'app/Support/Json.php'],
    'fclose' => ['app/Support/FileSystem.php', 'app/Support/Json.php'],
    'json_encode' => ['app/Support/Json.php', 'app/Support/View.php'],
    'json_decode' => ['app/Support/Json.php'],
    'function_exists' => ['app/Support/Process.php'],
    'ini_get' => ['app/Support/Process.php', 'app/Support/Session.php'],
    'exec' => ['app/Support/Process.php'],
    'escapeshellarg' => ['app/Support/Process.php'],
    'setcookie' => ['app/Support/Session.php'],
];

$prefixes = [
    'curl_' => ['app/Support/HttpClient.php'],
    'image' => ['app/Service/ImageProcessor.php', 'tests/ImageFixture.php'],
    'exif_' => ['app/Service/ImageProcessor.php'],
    'password_' => ['app/Support/PasswordHasher.php'],
    'session_' => ['app/Support/Session.php'],
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
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
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

            if ($token[0] === T_STRING && isFunctionCall($tokens, $index)) {
                $name = strtolower($token[1]);

                if (in_array($name, array_keys($exact), true)) {
                    $allowed = $exact[$name];

                    if (!in_array($relative, $allowed, true)) {
                        $violations[] = "{$relative}:{$line} usa {$name}() fuera de su wrapper.";
                    }
                } else {
                    foreach ($prefixes as $prefix => $allowedFiles) {
                        if (str_starts_with($name, $prefix) && !in_array($relative, $allowedFiles, true)) {
                            $violations[] = "{$relative}:{$line} usa {$name}() fuera de su wrapper.";
                            break;
                        }
                    }
                }
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

    fwrite(STDERR, 'Se encontraron ' . count($violations) . ' usos de funciones fuera de sus wrappers.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'OK: funciones nativas dentro de sus wrappers.' . PHP_EOL);

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function isFunctionCall(array $tokens, int $index): bool
{
    $next = nextSignificant($tokens, $index);

    if ($next < 0 || $tokens[$next] !== '(') {
        return false;
    }

    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (is_string($token)) {
            if ($token === '\\') {
                return false;
            }

            if (in_array($token, ['(', ',', ':'], true)) {
                return true;
            }

            continue;
        }

        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return !in_array($token[0], [
            T_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
            T_FUNCTION,
            T_NEW,
            T_CONST,
            T_USE,
        ], true);
    }

    return false;
}

/**
 * @param list<array{0: int, 1: string, 2: int}|string> $tokens
 */
function nextSignificant(array $tokens, int $index): int
{
    $total = count($tokens);

    for ($i = $index + 1; $i < $total; $i++) {
        $token = $tokens[$i];

        if (is_string($token)) {
            return $i;
        }

        if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $i;
        }
    }

    return -1;
}
