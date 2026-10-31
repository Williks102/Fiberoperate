<?php

namespace App\Policies;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\User;

/**
 * Commandes clients : vues par l'administration, la direction, le coordinateur et les techniciens habilités « étude » ou
 * « raccordement client ». Le périmètre par équipe n'est pas encore défini pour les commandes (à confirmer).
 */
class CustomerOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dg, Role::Dt, Role::Coordinator)
            || ($user->hasRole(Role::Technician) && ($user->hasSkill(MissionType::Study) || $user->hasSkill(MissionType::CustomerConnection)));
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Mettre en étude, programmer : le coordinateur et l'administrateur. */
    public function plan(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Coordinator);
    }

    /** Saisir le résultat d'une étude : le coordinateur et l'administrateur, ou le technicien à qui l'étude est confiée. */
    public function recordStudy(User $user, CustomerOrder $order): bool
    {
        return $user->hasRole(Role::Admin, Role::Coordinator)
            || ($user->hasRole(Role::Technician) && $order->interventions()
                ->where('type', MissionType::Study->value)->where('status', InterventionStatus::Planned->value)->where('technician_id', $user->getKey())->exists());
    }
}
