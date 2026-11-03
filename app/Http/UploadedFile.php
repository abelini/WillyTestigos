<?php

declare(strict_types=1);

namespace Willy\Http;

use Willy\Exception\ValidationException;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;

final class UploadedFile
{
    private function __construct(
        public readonly string $name,
        public readonly string $temporaryName,
        public readonly string $mime,
        public readonly int $error,
        public readonly int $size,
    ) {
    }

    /**
     * @param array<array-key, mixed> $files
     */
    public static function fromGlobals(array $files, string $key): self
    {
        if (!array_key_exists($key, $files) || !is_array($files[$key])) {
            throw new ValidationException('No se recibió ninguna captura.');
        }

        $file = $files[$key];

        return new self(
            name: self::stringField($file, 'name'),
            temporaryName: self::stringField($file, 'tmp_name'),
            mime: self::stringField($file, 'type'),
            error: self::intField($file, 'error'),
            size: self::intField($file, 'size'),
        );
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->temporaryName !== '';
    }

    public function errorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK => 'La captura se subió correctamente.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La captura supera el tamaño permitido por el servidor.',
            UPLOAD_ERR_PARTIAL => 'La captura se subió de forma incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ninguna captura.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene un directorio temporal disponible.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir la captura.',
            UPLOAD_ERR_EXTENSION => 'Una extensión del servidor detuvo la subida.',
            default => 'No se pudo subir la captura.',
        };
    }

    /**
     * @param array<array-key, mixed> $file
     */
    private static function stringField(array $file, string $key): string
    {
        if (!array_key_exists($key, $file) || !is_string($file[$key])) {
            throw new ValidationException('La captura recibida no es válida.');
        }

        return $file[$key];
    }

    /**
     * @param array<array-key, mixed> $file
     */
    private static function intField(array $file, string $key): int
    {
        if (!array_key_exists($key, $file) || !is_int($file[$key])) {
            throw new ValidationException('La captura recibida no es válida.');
        }

        return $file[$key];
    }
}
