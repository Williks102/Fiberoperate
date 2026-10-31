<?php

namespace App\Enums;

/** Résultat de l'étude d'une commande « étudiée ». */
enum StudyResult: string
{
    case Feasible = 'feasible';
    case NotFeasible = 'not_feasible';
    case CustomerAbsent = 'customer_absent';

    public function label(): string
    {
        return match ($this) {
            self::Feasible => 'Faisable',
            self::NotFeasible => 'Non faisable',
            self::CustomerAbsent => 'Client absent',
        };
    }
}
