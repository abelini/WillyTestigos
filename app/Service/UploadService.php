<?php

declare(strict_types=1);

namespace Willy\Service;

use Willy\Domain\ImageType;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Exception\ConflictException;
use Willy\Exception\StateException;
use Willy\Exception\ValidationException;
use Willy\Http\UploadedFile;
use Willy\Repository\JobStore;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\UploadGuard;

use function count;
use function in_array;
use function max;

final class UploadService
{
    public function __construct(
        private readonly Config $config,
        private readonly JobStore $jobs,
        private readonly JobFiles $files,
        private readonly ImageProcessor $images,
        private readonly FileSystem $system,
        private readonly Clock $clock,
        private readonly UploadGuard $guard,
    ) {
    }

    public function accept(string $jobId, UploadedFile $file, int $position): Job
    {
        if (!$file->isValid()) {
            throw new ValidationException($file->errorMessage());
        }

        $job = $this->jobs->find($jobId);

        if ($job->status !== JobStatus::Created && $job->status !== JobStatus::Uploading) {
            throw new StateException('El trabajo ya no acepta capturas.');
        }

        if ($position < 1 || $position > $this->config->maxFiles) {
            throw new ValidationException('La posición de la captura no es válida.');
        }

        if (!$this->guard->isUploaded($file->temporaryName)) {
            throw new ValidationException('La captura no proviene de una subida válida.');
        }

        $info = $this->images->inspect($file->temporaryName);

        if ($this->files->hasOriginal($jobId, $position)) {
            throw new ConflictException('Ya existe una captura en esa posición.');
        }

        if (($this->totalBytes($jobId) + $info->size) > $this->config->maxTotalBytes()) {
            throw new ValidationException('Las capturas superan el tamaño total permitido.');
        }

        $this->files->storeOriginal(
            $jobId,
            $file->temporaryName,
            $position,
            ImageType::fromMime($info->mime),
        );

        $now = $this->now();
        $uploaded = count($this->files->originals($jobId));

        return $this->jobs->update(
            $jobId,
            static fn (Job $current): Job => $current
                ->withStatus(JobStatus::Uploading, $now)
                ->withUploadedFiles($uploaded, $now)
                ->withTotalFiles(max($current->totalFiles, $position), $now),
        );
    }

    public function finish(string $jobId): Job
    {
        $job = $this->jobs->find($jobId);

        if (!in_array($job->status, [JobStatus::Created, JobStatus::Uploading, JobStatus::Queued], true)) {
            throw new StateException('El trabajo no se puede iniciar en su estado actual.');
        }

        $uploaded = count($this->files->originals($jobId));

        if ($uploaded === 0) {
            throw new ValidationException('Debes subir al menos una captura.');
        }

        $now = $this->now();

        return $this->jobs->update(
            $jobId,
            static fn (Job $current): Job => $current
                ->withStatus(JobStatus::Queued, $now)
                ->withTotalFiles($uploaded, $now)
                ->withUploadedFiles($uploaded, $now),
        );
    }

    private function totalBytes(string $jobId): int
    {
        $total = 0;

        foreach ($this->files->originals($jobId) as $path) {
            $total += $this->system->size($path);
        }

        return $total;
    }

    private function now(): int
    {
        return $this->clock->nowTimestamp();
    }
}
