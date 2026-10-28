<?php

namespace App\Enums;

/**
 * Habilitations de mission d'un technicien : un seul rôle « technician »,
 * plusieurs types de mission possibles.
 */
enum MissionType: string
{
    case Deployment = 'deployment';
    case CustomerConnection = 'customer_connection';
    case Maintenance = 'maintenance';
    case Study = 'study';

    public function label(): string
    {
        return match ($this) {
            self::Deployment => 'Déploiement',
            self::CustomerConnection => 'Raccordement client',
            self::Maintenance => 'Maintenance',
            self::Study => 'Étude',
        };
    }
}
