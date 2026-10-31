<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/** Visites planifiées : chaque technicien consulte les siennes. */
class InterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Technician);
    }
}
