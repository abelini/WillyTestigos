<?php

declare(strict_types=1);

namespace Willy\Support;

final class TelegramConfig
{
    public function __construct(
        public readonly string $token,
        public readonly string $chatId,
    ) {
    }
}
