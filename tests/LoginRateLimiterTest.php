<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Willy\Exception\RateLimitException;
use Willy\Service\LoginRateLimiter;

final class LoginRateLimiterTest extends TestCase
{
    private LoginRateLimiter $limiter;

    protected function setUp(): void
    {
        $files = TestEnvironment::files();
        $paths = TestEnvironment::paths();
        $files->ensurePaths($paths);

        if ($files->isFile($paths->loginAttempts())) {
            $files->delete($paths->loginAttempts());
        }

        $zone = TestEnvironment::clock()->zone();
        $clock = new FixedClock(
            (new DateTimeImmutable('@1700000000'))->setTimezone($zone),
            $zone,
        );

        $this->limiter = new LoginRateLimiter($paths, TestEnvironment::json(), $clock);
    }

    public function testItBlocksAfterFiveFailures(): void
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->limiter->registerFailure('203.0.113.5');
            $this->limiter->assertAllowed('203.0.113.5');
        }

        $this->limiter->registerFailure('203.0.113.5');

        $this->expectException(RateLimitException::class);
        $this->limiter->assertAllowed('203.0.113.5');
    }

    public function testClearResetsTheCounter(): void
    {
        $this->limiter->registerFailure('203.0.113.5');
        $this->limiter->registerFailure('203.0.113.5');
        $this->limiter->clear('203.0.113.5');

        self::assertSame(5, $this->limiter->remainingAttempts('203.0.113.5'));
    }

    public function testRetryAfterIsReportedWhenBlocked(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->limiter->registerFailure('203.0.113.5');
        }

        self::assertSame(900, $this->limiter->retryAfter('203.0.113.5'));
        self::assertSame(0, $this->limiter->remainingAttempts('203.0.113.5'));
    }

    public function testDifferentAddressesAreIndependent(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->limiter->registerFailure('203.0.113.5');
        }

        $this->limiter->assertAllowed('203.0.113.6');
        self::assertSame(5, $this->limiter->remainingAttempts('203.0.113.6'));
    }
}
