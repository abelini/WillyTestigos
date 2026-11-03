<?php

declare(strict_types=1);

namespace Willy\Domain;

enum JobStatus: string
{
    case Created = 'created';
    case Uploading = 'uploading';
    case Queued = 'queued';
    case Processing = 'processing';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creado',
            self::Uploading => 'Subiendo capturas',
            self::Queued => 'En cola',
            self::Processing => 'Generando PDF',
            self::Delivering => 'Enviando PDF',
            self::Completed => 'Completado',
            self::Failed => 'Error',
        };
    }

    public function isActive(): bool
    {
        return match ($this) {
            self::Created, self::Uploading, self::Queued, self::Processing, self::Delivering => true,
            self::Completed, self::Failed => false,
        };
    }

    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            self::Created, self::Uploading, self::Queued, self::Processing, self::Delivering => false,
        };
    }
}
