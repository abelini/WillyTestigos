<?php

declare(strict_types=1);

namespace Willy\Domain;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Sent => 'Enviado',
            self::Failed => 'Error',
            self::Skipped => 'No configurado',
        };
    }
}
