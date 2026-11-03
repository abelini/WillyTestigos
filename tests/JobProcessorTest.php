<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Container;
use Willy\Domain\DeliveryStatus;
use Willy\Domain\ImageType;
use Willy\Domain\JobStatus;
use Willy\Domain\Month;
use Willy\Exception\ProcessingException;

final class JobProcessorTest extends TestCase
{
    public function testItBuildsThePdfAndCleansThePhotos(): void
    {
        TestEnvironment::resetJobs();
        $container = Container::create(TestEnvironment::root());
        $job = $container->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $container->jobFiles->prepare($job->id);

        $sizes = [
            [1600, 900],
            [720, 1600],
            [1200, 1200],
            [1000, 800],
            [900, 1600],
            [1600, 1000],
            [1000, 1000],
            [1400, 700],
            [800, 1400],
        ];

        foreach ($sizes as $index => $size) {
            ImageFixture::jpeg(
                $container->jobFiles->originalPath($job->id, $index + 1, ImageType::Jpeg),
                $size[0],
                $size[1],
            );
        }

        $result = $container->processor->process($job->id);

        self::assertSame(JobStatus::Completed, $result->status);
        self::assertTrue($result->hasPdf());
        self::assertSame(3, $result->pdfPages);
        self::assertGreaterThan(0, $result->pdfSize);
        self::assertFileExists($result->pdfPath);
        self::assertSame([], $container->jobFiles->originals($job->id));
        self::assertSame([], $container->jobFiles->processed($job->id));
        self::assertSame(DeliveryStatus::Skipped, $result->telegramStatus);
        self::assertSame(DeliveryStatus::Skipped, $result->mailStatus);
        self::assertGreaterThan(0, $result->expiresAt);
    }

    public function testItMarksTheJobAsFailedWithoutPhotos(): void
    {
        TestEnvironment::resetJobs();
        $container = Container::create(TestEnvironment::root());
        $job = $container->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');

        try {
            $container->processor->process($job->id);
            self::fail('Se esperaba un error de procesamiento.');
        } catch (ProcessingException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        $failed = $container->jobs->find($job->id);

        self::assertSame(JobStatus::Failed, $failed->status);
        self::assertNotSame('', $failed->error);
    }
}
