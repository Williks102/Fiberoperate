<?php

namespace App\Enums;

/** Nature d'un projet : le déploiement d'une plaque, ou une désaturation (nouveau projet sur une plaque déjà déployée). */
enum ProjectKind: string
{
    case Deployment = 'deployment';
    case Desaturation = 'desaturation';

    public function label(): string
    {
        return match ($this) {
            self::Deployment => 'Déploiement',
            self::Desaturation => 'Désaturation',
        };
    }
}
