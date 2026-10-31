<?php

namespace App\Policies;

use App\Enums\MissionType;
use App\Enums\Role;
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
}
