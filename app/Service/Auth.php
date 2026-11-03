<?php

declare(strict_types=1);

namespace Willy\Service;

use Willy\Exception\AuthException;
use Willy\Support\Config;
use Willy\Support\PasswordHasher;
use Willy\Support\Session;

final class Auth
{
    private const SESSION_KEY = 'authenticated';

    public function __construct(
        private readonly Session $session,
        private readonly PasswordHasher $hasher,
        private readonly LoginRateLimiter $limiter,
        private readonly Config $config,
    ) {
    }

    public function check(): bool
    {
        return $this->session->has(self::SESSION_KEY) && $this->session->getBool(self::SESSION_KEY);
    }

    public function attempt(string $password, string $ip): void
    {
        $this->limiter->assertAllowed($ip);

        if (!$this->hasher->verify($password, $this->config->passwordHash)) {
            $this->limiter->registerFailure($ip);

            throw new AuthException('La contraseña no es correcta.');
        }

        $this->limiter->clear($ip);
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, true);
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function requireAuthentication(): void
    {
        if (!$this->check()) {
            throw new AuthException('Debes iniciar sesión para continuar.');
        }
    }
}
