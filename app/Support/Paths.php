<?php

declare(strict_types=1);

namespace Willy\Support;

use Willy\Exception\ValidationException;

use function preg_match;
use function rtrim;
use function str_replace;

final class Paths
{
    public readonly string $root;

    public readonly string $storage;

    public function __construct(string $root, string $storage)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->storage = rtrim(str_replace('\\', '/', $storage), '/');
    }

    public function jobs(): string
    {
        return $this->storage . '/jobs';
    }

    public function jobDir(string $jobId): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $jobId) !== 1) {
            throw new ValidationException('Identificador de trabajo inválido.');
        }

        return $this->jobs() . '/' . $jobId;
    }

    public function jobFile(string $jobId): string
    {
        return $this->jobDir($jobId) . '/job.json';
    }

    public function originalsDir(string $jobId): string
    {
        return $this->jobDir($jobId) . '/originals';
    }

    public function processedDir(string $jobId): string
    {
        return $this->jobDir($jobId) . '/processed';
    }

    public function outputDir(string $jobId): string
    {
        return $this->jobDir($jobId) . '/output';
    }

    public function workerLock(string $jobId): string
    {
        return $this->jobDir($jobId) . '/worker.lock';
    }

    public function workerLog(string $jobId): string
    {
        return $this->jobDir($jobId) . '/worker.log';
    }

    public function logs(): string
    {
        return $this->storage . '/logs';
    }

    public function logFile(): string
    {
        return $this->logs() . '/app.log';
    }

    public function sessions(): string
    {
        return $this->storage . '/sessions';
    }

    public function security(): string
    {
        return $this->storage . '/security';
    }

    public function loginAttempts(): string
    {
        return $this->security() . '/login-attempts.json';
    }

    public function cleanupStamp(): string
    {
        return $this->storage . '/cleanup.stamp';
    }

    public function publicDir(): string
    {
        return $this->root . '/public';
    }

    public function workerScript(): string
    {
        return $this->root . '/bin/worker.php';
    }

    public function views(): string
    {
        return $this->root . '/resources/views';
    }
}
