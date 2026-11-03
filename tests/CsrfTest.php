<?php

declare(strict_types=1);

namespace Willy\Tests;

use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Willy\Exception\CsrfException;
use Willy\Support\Csrf;
use Willy\Support\Session;

final class CsrfTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        TestEnvironment::resetSession();
        TestEnvironment::files()->ensurePaths(TestEnvironment::paths());

        $session = Session::start(
            TestEnvironment::config(),
            TestEnvironment::paths(),
            TestEnvironment::clock(),
        );

        $this->csrf = new Csrf($session, new Randomizer());
    }

    public function testItGeneratesAStableHexToken(): void
    {
        $token = $this->csrf->token();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertSame($token, $this->csrf->token());
    }

    public function testRotateReplacesTheToken(): void
    {
        $first = $this->csrf->token();
        $second = $this->csrf->rotate();

        self::assertNotSame($first, $second);
        self::assertSame($second, $this->csrf->token());
    }

    public function testVerifyAcceptsTheCurrentToken(): void
    {
        $this->csrf->verify($this->csrf->token());

        self::expectNotToPerformAssertions();
    }

    public function testVerifyRejectsAForeignToken(): void
    {
        $this->csrf->token();

        $this->expectException(CsrfException::class);

        $this->csrf->verify(str_repeat('a', 64));
    }

    public function testVerifyRejectsAnEmptyToken(): void
    {
        $this->expectException(CsrfException::class);

        $this->csrf->verify('');
    }
}
