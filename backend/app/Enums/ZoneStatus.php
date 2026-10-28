<?php

namespace App\Enums;

/** Statut d'une zone (sous-plaque). La clôture est validée par le DT. */
enum ZoneStatus: string
{
    case Planned = 'planned';
    case Deploying = 'deploying';
    case InOperation = 'in_operation';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planifiée',
            self::Deploying => 'En déploiement',
            self::InOperation => 'En exploitation',
            self::Closed => 'Clôturée',
        };
    }
}
