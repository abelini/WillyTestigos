<?php

declare(strict_types=1);

namespace Willy\Service;

use Willy\Domain\DispatchMode;
use Willy\Domain\Job;
use Willy\Exception\ProcessingException;
use Willy\Support\Config;
use Willy\Support\Logger;
use Willy\Support\Paths;
use Willy\Support\Process;

final class JobDispatcher
{
    public function __construct(
        private readonly Config $config,
        private readonly Paths $paths,
        private readonly Process $process,
        private readonly Logger $logger,
    ) {
    }

    public function dispatch(Job $job): DispatchMode
    {
        if ($this->config->workerMode === 'inline') {
            return DispatchMode::Inline;
        }

        $spawned = $this->process->spawnWorker(
            $this->config->phpBinary,
            $this->paths->workerScript(),
            $job->id,
            $this->paths->workerLog($job->id),
        );

        if ($spawned) {
            return DispatchMode::Background;
        }

        if ($this->config->workerMode === 'background') {
            throw new ProcessingException('No se pudo iniciar el proceso en segundo plano.');
        }

        $this->logger->warning('No se pudo iniciar el trabajador; se procesará en línea.', [
            'job' => $job->id,
        ]);

        return DispatchMode::Inline;
    }
}
