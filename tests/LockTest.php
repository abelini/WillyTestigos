<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Willy\Support\Lock;

final class LockTest extends TestCase
{
    public function testItRejectsASecondLockAndCanBeReleased(): void
    {
        $path = TestEnvironment::root() . '/lock-test.lock';
        $first = Lock::acquire($path, false);

        self::assertTrue($first->isAcquired());

        $second = Lock::acquire($path, false);
        self::assertFalse($second->isAcquired());
        $second->release();

        $first->release();

        $third = Lock::acquire($path, false);
        self::assertTrue($third->isAcquired());
        $third->release();
    }
}
