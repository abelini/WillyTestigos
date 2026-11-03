<?php

declare(strict_types=1);

namespace Willy\Support;

use JsonException;
use Willy\Exception\NotFoundException;
use Willy\Exception\StorageException;

use function dirname;
use function fclose;
use function flock;
use function fopen;
use function is_array;
use function json_decode;
use function json_encode;

final class Json
{
    public function __construct(private readonly FileSystem $files)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        return $this->decode($this->files->read($path));
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $raw): array
    {
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new StorageException('El contenido no es un JSON válido.');
        }

        if (!is_array($data)) {
            throw new StorageException('El contenido no es un objeto JSON.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function readOrDefault(string $path): array
    {
        if (!$this->files->exists($path)) {
            return [];
        }

        return $this->read($path);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $path, array $data): void
    {
        $this->files->writeAtomic($path, $this->encode($data) . "\n");
    }

    /**
     * @param array<string, mixed> $data
     */
    public function encode(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            );
        } catch (JsonException $exception) {
            throw new StorageException('No se pudo codificar el JSON.');
        }
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     * @param array<string, mixed> $initial
     * @return array<string, mixed>
     */
    public function update(string $path, callable $mutator, array $initial, bool $requireExisting): array
    {
        $this->files->ensureDirectory(dirname($path));
        $handle = fopen($path . '.lock', 'c');

        if ($handle === false) {
            throw new StorageException("No se pudo abrir el lock de {$path}.");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException("No se pudo bloquear {$path}.");
            }

            if (!$this->files->exists($path)) {
                if ($requireExisting) {
                    throw new NotFoundException("No existe el archivo {$path}.");
                }

                $data = $initial;
            } else {
                $data = $this->read($path);
            }

            $updated = $mutator($data);
            $this->write($path, $updated);

            return $updated;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
