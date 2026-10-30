<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Plaque : l'administrateur et le bureau d'études la modifient (nom, OLT envoyé par le client), sans limite de phase ;
 * le DT désigne seul son superviseur (voir PlaqueRequest). La suppression reste à l'administrateur.
 */
class PlaquePolicy extends NetworkPolicy
{
    public function update(User $user, Model $model): bool
    {
        return $user->hasRole(Role::Admin, Role::Dt) || $user->isBeMember();
    }
}
