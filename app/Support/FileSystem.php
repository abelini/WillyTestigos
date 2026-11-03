<?php

declare(strict_types=1);

namespace Willy\Support;

use DirectoryIterator;
use FilesystemIterator;
use GlobIterator;
use Random\Randomizer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SplFileObject;
use Willy\Exception\StorageException;

use function copy;
use function dirname;
use function disk_free_space;
use function hash_file;
use function is_dir;
use function is_file;
use function is_int;
use function mkdir;
use function rename;
use function rmdir;
use function sort;
use function strlen;
use function unlink;

final class FileSystem
{
    public function __construct(private readonly Randomizer $random)
    {
    }

    public function ensurePaths(Paths $paths): void
    {
        $this->ensureDirectory($paths->storage);
        $this->ensureDirectory($paths->jobs());
        $this->ensureDirectory($paths->logs());
        $this->ensureDirectory($paths->sessions());
        $this->ensureDirectory($paths->security());
    }

    public function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new StorageException("No se pudo crear el directorio {$path}.");
        }
    }

    public function exists(string $path): bool
    {
        $info = new SplFileInfo($path);

        return $info->isFile() || $info->isDir();
    }

    public function isFile(string $path): bool
    {
        return new SplFileInfo($path)->isFile();
    }

    public function isDirectory(string $path): bool
    {
        return new SplFileInfo($path)->isDir();
    }

    public function isWritable(string $path): bool
    {
        return new SplFileInfo($path)->isWritable();
    }

    public function read(string $path): string
    {
        if (!$this->isFile($path)) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        $file = new SplFileObject($path, 'rb');
        $size = $file->getSize();

        if (!is_int($size)) {
            throw new StorageException("No se pudo leer el tamaño de {$path}.");
        }

        if ($size === 0) {
            return '';
        }

        $contents = $file->fread($size);

        if ($contents === false) {
            throw new StorageException("No se pudo leer el archivo {$path}.");
        }

        return $contents;
    }

    public function writeAtomic(string $path, string $contents): void
    {
        $directory = dirname($path);
        $this->ensureDirectory($directory);

        $temporary = $path . '.' . $this->random->getBytesFromString('0123456789abcdef', 12) . '.tmp';
        $file = new SplFileObject($temporary, 'wb');
        $written = $file->fwrite($contents);

        if (!is_int($written) || $written !== strlen($contents)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new StorageException("No se pudo escribir el archivo {$path}.");
        }

        unset($file);

        if (rename($temporary, $path)) {
            return;
        }

        if (is_file($path) && !unlink($path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new StorageException("No se pudo reemplazar el archivo {$path}.");
        }

        if (!rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new StorageException("No se pudo mover el archivo temporal a {$path}.");
        }
    }

    public function append(string $path, string $contents): void
    {
        $this->ensureDirectory(dirname($path));

        $file = new SplFileObject($path, 'ab');
        $written = $file->fwrite($contents);

        if (!is_int($written)) {
            throw new StorageException("No se pudo agregar contenido a {$path}.");
        }
    }

    public function delete(string $path): void
    {
        $info = new SplFileInfo($path);

        if ($info->isFile()) {
            if (!unlink($path)) {
                throw new StorageException("No se pudo borrar el archivo {$path}.");
            }

            return;
        }

        if (!$info->isDir()) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $itemPath = $item->getPathname();

            if ($item->isDir()) {
                if (!rmdir($itemPath)) {
                    throw new StorageException("No se pudo borrar el directorio {$itemPath}.");
                }

                continue;
            }

            if (!unlink($itemPath)) {
                throw new StorageException("No se pudo borrar el archivo {$itemPath}.");
            }
        }

        if (!rmdir($path)) {
            throw new StorageException("No se pudo borrar el directorio {$path}.");
        }
    }

    public function deleteContents(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $this->delete($item->getPathname());
        }
    }

    /**
     * @return list<string>
     */
    public function listFiles(string $pattern): array
    {
        $files = [];
        $iterator = new GlobIterator($pattern, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return list<string>
     */
    public function listDirectories(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $directories = [];
        $iterator = new DirectoryIterator($directory);

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isDir()) {
                $directories[] = $item->getPathname();
            }
        }

        sort($directories, SORT_STRING);

        return $directories;
    }

    public function size(string $path): int
    {
        $size = new SplFileInfo($path)->getSize();

        if (!is_int($size)) {
            throw new StorageException("No se pudo obtener el tamaño de {$path}.");
        }

        return $size;
    }

    public function sha256(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new StorageException("No se pudo calcular el hash de {$path}.");
        }

        return $hash;
    }

    public function move(string $from, string $to): void
    {
        $this->ensureDirectory(dirname($to));

        if (!rename($from, $to)) {
            throw new StorageException("No se pudo mover {$from} a {$to}.");
        }
    }

    public function copy(string $from, string $to): void
    {
        $this->ensureDirectory(dirname($to));

        if (!copy($from, $to)) {
            throw new StorageException("No se pudo copiar {$from} a {$to}.");
        }
    }

    public function setPermissions(string $path, int $permissions): void
    {
        if (!chmod($path, $permissions)) {
            throw new StorageException("No se pudieron ajustar los permisos de {$path}.");
        }
    }

    public function lastModified(string $path): int
    {
        $timestamp = new SplFileInfo($path)->getMTime();

        if (!is_int($timestamp)) {
            throw new StorageException("No se pudo obtener la fecha de {$path}.");
        }

        return $timestamp;
    }

    public function diskFree(string $path): float
    {
        $free = disk_free_space($path);

        if ($free === false) {
            throw new StorageException("No se pudo consultar el espacio libre de {$path}.");
        }

        return $free;
    }
}
