<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/** Catalogue de prix : consulté par l'administration, la direction et le BE ; mis à jour par l'administrateur seul. */
class PriceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dg, Role::Dt) || $user->isBeMember();
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
