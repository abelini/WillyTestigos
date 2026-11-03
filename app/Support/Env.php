<?php

declare(strict_types=1);

namespace Willy\Support;

use Dotenv\Dotenv;
use Willy\Exception\ConfigurationException;

use function array_key_exists;
use function getenv;
use function in_array;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

final class Env
{
    public static function load(string $root): void
    {
        Dotenv::createImmutable($root)->safeLoad();
    }

    public static function string(string $key, string $default): string
    {
        $value = self::raw($key);

        if ($value === '') {
            return $default;
        }

        return $value;
    }

    public static function required(string $key): string
    {
        $value = self::raw($key);

        if ($value === '') {
            throw new ConfigurationException("Falta la variable de entorno {$key}.");
        }

        return $value;
    }

    public static function int(string $key, int $default, int $min, int $max): int
    {
        $raw = self::raw($key);

        if ($raw === '') {
            return $default;
        }

        if (preg_match('/^-?\d+$/', $raw) !== 1) {
            throw new ConfigurationException("La variable {$key} debe ser un número entero.");
        }

        $value = (int) $raw;

        if ($value < $min || $value > $max) {
            throw new ConfigurationException("La variable {$key} debe estar entre {$min} y {$max}.");
        }

        return $value;
    }

    public static function bool(string $key, bool $default): bool
    {
        $raw = strtolower(self::raw($key));

        if ($raw === '') {
            return $default;
        }

        if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new ConfigurationException("La variable {$key} debe ser un booleano.");
    }

    public static function raw(string $key): string
    {
        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];

            if (is_string($value)) {
                return trim($value);
            }
        }

        $value = getenv($key);

        if ($value === false) {
            return '';
        }

        return trim($value);
    }
}
