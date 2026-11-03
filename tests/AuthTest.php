<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Willy\Exception\AuthException;
use Willy\Exception\RateLimitException;
use Willy\Service\Auth;
use Willy\Service\LoginRateLimiter;
use Willy\Support\PasswordHasher;
use Willy\Support\Session;

final class AuthTest extends TestCase
{
    private Auth $auth;

    private LoginRateLimiter $limiter;

    protected function setUp(): void
    {
        TestEnvironment::resetSession();

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
        $session = Session::start(TestEnvironment::config(), $paths, TestEnvironment::clock());
        $this->auth = new Auth($session, new PasswordHasher(), $this->limiter, TestEnvironment::config());
    }

    public function testItStartsWithoutAuthentication(): void
    {
        self::assertFalse($this->auth->check());
    }

    public function testTheCorrectPasswordAuthenticates(): void
    {
        $this->auth->attempt(TestEnvironment::password(), '203.0.113.5');

        self::assertTrue($this->auth->check());
        self::assertSame(5, $this->limiter->remainingAttempts('203.0.113.5'));
    }

    public function testAWrongPasswordIsRejectedAndCounted(): void
    {
        try {
            $this->auth->attempt('clave-incorrecta', '203.0.113.5');
            self::fail('Se esperaba un error de autenticación.');
        } catch (AuthException $exception) {
            self::assertNotSame('', $exception->getMessage());
        }

        self::assertFalse($this->auth->check());
        self::assertSame(4, $this->limiter->remainingAttempts('203.0.113.5'));
    }

    public function testTheAddressIsBlockedAfterFiveFailures(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $this->auth->attempt('clave-incorrecta', '203.0.113.5');
            } catch (AuthException $exception) {
                continue;
            }
        }

        $this->expectException(RateLimitException::class);

        $this->auth->attempt(TestEnvironment::password(), '203.0.113.5');
    }

    public function testACorrectPasswordKeepsAllAttemptsAvailable(): void
    {
        $this->auth->attempt(TestEnvironment::password(), '203.0.113.5');
        $this->auth->attempt(TestEnvironment::password(), '203.0.113.5');

        self::assertSame(5, $this->limiter->remainingAttempts('203.0.113.5'));
    }

    public function testAuthenticationIsRequiredElsewhere(): void
    {
        $this->expectException(AuthException::class);

        $this->auth->requireAuthentication();
    }

    public function testLogoutDestroysTheSession(): void
    {
        $this->auth->attempt(TestEnvironment::password(), '203.0.113.5');
        self::assertTrue($this->auth->check());

        $this->auth->logout();

        $fresh = Session::start(TestEnvironment::config(), TestEnvironment::paths(), TestEnvironment::clock());
        $auth = new Auth($fresh, new PasswordHasher(), $this->limiter, TestEnvironment::config());

        self::assertFalse($auth->check());
    }
}
