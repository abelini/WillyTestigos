<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Willy\Domain\Job;
use Willy\Domain\JobStatus;
use Willy\Domain\Month;
use Willy\Exception\NotFoundException;
use Willy\Repository\JobStore;

final class JobStoreTest extends TestCase
{
    private JobStore $store;

    protected function setUp(): void
    {
        TestEnvironment::resetJobs();
        $paths = TestEnvironment::paths();

        $this->store = new JobStore(
            $paths,
            TestEnvironment::files(),
            TestEnvironment::json(),
            TestEnvironment::clock(),
            new Randomizer(),
        );
    }

    public function testCreatePersistsTheJob(): void
    {
        $job = $this->store->create(Month::Octubre, 'Portal: Proyecto Z');

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $job->id);
        self::assertSame(JobStatus::Created, $job->status);
        self::assertTrue($this->store->exists($job->id));
        self::assertSame($job->toArray(), $this->store->find($job->id)->toArray());
    }

    public function testUpdateAppliesTheWithers(): void
    {
        $job = $this->store->create(Month::Octubre, 'Portal: Proyecto Z');
        $updated = $this->store->update(
            $job->id,
            static fn (Job $current): Job => $current->withStatus(JobStatus::Uploading, $current->createdAt),
        );

        self::assertSame(JobStatus::Uploading, $updated->status);
        self::assertSame(JobStatus::Uploading, $this->store->find($job->id)->status);
    }

    public function testAllReturnsTheNewestFirst(): void
    {
        $first = $this->storeAt(1_700_000_000)->create(Month::Octubre, 'Portal: Proyecto Z');
        $second = $this->storeAt(1_700_000_060)->create(Month::Noviembre, 'Portal: Proyecto Z');
        $all = $this->store->all();

        self::assertCount(2, $all);
        self::assertSame($second->id, $all[0]->id);
        self::assertSame($first->id, $all[1]->id);
    }

    public function testActiveOnlyIncludesUnfinishedJobs(): void
    {
        $job = $this->store->create(Month::Octubre, 'Portal: Proyecto Z');
        $this->store->update(
            $job->id,
            static fn (Job $current): Job => $current->withStatus(JobStatus::Failed, $current->createdAt),
        );

        self::assertSame([], $this->store->active());
    }

    public function testDeleteRemovesTheJob(): void
    {
        $job = $this->store->create(Month::Octubre, 'Portal: Proyecto Z');
        $this->store->delete($job->id);

        self::assertFalse($this->store->exists($job->id));
        $this->expectException(NotFoundException::class);
        $this->store->find($job->id);
    }

    private function storeAt(int $timestamp): JobStore
    {
        $zone = TestEnvironment::clock()->zone();
        $clock = new FixedClock((new DateTimeImmutable('@' . $timestamp))->setTimezone($zone), $zone);

        return new JobStore(
            TestEnvironment::paths(),
            TestEnvironment::files(),
            TestEnvironment::json(),
            $clock,
            new Randomizer(),
        );
    }
}
