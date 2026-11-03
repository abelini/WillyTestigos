<?php

declare(strict_types=1);

namespace Willy\Support;

final class MailConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $user,
        public readonly string $pass,
        public readonly string $encryption,
        public readonly string $from,
        public readonly string $fromName,
        public readonly string $to,
    ) {
    }
}
