<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Dg = 'dg';
    case Dt = 'dt';
    case Supervisor = 'supervisor';
    case Coordinator = 'coordinator';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Dg => 'Directeur général',
            self::Dt => 'Directeur technique',
            self::Supervisor => 'Superviseur',
            self::Coordinator => 'Coordinateur',
            self::Technician => 'Technicien',
        };
    }
}
