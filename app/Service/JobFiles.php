<?php

declare(strict_types=1);

namespace Willy\Service;

use Willy\Domain\ImageType;
use Willy\Domain\Month;
use Willy\Support\Config;
use Willy\Support\FileSystem;
use Willy\Support\Paths;

use function sprintf;

final class JobFiles
{
    public function __construct(
        private readonly Paths $paths,
        private readonly FileSystem $files,
        private readonly Config $config,
    ) {
    }

    public function prepare(string $jobId): void
    {
        $this->files->ensureDirectory($this->paths->originalsDir($jobId));
        $this->files->ensureDirectory($this->paths->processedDir($jobId));
        $this->files->ensureDirectory($this->paths->outputDir($jobId));
    }

    public function storeOriginal(string $jobId, string $temporary, int $sequence, ImageType $type): string
    {
        $this->prepare($jobId);
        $path = $this->originalPath($jobId, $sequence, $type);
        $this->files->move($temporary, $path);

        return $path;
    }

    public function originalPath(string $jobId, int $sequence, ImageType $type): string
    {
        return $this->paths->originalsDir($jobId) . '/' . $this->fileName($sequence, $type);
    }

    public function processedPath(string $jobId, int $sequence): string
    {
        return $this->paths->processedDir($jobId) . '/' . sprintf('%04d.jpg', $sequence);
    }

    /**
     * @return list<string>
     */
    public function originals(string $jobId): array
    {
        $directory = $this->paths->originalsDir($jobId);

        if (!$this->files->isDirectory($directory)) {
            return [];
        }

        return $this->files->listFiles($directory . '/*');
    }

    /**
     * @return list<string>
     */
    public function processed(string $jobId): array
    {
        $directory = $this->paths->processedDir($jobId);

        if (!$this->files->isDirectory($directory)) {
            return [];
        }

        return $this->files->listFiles($directory . '/*');
    }

    public function pdfPath(string $jobId, Month $month): string
    {
        return $this->paths->outputDir($jobId) . '/' . $month->fileName();
    }

    public function hasPdf(string $jobId, Month $month): bool
    {
        return $this->files->isFile($this->pdfPath($jobId, $month));
    }

    public function hasOriginal(string $jobId, int $sequence): bool
    {
        if ($sequence < 1) {
            return false;
        }

        $directory = $this->paths->originalsDir($jobId);

        if (!$this->files->isDirectory($directory)) {
            return false;
        }

        return $this->files->listFiles($directory . '/' . sprintf('%04d.*', $sequence)) !== [];
    }

    public function deletePdf(string $jobId, Month $month): void
    {
        $path = $this->pdfPath($jobId, $month);

        if ($this->files->isFile($path)) {
            $this->files->delete($path);
        }
    }

    public function clearOriginals(string $jobId): void
    {
        $directory = $this->paths->originalsDir($jobId);

        if ($this->files->isDirectory($directory)) {
            $this->files->deleteContents($directory);
        }
    }

    public function clearProcessed(string $jobId): void
    {
        $directory = $this->paths->processedDir($jobId);

        if ($this->files->isDirectory($directory)) {
            $this->files->deleteContents($directory);
        }
    }

    public function clearWorking(string $jobId): void
    {
        $this->clearOriginals($jobId);
        $this->clearProcessed($jobId);
    }

    public function maxBytes(): int
    {
        return $this->config->maxTotalBytes();
    }

    private function fileName(int $sequence, ImageType $type): string
    {
        return sprintf('%04d.%s', $sequence, $type->extension());
    }
}
