<?php

declare(strict_types=1);

namespace Willy\Tests;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Willy\Support\Clock;

final class FixedClock implements Clock
{
    public function __construct(
        private readonly DateTimeImmutable $now,
        private readonly DateTimeZone $zone,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
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
