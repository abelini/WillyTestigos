<?php

declare(strict_types=1);

namespace Willy\Domain;

use IntlDateFormatter;
use ValueError;
use Willy\Exception\StorageException;
use Willy\Support\Clock;
use Willy\Support\Data;

use function class_exists;
use function floor;
use function intdiv;
use function is_string;
use function mb_substr;
use function number_format;
use function str_replace;

final class Job
{
    public function __construct(
        public readonly string $id,
        public readonly Month $month,
        public readonly string $coverSubtitle,
        public readonly JobStatus $status,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly int $totalFiles,
        public readonly int $uploadedFiles,
        public readonly int $processedFiles,
        public readonly string $pdfPath,
        public readonly int $pdfSize,
        public readonly int $pdfPages,
        public readonly string $pdfSha256,
        public readonly string $error,
        public readonly DeliveryStatus $telegramStatus,
        public readonly string $telegramError,
        public readonly DeliveryStatus $mailStatus,
        public readonly string $mailError,
        public readonly int $expiresAt,
        public readonly int $startedAt,
        public readonly int $finishedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Data::string($data, 'id'),
            month: Month::fromName(Data::string($data, 'month')),
            coverSubtitle: Data::string($data, 'cover_subtitle'),
            status: self::parseJobStatus(Data::string($data, 'status')),
            createdAt: Data::int($data, 'created_at'),
            updatedAt: Data::int($data, 'updated_at'),
            totalFiles: Data::int($data, 'total_files'),
            uploadedFiles: Data::int($data, 'uploaded_files'),
            processedFiles: Data::int($data, 'processed_files'),
            pdfPath: Data::string($data, 'pdf_path'),
            pdfSize: Data::int($data, 'pdf_size'),
            pdfPages: Data::int($data, 'pdf_pages'),
            pdfSha256: Data::string($data, 'pdf_sha256'),
            error: Data::string($data, 'error'),
            telegramStatus: self::parseDeliveryStatus(Data::string($data, 'telegram_status')),
            telegramError: Data::string($data, 'telegram_error'),
            mailStatus: self::parseDeliveryStatus(Data::string($data, 'mail_status')),
            mailError: Data::string($data, 'mail_error'),
            expiresAt: Data::int($data, 'expires_at'),
            startedAt: Data::int($data, 'started_at'),
            finishedAt: Data::int($data, 'finished_at'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'month' => $this->month->value,
            'cover_subtitle' => $this->coverSubtitle,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'total_files' => $this->totalFiles,
            'uploaded_files' => $this->uploadedFiles,
            'processed_files' => $this->processedFiles,
            'pdf_path' => $this->pdfPath,
            'pdf_size' => $this->pdfSize,
            'pdf_pages' => $this->pdfPages,
            'pdf_sha256' => $this->pdfSha256,
            'error' => $this->error,
            'telegram_status' => $this->telegramStatus->value,
            'telegram_error' => $this->telegramError,
            'mail_status' => $this->mailStatus->value,
            'mail_error' => $this->mailError,
            'expires_at' => $this->expiresAt,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
        ];
    }

    public function title(): string
    {
        return $this->month->title();
    }

    public function fileName(): string
    {
        return $this->month->fileName();
    }

    public function hasPdf(): bool
    {
        return $this->pdfPath !== '';
    }

    public function hasError(): bool
    {
        return $this->error !== '';
    }

    public function hasExpiry(): bool
    {
        return $this->expiresAt > 0;
    }

    public function hasStarted(): bool
    {
        return $this->startedAt > 0;
    }

    public function hasFinished(): bool
    {
        return $this->finishedAt > 0;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function isExpired(int $now): bool
    {
        return $this->hasExpiry() && $now >= $this->expiresAt;
    }

    public function isStale(int $now, int $ttl): bool
    {
        return !$this->isFinished() && ($this->createdAt + $ttl) < $now;
    }

    public function progress(): int
    {
        if ($this->hasPdf() || $this->status === JobStatus::Delivering || $this->status === JobStatus::Completed) {
            return 100;
        }

        if ($this->totalFiles <= 0) {
            return 0;
        }

        $progress = (int) floor(($this->processedFiles / $this->totalFiles) * 100);

        if ($progress < 0) {
            return 0;
        }

        if ($progress > 100) {
            return 100;
        }

        return $progress;
    }

    public function withStatus(JobStatus $status, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withTotalFiles(int $totalFiles, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withUploadedFiles(int $uploadedFiles, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withProcessedFiles(int $processedFiles, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withPdf(PdfResult $result, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $result->path,
            pdfSize: $result->size,
            pdfPages: $result->pages,
            pdfSha256: $result->sha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withError(string $error, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withTelegram(DeliveryStatus $status, string $error, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $status,
            telegramError: $error,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withMail(DeliveryStatus $status, string $error, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $status,
            mailError: $error,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withExpiresAt(int $expiresAt, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withStartedAt(int $startedAt, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $startedAt,
            finishedAt: $this->finishedAt,
        );
    }

    public function withFinishedAt(int $finishedAt, int $now): self
    {
        return new self(
            id: $this->id,
            month: $this->month,
            coverSubtitle: $this->coverSubtitle,
            status: $this->status,
            createdAt: $this->createdAt,
            updatedAt: $now,
            totalFiles: $this->totalFiles,
            uploadedFiles: $this->uploadedFiles,
            processedFiles: $this->processedFiles,
            pdfPath: $this->pdfPath,
            pdfSize: $this->pdfSize,
            pdfPages: $this->pdfPages,
            pdfSha256: $this->pdfSha256,
            error: $this->error,
            telegramStatus: $this->telegramStatus,
            telegramError: $this->telegramError,
            mailStatus: $this->mailStatus,
            mailError: $this->mailError,
            expiresAt: $this->expiresAt,
            startedAt: $this->startedAt,
            finishedAt: $finishedAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toView(Clock $clock): array
    {
        $now = $clock->timestamp($clock->now());
        $expiresIn = $this->expiresAt > $now ? $this->expiresAt - $now : 0;
        $canDownload = $this->hasPdf() && !$this->isExpired($now);
        $canRetry = $this->hasPdf()
            && ($this->telegramStatus === DeliveryStatus::Failed || $this->mailStatus === DeliveryStatus::Failed);

        return [
            'id' => $this->id,
            'title' => $this->title(),
            'file_name' => $this->fileName(),
            'month' => $this->month->value,
            'month_label' => $this->month->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->createdAt,
            'created_at_iso' => $clock->iso($clock->fromTimestamp($this->createdAt)),
            'updated_at' => $this->updatedAt,
            'updated_at_iso' => $clock->iso($clock->fromTimestamp($this->updatedAt)),
            'total_files' => $this->totalFiles,
            'uploaded_files' => $this->uploadedFiles,
            'processed_files' => $this->processedFiles,
            'progress' => $this->progress(),
            'pdf_ready' => $this->hasPdf(),
            'pdf_size' => $this->pdfSize,
            'pdf_size_label' => $this->humanSize($this->pdfSize),
            'pdf_pages' => $this->pdfPages,
            'download_url' => $canDownload ? '/api/jobs/' . $this->id . '/download' : '',
            'expires_at' => $this->expiresAt,
            'expires_at_iso' => $this->hasExpiry()
                ? $clock->iso($clock->fromTimestamp($this->expiresAt))
                : '',
            'expires_at_label' => $this->hasExpiry()
                ? $this->timeLabel($clock, $this->expiresAt)
                : '',
            'expires_in' => $expiresIn,
            'expires_in_label' => $this->expiryLabel($expiresIn),
            'created_at_label' => $this->timeLabel($clock, $this->createdAt),
            'has_error' => $this->hasError(),
            'error' => $this->error,
            'telegram' => [
                'status' => $this->telegramStatus->value,
                'label' => $this->telegramStatus->label(),
                'error' => $this->telegramError,
            ],
            'mail' => [
                'status' => $this->mailStatus->value,
                'label' => $this->mailStatus->label(),
                'error' => $this->mailError,
            ],
            'can_retry' => $canRetry,
            'is_active' => $this->isActive(),
            'is_finished' => $this->isFinished(),
        ];
    }

    private static function parseJobStatus(string $value): JobStatus
    {
        try {
            return JobStatus::from($value);
        } catch (ValueError $exception) {
            throw new StorageException('Estado de trabajo inválido.');
        }
    }

    private static function parseDeliveryStatus(string $value): DeliveryStatus
    {
        try {
            return DeliveryStatus::from($value);
        } catch (ValueError $exception) {
            throw new StorageException('Estado de entrega inválido.');
        }
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Fecha y hora legible en español, en la zona horaria de la aplicación.
     */
    private function timeLabel(Clock $clock, int $timestamp): string
    {
        $date = $clock->fromTimestamp($timestamp);

        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                'es',
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                $clock->zone(),
                IntlDateFormatter::GREGORIAN,
                "d MMM yyyy · HH:mm",
            );

            $label = $formatter->format($date->getTimestamp());

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return str_replace('T', ' ', mb_substr($clock->iso($date), 0, 16));
    }

    private function expiryLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return 'quedan ' . $seconds . ' s';
        }

        if ($seconds < 3600) {
            return 'quedan ' . intdiv($seconds, 60) . ' min';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return 'quedan ' . $hours . ' h' . ($minutes > 0 ? ' ' . $minutes . ' min' : '');
    }
}
