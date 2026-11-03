<?php

declare(strict_types=1);

namespace Willy\Service;

use Throwable;
use Willy\Domain\DeliveryStatus;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Exception\ProcessingException;
use Willy\Repository\JobStore;
use Willy\Support\Clock;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\Logger;

use function mb_substr;

final class DeliveryService
{
    private const MAX_ERROR_LENGTH = 500;

    public function __construct(
        private readonly Config $config,
        private readonly JobStore $jobs,
        private readonly FileSystem $files,
        private readonly TelegramSender $telegram,
        private readonly MailSender $mail,
        private readonly Clock $clock,
        private readonly Logger $logger,
    ) {
    }

    public function deliver(string $jobId): Job
    {
        $job = $this->jobs->find($jobId);

        if (!$job->hasPdf()) {
            throw new ProcessingException('El trabajo no tiene un PDF para enviar.');
        }

        if (!$this->files->isFile($job->pdfPath)) {
            throw new ProcessingException('El PDF generado ya no está disponible.');
        }

        $now = $this->clock->timestamp($this->clock->now());
        $this->jobs->update(
            $jobId,
            static fn (Job $current): Job => $current->withStatus(JobStatus::Delivering, $now),
        );

        $this->sendTelegram($jobId, $now);
        $this->sendMail($jobId, $now);

        $expiresAt = $now + $this->config->pdfRetentionSeconds();

        return $this->jobs->update(
            $jobId,
            static fn (Job $current): Job => $current
                ->withStatus(JobStatus::Completed, $now)
                ->withFinishedAt($now, $now)
                ->withExpiresAt($expiresAt, $now),
        );
    }

    private function sendTelegram(string $jobId, int $now): void
    {
        $job = $this->jobs->find($jobId);

        if (!$this->config->hasTelegram()) {
            $this->jobs->update(
                $jobId,
                static fn (Job $current): Job => $current->withTelegram(
                    DeliveryStatus::Skipped,
                    'Telegram no está configurado.',
                    $now,
                ),
            );

            return;
        }

        try {
            $this->telegram->send($this->config->telegram(), $job->pdfPath, $job->title());
            $this->jobs->update(
                $jobId,
                static fn (Job $current): Job => $current->withTelegram(DeliveryStatus::Sent, '', $now),
            );
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo enviar el PDF por Telegram.', [
                'job' => $jobId,
                'message' => $exception->getMessage(),
            ]);

            $this->jobs->update(
                $jobId,
                fn (Job $current): Job => $current->withTelegram(
                    DeliveryStatus::Failed,
                    $this->message($exception),
                    $now,
                ),
            );
        }
    }

    private function sendMail(string $jobId, int $now): void
    {
        $job = $this->jobs->find($jobId);

        if (!$this->config->hasMail()) {
            $this->jobs->update(
                $jobId,
                static fn (Job $current): Job => $current->withMail(
                    DeliveryStatus::Skipped,
                    'El correo no está configurado.',
                    $now,
                ),
            );

            return;
        }

        try {
            $this->mail->send(
                $this->config->mail(),
                $job->pdfPath,
                $job->title(),
                'Adjuntamos el PDF con las capturas del mes de ' . $job->month->label() . '.',
            );
            $this->jobs->update(
                $jobId,
                static fn (Job $current): Job => $current->withMail(DeliveryStatus::Sent, '', $now),
            );
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo enviar el PDF por correo.', [
                'job' => $jobId,
                'message' => $exception->getMessage(),
            ]);

            $this->jobs->update(
                $jobId,
                fn (Job $current): Job => $current->withMail(
                    DeliveryStatus::Failed,
                    $this->message($exception),
                    $now,
                ),
            );
        }
    }

    private function message(Throwable $exception): string
    {
        return mb_substr($exception->getMessage(), 0, self::MAX_ERROR_LENGTH);
    }
}
