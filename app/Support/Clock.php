<?php

declare(strict_types=1);

namespace Willy\Support;

use DateTimeImmutable;
use DateTimeZone;

interface Clock
{
    public function now(): DateTimeImmutable;

    /** Marca de tiempo Unix del instante actual. */
    public function nowTimestamp(): int;

    public function fromTimestamp(int $timestamp): DateTimeImmutable;

    public function timestamp(DateTimeImmutable $date): int;

    public function iso(DateTimeImmutable $date): string;

    public function zone(): DateTimeZone;
}
