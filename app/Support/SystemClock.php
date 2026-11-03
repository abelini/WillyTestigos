<?php

declare(strict_types=1);

namespace Willy\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;
use Willy\Exception\ConfigurationException;

final class SystemClock implements Clock
{
    public function __construct(private readonly DateTimeZone $zone)
    {
    }

    public static function fromTimezone(string $timezone): self
    {
        try {
            return new self(new DateTimeZone($timezone));
        } catch (Throwable $exception) {
            throw new ConfigurationException("La zona horaria {$timezone} no es válida.");
        }
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->zone);
    }

    public function nowTimestamp(): int
    {
        return $this->now()->getTimestamp();
    }

    public function fromTimestamp(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($this->zone);
    }

    public function timestamp(DateTimeImmutable $date): int
    {
        return $date->getTimestamp();
    }

    public function iso(DateTimeImmutable $date): string
    {
        return $date->setTimezone($this->zone)->format(DateTimeInterface::ATOM);
    }

    public function zone(): DateTimeZone
    {
        return $this->zone;
    }
}
