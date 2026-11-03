<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Willy\Domain\DeliveryStatus;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Domain\Month;
use Willy\Domain\PdfResult;

final class JobTest extends TestCase
{
    public function testRoundTripKeepsAllFields(): void
    {
        $job = $this->job();

        self::assertSame($job->toArray(), Job::fromArray($job->toArray())->toArray());
    }

    public function testInitialJobHasNoPdfAndNoExpiry(): void
    {
        $job = $this->job();

        self::assertFalse($job->hasPdf());
        self::assertFalse($job->hasError());
        self::assertFalse($job->hasExpiry());
        self::assertSame(0, $job->progress());
    }

    public function testPdfMakesTheJobDownloadable(): void
    {
        $clock = $this->clock();
        $job = $this->job()
            ->withPdf(new PdfResult('/storage/job.pdf', 13, 1024, str_repeat('a', 64)), 1_700_000_000)
            ->withExpiresAt(1_700_003_600, 1_700_000_000);

        $view = $job->toView($clock);

        self::assertTrue($job->hasPdf());
        self::assertSame(100, $job->progress());
        self::assertSame('/api/jobs/' . $job->id . '/download', $view['download_url']);
        self::assertSame(1_700_003_600, $view['expires_at']);
    }

    public function testExpiredJobIsNotDownloadable(): void
    {
        $clock = $this->clockAt(1_700_003_600);
        $job = $this->job()
            ->withPdf(new PdfResult('/storage/job.pdf', 13, 1024, str_repeat('a', 64)), 1_700_000_000)
            ->withExpiresAt(1_700_003_600, 1_700_000_000);

        $view = $job->toView($clock);

        self::assertTrue($job->isExpired(1_700_003_600));
        self::assertSame('', $view['download_url']);
    }

    public function testDeliveryErrorsEnableRetry(): void
    {
        $job = $this->job()
            ->withPdf(new PdfResult('/storage/job.pdf', 13, 1024, str_repeat('a', 64)), 1_700_000_000)
            ->withTelegram(DeliveryStatus::Failed, 'Telegram falló', 1_700_000_000)
            ->withMail(DeliveryStatus::Sent, '', 1_700_000_000);

        $view = $job->toView($this->clock());
        $telegram = $view['telegram'];
        $mail = $view['mail'];

        self::assertIsArray($telegram);
        self::assertIsArray($mail);
        self::assertTrue($view['can_retry']);
        self::assertSame('failed', $telegram['status']);
        self::assertSame('sent', $mail['status']);
    }

    private function job(): Job
    {
        return new Job(
            id: str_repeat('b', 32),
            month: Month::Noviembre,
            coverSubtitle: 'Portal: Proyecto Z',
            status: JobStatus::Created,
            createdAt: 1_700_000_000,
            updatedAt: 1_700_000_000,
            totalFiles: 90,
            uploadedFiles: 0,
            processedFiles: 0,
            pdfPath: '',
            pdfSize: 0,
            pdfPages: 0,
            pdfSha256: '',
            error: '',
            telegramStatus: DeliveryStatus::Pending,
            telegramError: '',
            mailStatus: DeliveryStatus::Pending,
            mailError: '',
            expiresAt: 0,
            startedAt: 0,
            finishedAt: 0,
        );
    }

    private function clock(): FixedClock
    {
        return $this->clockAt(1_700_000_000);
    }

    private function clockAt(int $timestamp): FixedClock
    {
        $zone = new DateTimeZone('America/Mazatlan');

        return new FixedClock((new DateTimeImmutable('@' . $timestamp))->setTimezone($zone), $zone);
    }
}
