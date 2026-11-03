<?php

declare(strict_types=1);

namespace Willy\Support;

use Willy\Exception\StorageException;

use function array_key_exists;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;

final class Data
{
    /**
     * @param array<string, mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new StorageException("Campo inválido en el estado del trabajo: {$key}.");
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        if (!array_key_exists($key, $data)) {
            throw new StorageException("Falta el campo en el estado del trabajo: {$key}.");
        }

        $value = $data[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new StorageException("Campo numérico inválido en el estado del trabajo: {$key}.");
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        if (!array_key_exists($key, $data)) {
            throw new StorageException("Falta el campo en el estado del trabajo: {$key}.");
        }

        $value = $data[$key];

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw new StorageException("Campo booleano inválido en el estado del trabajo: {$key}.");
    }
}
