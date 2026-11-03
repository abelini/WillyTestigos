<?php

declare(strict_types=1);

namespace Willy\Support;

use SplFileObject;

final class Lock
{
    private function __construct(
        private readonly SplFileObject $file,
        private readonly bool $acquired,
    ) {
    }

    public static function acquire(string $path, bool $blocking): self
    {
        $file = new SplFileObject($path, 'c');
        $operation = LOCK_EX;

        if (!$blocking) {
            $operation |= LOCK_NB;
        }

        $wouldBlock = 0;
        $acquired = $file->flock($operation, $wouldBlock);

        return new self($file, $acquired);
    }

    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    public function release(): void
    {
        if ($this->acquired) {
            $this->file->flock(LOCK_UN);
        }
    }
}
