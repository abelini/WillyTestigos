<?php

declare(strict_types=1);

namespace Willy\Service;

use Throwable;
use Willy\Domain\Job;
use Willy\Repository\JobStore;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\Logger;
use Willy\Support\Paths;

final class CleanupService
{
    private const STAMP_INTERVAL = 300;
    private const SESSION_TTL = 86400;
    private const LOG_MAX_BYTES = 5_242_880;

    public function __construct(
        private readonly Paths $paths,
        private readonly FileSystem $files,
        private readonly JobStore $jobs,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    public function maybeRun(): int
    {
        $now = $this->clock->timestamp($this->clock->now());
        $stamp = $this->paths->cleanupStamp();

        if ($this->files->isFile($stamp) && ($this->files->lastModified($stamp) + self::STAMP_INTERVAL) > $now) {
            return 0;
        }

        $this->files->writeAtomic($stamp, (string) $now);

        return $this->run();
    }

    public function run(): int
    {
        $now = $this->clock->timestamp($this->clock->now());
        $removed = 0;

        foreach ($this->jobs->all() as $job) {
            if (!$this->shouldRemove($job, $now)) {
                continue;
            }

            try {
                $this->jobs->delete($job->id);
                $removed++;
            } catch (Throwable $exception) {
                $this->logger->error('No se pudo limpiar un trabajo.', [
                    'job' => $job->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->cleanupSessions($now);
        $this->trimLog();

        return $removed;
    }

    private function shouldRemove(Job $job, int $now): bool
    {
        if ($job->hasExpiry() && $job->isExpired($now)) {
            return true;
        }

        if ($job->isStale($now, $this->config->staleJobSeconds())) {
            return true;
        }

        if ($job->hasError() && ($job->updatedAt + $this->config->failureRetentionSeconds()) <= $now) {
            return true;
        }

        if ($job->isFinished() && !$job->hasExpiry() && !$job->hasError()) {
            return ($job->updatedAt + $this->config->pdfRetentionSeconds()) <= $now;
        }

        return false;
    }

    private function cleanupSessions(int $now): void
    {
        $directory = $this->paths->sessions();

        if (!$this->files->isDirectory($directory)) {
            return;
        }

        foreach ($this->files->listFiles($directory . '/sess_*') as $session) {
            try {
                if (($this->files->lastModified($session) + self::SESSION_TTL) > $now) {
                    continue;
                }

                $this->files->delete($session);
            } catch (Throwable $exception) {
                continue;
            }
        }
    }

    private function trimLog(): void
    {
        $log = $this->paths->logFile();

        if (!$this->files->isFile($log)) {
            return;
        }

        try {
            if ($this->files->size($log) <= self::LOG_MAX_BYTES) {
                return;
            }

            $this->files->writeAtomic($log, '');
        } catch (Throwable $exception) {
            return;
        }
    }
}
