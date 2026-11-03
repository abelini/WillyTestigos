<?php

declare(strict_types=1);

namespace Willy\Support;

use function call_user_func;
use function escapeshellarg;
use function exec;
use function explode;
use function function_exists;
use function ini_get;
use function trim;

final class Process
{
    public function canSpawnDetached(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        if (!function_exists('exec')) {
            return false;
        }

        $disabled = ini_get('disable_functions');

        if ($disabled === false) {
            return true;
        }

        $functions = explode(',', $disabled);

        foreach ($functions as $function) {
            if (trim($function) === 'exec') {
                return false;
            }
        }

        return true;
    }

    public function spawnWorker(string $phpBinary, string $workerScript, string $jobId, string $logPath): bool
    {
        if (!$this->canSpawnDetached()) {
            return false;
        }

        $command = escapeshellarg($phpBinary)
            . ' '
            . escapeshellarg($workerScript)
            . ' '
            . escapeshellarg($jobId);

        $full = 'nohup ' . $command . ' >> ' . escapeshellarg($logPath) . ' 2>&1 &';
        $output = [];
        $code = 0;
        exec($full, $output, $code);

        return $code === 0;
    }

    public function detach(): void
    {
        ignore_user_abort(true);

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
    }

    public function finishRequest(): void
    {
        foreach (['litespeed_finish_request', 'fastcgi_finish_request'] as $function) {
            if (!function_exists($function)) {
                continue;
            }

            call_user_func($function);

            return;
        }
    }
}
