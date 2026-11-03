<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Domain\Month;
use Willy\Exception\ConflictException;
use Willy\Exception\StateException;
use Willy\Exception\ValidationException;
use Willy\Http\UploadedFile;
use Willy\Repository\JobStore;
use Willy\Service\ImageProcessor;
use Willy\Service\JobFiles;
use Willy\Service\UploadService;
use Willy\Support\SystemUploadGuard;

final class UploadServiceTest extends TestCase
{
    private JobStore $jobs;

    private JobFiles $jobFiles;

    private UploadService $uploads;

    private string $uploadDir;

    protected function setUp(): void
    {
        TestEnvironment::resetJobs();

        $paths = TestEnvironment::paths();
        $files = TestEnvironment::files();
        $files->ensurePaths($paths);

        $this->uploadDir = TestEnvironment::root() . '/uploads';
        $files->ensureDirectory($this->uploadDir);
        $files->deleteContents($this->uploadDir);

        $zone = TestEnvironment::clock()->zone();
        $clock = new FixedClock(
            (new DateTimeImmutable('@1700000000'))->setTimezone($zone),
            $zone,
        );
        $config = TestEnvironment::config();

        $this->jobs = new JobStore($paths, $files, TestEnvironment::json(), $clock, new Randomizer());
        $this->jobFiles = new JobFiles($paths, $files, $config);
        $this->uploads = new UploadService(
            $config,
            $this->jobs,
            $this->jobFiles,
            new ImageProcessor($files, $config),
            $files,
            $clock,
            new FixedUploadGuard(true),
        );
    }

    public function testItStoresTheCaptureAndUpdatesTheJob(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $updated = $this->uploads->accept($job->id, $this->capture('valida.jpg', 400, 300), 1);

        self::assertSame(JobStatus::Uploading, $updated->status);
        self::assertSame(1, $updated->uploadedFiles);
        self::assertSame(1, $updated->totalFiles);
        self::assertTrue($this->jobFiles->hasOriginal($job->id, 1));
    }

    public function testItRejectsAnInvalidPosition(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $this->expectValidationException();

        $this->uploads->accept($job->id, $this->capture('posicion.jpg', 400, 300), 0);
    }

    public function testItRejectsADuplicatePosition(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $this->uploads->accept($job->id, $this->capture('primera.jpg', 400, 300), 1);

        $this->expectException(ConflictException::class);

        $this->uploads->accept($job->id, $this->capture('repetida.jpg', 400, 300), 1);
    }

    public function testItRejectsAFileThatIsNotAnImage(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $path = $this->uploadDir . '/no-imagen.jpg';
        TestEnvironment::files()->writeAtomic($path, 'esto no es una imagen');

        $this->expectValidationException();

        $this->uploads->accept($job->id, UploadedFile::fromGlobals([
            'photo' => [
                'name' => 'no-imagen.jpg',
                'tmp_name' => $path,
                'type' => 'image/jpeg',
                'error' => UPLOAD_ERR_OK,
                'size' => 18,
            ],
        ], 'photo'), 1);
    }

    public function testItRejectsAFileOutsideTheUploadRequest(): void
    {
        $config = TestEnvironment::config();
        $paths = TestEnvironment::paths();
        $files = TestEnvironment::files();
        $real = new UploadService(
            $config,
            $this->jobs,
            new JobFiles($paths, $files, $config),
            new ImageProcessor($files, $config),
            $files,
            TestEnvironment::clock(),
            new SystemUploadGuard(),
        );
        $path = $this->uploadDir . '/fuera.jpg';
        ImageFixture::jpeg($path, 400, 300);
        self::assertFileExists($path);
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');

        $this->expectException(ValidationException::class);

        $real->accept($job->id, UploadedFile::fromGlobals([
            'photo' => [
                'name' => 'fuera.jpg',
                'tmp_name' => $path,
                'type' => 'image/jpeg',
                'error' => UPLOAD_ERR_OK,
                'size' => (int) filesize($path),
            ],
        ], 'photo'), 1);
    }

    public function testItRejectsUploadsInAFinishedJob(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $this->jobs->update(
            $job->id,
            static fn (Job $current): Job => $current->withStatus(JobStatus::Completed, 1700000000),
        );

        $this->expectException(StateException::class);

        $this->uploads->accept($job->id, $this->capture('terminado.jpg', 400, 300), 1);
    }

    public function testFinishRequiresAtLeastOneCapture(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');

        $this->expectValidationException();

        $this->uploads->finish($job->id);
    }

    public function testFinishMarksTheJobAsQueued(): void
    {
        $job = $this->jobs->create(Month::Noviembre, 'Portal: Proyecto Z');
        $this->uploads->accept($job->id, $this->capture('final.jpg', 400, 300), 1);

        $finished = $this->uploads->finish($job->id);

        self::assertSame(JobStatus::Queued, $finished->status);
        self::assertSame(1, $finished->uploadedFiles);
        self::assertSame(1, $finished->totalFiles);
    }

    private function capture(string $name, int $width, int $height): UploadedFile
    {
        $path = $this->uploadDir . '/' . $name . '-' . bin2hex(random_bytes(6)) . '.jpg';
        ImageFixture::jpeg($path, $width, $height);
        self::assertFileExists($path);

        return UploadedFile::fromGlobals([
            'photo' => [
                'name' => $name,
                'tmp_name' => $path,
                'type' => 'image/jpeg',
                'error' => UPLOAD_ERR_OK,
                'size' => TestEnvironment::files()->size($path),
            ],
        ], 'photo');
    }

    private function expectValidationException(): void
    {
        $this->expectException(ValidationException::class);
    }
}
