<?php

namespace App\Enums;

/** État d'une intervention planifiée. */
enum InterventionStatus: string
{
    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planifiée',
            self::Done => 'Réalisée',
            self::Cancelled => 'Annulée',
        };
    }
}
