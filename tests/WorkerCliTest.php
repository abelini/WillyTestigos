<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;

final class WorkerCliTest extends TestCase
{
    public function testItRejectsAMissingJobIdentifier(): void
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__) . '/bin/worker.php')
            . ' 2>&1';

        $output = [];
        $code = 0;
        exec($command, $output, $code);

        self::assertSame(1, $code);
        self::assertStringContainsString('Uso: php bin/worker.php <job-id>', implode("\n", $output));
    }
}
