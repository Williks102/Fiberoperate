<?php

namespace App\Enums;

/** Statut interne d'une commande client (cahier §3.4) : reçue → en étude → étudiée → programmée → installée | reportée | échec → activée. */
enum OrderStatus: string
{
    case Received = 'received';
    case InStudy = 'in_study';
    case Studied = 'studied';
    case AppointmentSet = 'appointment_set';
    case Installed = 'installed';
    case Postponed = 'postponed';
    case Failed = 'failed';
    case Activated = 'activated';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Reçue',
            self::InStudy => 'En étude',
            self::Studied => 'Étudiée',
            self::AppointmentSet => 'Installation programmée',
            self::Installed => 'Installée',
            self::Postponed => 'Reportée',
            self::Failed => 'Échec',
            self::Activated => 'Activée',
        };
    }
}
