<?php

declare(strict_types=1);

namespace Willy\Support;

use Random\Randomizer;
use Willy\Exception\CsrfException;

use function hash_equals;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(
        private readonly Session $session,
        private readonly Randomizer $random,
    ) {
    }

    public function token(): string
    {
        if ($this->session->has(self::SESSION_KEY)) {
            $existing = $this->session->getString(self::SESSION_KEY);

            if ($existing !== '') {
                return $existing;
            }
        }

        return $this->rotate();
    }

    public function rotate(): string
    {
        $token = $this->random->getBytesFromString('0123456789abcdef', 64);
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function verify(string $token): void
    {
        $expected = $this->token();

        if ($token === '' || !hash_equals($expected, $token)) {
            throw new CsrfException('Token CSRF inválido. Recarga la página.');
        }
    }
}
