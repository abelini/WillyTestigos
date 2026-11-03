<?php

declare(strict_types=1);

namespace Willy\Repository;

use Random\Randomizer;
use SplFileInfo;
use Throwable;
use Willy\Domain\DeliveryStatus;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Domain\Month;
use Willy\Exception\NotFoundException;
use Willy\Support\Clock;
use Willy\Support\FileSystem;
use Willy\Support\Json;
use Willy\Support\Paths;

use function preg_match;
use function usort;

final class JobStore
{
    public function __construct(
        private readonly Paths $paths,
        private readonly FileSystem $files,
        private readonly Json $json,
        private readonly Clock $clock,
        private readonly Randomizer $random,
    ) {
    }

    public function create(Month $month, string $coverSubtitle): Job
    {
        $now = $this->clock->nowTimestamp();
        $job = new Job(
            id: $this->newId(),
            month: $month,
            coverSubtitle: $coverSubtitle,
            status: JobStatus::Created,
            createdAt: $now,
            updatedAt: $now,
            totalFiles: 0,
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

        $this->files->ensureDirectory($this->paths->jobDir($job->id));
        $this->json->write($this->paths->jobFile($job->id), $job->toArray());

        return $job;
    }

    public function save(Job $job): Job
    {
        $this->json->write($this->paths->jobFile($job->id), $job->toArray());

        return $job;
    }

    public function find(string $id): Job
    {
        $this->assertId($id);
        $path = $this->paths->jobFile($id);

        if (!$this->files->isFile($path)) {
            throw new NotFoundException('El trabajo solicitado no existe.');
        }

        return Job::fromArray($this->json->read($path));
    }

    public function exists(string $id): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            return false;
        }

        return $this->files->isFile($this->paths->jobFile($id));
    }

    /**
     * @param callable(Job): Job $mutator
     */
    public function update(string $id, callable $mutator): Job
    {
        $this->assertId($id);

        $updated = $this->json->update(
            $this->paths->jobFile($id),
            static function (array $data) use ($mutator): array {
                $job = Job::fromArray($data);

                return $mutator($job)->toArray();
            },
            [],
            true,
        );

        return Job::fromArray($updated);
    }

    public function delete(string $id): void
    {
        $this->assertId($id);

        if (!$this->files->isDirectory($this->paths->jobDir($id))) {
            return;
        }

        $this->files->delete($this->paths->jobDir($id));
    }

    /**
     * @return list<Job>
     */
    public function all(): array
    {
        $jobs = [];

        foreach ($this->files->listDirectories($this->paths->jobs()) as $directory) {
            $id = new SplFileInfo($directory);

            if (preg_match('/^[a-f0-9]{32}$/', $id->getBasename()) !== 1) {
                continue;
            }

            try {
                $jobs[] = $this->find($id->getBasename());
            } catch (Throwable $exception) {
                continue;
            }
        }

        usort(
            $jobs,
            static fn (Job $left, Job $right): int => $right->createdAt <=> $left->createdAt,
        );

        return $jobs;
    }

    /**
     * @return list<Job>
     */
    public function active(): array
    {
        $active = [];

        foreach ($this->all() as $job) {
            if ($job->isActive()) {
                $active[] = $job;
            }
        }

        return $active;
    }

    public function latest(): Job
    {
        $jobs = $this->all();

        if ($jobs === []) {
            throw new NotFoundException('Todavía no existe ningún trabajo.');
        }

        return $jobs[0];
    }

    private function newId(): string
    {
        return $this->random->getBytesFromString('0123456789abcdef', 32);
    }

    private function assertId(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            throw new NotFoundException('El identificador del trabajo no es válido.');
        }
    }
}
