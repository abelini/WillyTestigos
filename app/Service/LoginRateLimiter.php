<?php

declare(strict_types=1);

namespace Willy\Service;

use Willy\Exception\RateLimitException;
use Willy\Support\Clock;
use Willy\Support\Json;
use Willy\Support\Paths;

use function array_key_exists;
use function hash;
use function is_array;
use function is_int;
use function is_string;
use function max;
use function preg_match;
use function sprintf;

final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;
    private const BLOCK_SECONDS = 900;
    private const RETENTION_SECONDS = 86400;

    public function __construct(
        private readonly Paths $paths,
        private readonly Json $json,
        private readonly Clock $clock,
    ) {
    }

    public function assertAllowed(string $ip): void
    {
        $now = $this->now();
        $entry = $this->entry($this->readIps(), $this->key($ip));
        $retry = $entry['blocked_until'] - $now;

        if ($retry > 0) {
            throw new RateLimitException(sprintf(
                'Demasiados intentos fallidos. Vuelve a intentarlo en %d segundos.',
                $retry,
            ));
        }
    }

    public function registerFailure(string $ip): int
    {
        $now = $this->now();
        $key = $this->key($ip);

        $data = $this->json->update(
            $this->paths->loginAttempts(),
            function (array $data) use ($key, $now): array {
                $ips = $this->parseIps($data);
                $entry = $this->inWindow($this->entry($ips, $key), $now);
                $entry['failures']++;
                $entry['last'] = $now;

                if ($entry['failures'] >= self::MAX_ATTEMPTS) {
                    $entry['blocked_until'] = $now + self::BLOCK_SECONDS;
                }

                $ips[$key] = $entry;
                $data['ips'] = $this->prune($ips, $now);

                return $data;
            },
            ['ips' => []],
            false,
        );

        $entry = $this->inWindow($this->entry($this->parseIps($data), $key), $now);

        return $this->remainingFrom($entry);
    }

    public function clear(string $ip): void
    {
        $key = $this->key($ip);

        $this->json->update(
            $this->paths->loginAttempts(),
            function (array $data) use ($key): array {
                $ips = $this->parseIps($data);

                if (array_key_exists($key, $ips)) {
                    unset($ips[$key]);
                }

                $data['ips'] = $ips;

                return $data;
            },
            ['ips' => []],
            false,
        );
    }

    public function remainingAttempts(string $ip): int
    {
        $entry = $this->inWindow(
            $this->entry($this->readIps(), $this->key($ip)),
            $this->now(),
        );

        return $this->remainingFrom($entry);
    }

    public function retryAfter(string $ip): int
    {
        $entry = $this->entry($this->readIps(), $this->key($ip));

        return max(0, $entry['blocked_until'] - $this->now());
    }

    private function key(string $ip): string
    {
        return hash('sha256', $ip);
    }

    /**
     * @return array<string, array{failures: int, first: int, last: int, blocked_until: int}>
     */
    private function readIps(): array
    {
        $data = $this->json->readOrDefault($this->paths->loginAttempts());

        return $this->parseIps($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, array{failures: int, first: int, last: int, blocked_until: int}>
     */
    private function parseIps(array $data): array
    {
        $ips = [];

        if (!array_key_exists('ips', $data) || !is_array($data['ips'])) {
            return $ips;
        }

        foreach ($data['ips'] as $key => $value) {
            if (!is_string($key) || !is_array($value)) {
                continue;
            }

            $ips[$key] = [
                'failures' => $this->intField($value, 'failures'),
                'first' => $this->intField($value, 'first'),
                'last' => $this->intField($value, 'last'),
                'blocked_until' => $this->intField($value, 'blocked_until'),
            ];
        }

        return $ips;
    }

    /**
     * @param array<string, array{failures: int, first: int, last: int, blocked_until: int}> $ips
     * @return array{failures: int, first: int, last: int, blocked_until: int}
     */
    private function entry(array $ips, string $key): array
    {
        if (!array_key_exists($key, $ips)) {
            return $this->emptyEntry();
        }

        return $ips[$key];
    }

    /**
     * @return array{failures: int, first: int, last: int, blocked_until: int}
     */
    private function emptyEntry(): array
    {
        return [
            'failures' => 0,
            'first' => 0,
            'last' => 0,
            'blocked_until' => 0,
        ];
    }

    /**
     * @param array{failures: int, first: int, last: int, blocked_until: int} $entry
     * @return array{failures: int, first: int, last: int, blocked_until: int}
     */
    private function inWindow(array $entry, int $now): array
    {
        if ($entry['first'] === 0) {
            $entry['first'] = $now;
            $entry['last'] = $now;

            return $entry;
        }

        if (($entry['first'] + self::WINDOW_SECONDS) > $now) {
            return $entry;
        }

        return [
            'failures' => 0,
            'first' => $now,
            'last' => $now,
            'blocked_until' => 0,
        ];
    }

    /**
     * @param array{failures: int, first: int, last: int, blocked_until: int} $entry
     */
    private function remainingFrom(array $entry): int
    {
        return max(0, self::MAX_ATTEMPTS - $entry['failures']);
    }

    /**
     * @param array<string, array{failures: int, first: int, last: int, blocked_until: int}> $ips
     * @return array<string, array{failures: int, first: int, last: int, blocked_until: int}>
     */
    private function prune(array $ips, int $now): array
    {
        $pruned = [];

        foreach ($ips as $key => $entry) {
            if (($entry['last'] + self::RETENTION_SECONDS) <= $now) {
                continue;
            }

            $pruned[$key] = $entry;
        }

        return $pruned;
    }

    /**
     * @param array<array-key, mixed> $item
     */
    private function intField(array $item, string $key): int
    {
        if (!array_key_exists($key, $item)) {
            return 0;
        }

        $value = $item[$key];

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return 0;
    }

    private function now(): int
    {
        return $this->clock->nowTimestamp();
    }
}
