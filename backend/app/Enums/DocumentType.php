<?php

namespace App\Enums;

/** Types de document d'un projet (docs/workflow-projet-deploiement.md) : chacun est versionné, jamais écrasé. */
enum DocumentType: string
{
    case PlaquesPlan = 'plaques_plan';
    case Synoptic = 'synoptic';
    case Kmz = 'kmz';
    case ZonePlan = 'zone_plan';
    case Avp = 'avp';
    case ClientProof = 'client_proof';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PlaquesPlan => 'Plan des plaques',
            self::Synoptic => 'Plan synoptique',
            self::Kmz => 'Plan KMZ',
            self::ZonePlan => 'Plan de la sous-plaque',
            self::Avp => 'Dossier AVP',
            self::ClientProof => "Justificatif d'accord du client",
            self::Other => 'Autre document',
        };
    }

    /** Un plan de sous-plaque se rattache à sa sous-plaque. */
    public function needsZone(): bool
    {
        return $this === self::ZonePlan;
    }
}
