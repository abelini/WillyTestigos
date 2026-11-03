<?php

declare(strict_types=1);

namespace Willy\Support;

use Throwable;

final class Logger
{
    public function __construct(
        private readonly Paths $paths,
        private readonly FileSystem $files,
        private readonly Json $json,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'time' => $this->clock->iso($this->clock->now()),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        try {
            $this->files->ensureDirectory($this->paths->logs());
            $this->files->append($this->paths->logFile(), $this->json->encode($entry) . "\n");
        } catch (Throwable $exception) {
            // El logger nunca debe interrumpir el flujo principal.
        }
    }
}
