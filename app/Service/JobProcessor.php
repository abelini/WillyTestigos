<?php

declare(strict_types=1);

namespace Willy\Service;

use Throwable;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Exception\ProcessingException;
use Willy\Repository\JobStore;
use Willy\Support\Clock;
use Willy\Support\Lock;
use Willy\Support\Logger;
use Willy\Support\Paths;

use function mb_substr;

final class JobProcessor
{
    private const MAX_ERROR_LENGTH = 500;

    public function __construct(
        private readonly JobStore $jobs,
        private readonly JobFiles $files,
        private readonly ImageProcessor $images,
        private readonly PdfBuilder $pdf,
        private readonly DeliveryService $delivery,
        private readonly Paths $paths,
        private readonly Clock $clock,
        private readonly Logger $logger,
    ) {
    }

    public function process(string $jobId): Job
    {
        $lock = Lock::acquire($this->paths->workerLock($jobId), false);

        if (!$lock->isAcquired()) {
            throw new ProcessingException('El trabajo ya se está procesando.');
        }

        try {
            return $this->run($jobId);
        } catch (Throwable $exception) {
            $this->fail($jobId, $exception);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function run(string $jobId): Job
    {
        $job = $this->jobs->find($jobId);

        if ($job->isFinished()) {
            return $job;
        }

        $now = $this->clock->timestamp($this->clock->now());
        $this->jobs->update(
            $jobId,
            static fn (Job $current): Job => $current
                ->withStatus(JobStatus::Processing, $now)
                ->withStartedAt($current->hasStarted() ? $current->startedAt : $now, $now),
        );

        $this->files->prepare($jobId);
        $originals = $this->files->originals($jobId);

        if ($originals === []) {
            throw new ProcessingException('No hay capturas para generar el PDF.');
        }

        $images = [];
        $sequence = 1;

        foreach ($originals as $original) {
            $images[] = $this->images->process(
                $original,
                $this->files->processedPath($jobId, $sequence),
            );

            $processed = $sequence;
            $this->jobs->update(
                $jobId,
                static fn (Job $current): Job => $current->withProcessedFiles($processed, $now),
            );

            $sequence++;
        }

        $result = $this->pdf->build($job, $images, $this->files->pdfPath($jobId, $job->month));

        $this->jobs->update(
            $jobId,
            static fn (Job $current): Job => $current
                ->withPdf($result, $now)
                ->withStatus(JobStatus::Delivering, $now),
        );

        $this->files->clearWorking($jobId);

        return $this->delivery->deliver($jobId);
    }

    private function fail(string $jobId, Throwable $exception): void
    {
        $this->logger->error('Falló el procesamiento del trabajo.', [
            'job' => $jobId,
            'message' => $exception->getMessage(),
        ]);

        try {
            $now = $this->clock->timestamp($this->clock->now());
            $this->jobs->update(
                $jobId,
                fn (Job $current): Job => $current
                    ->withError($this->message($exception), $now)
                    ->withStatus(JobStatus::Failed, $now)
                    ->withFinishedAt($now, $now),
            );
            $this->files->clearWorking($jobId);
        } catch (Throwable $cleanupFailure) {
            $this->logger->error('No se pudo registrar el fallo del trabajo.', [
                'job' => $jobId,
                'message' => $cleanupFailure->getMessage(),
            ]);
        }
    }

    private function message(Throwable $exception): string
    {
        return mb_substr($exception->getMessage(), 0, self::MAX_ERROR_LENGTH);
    }
}
