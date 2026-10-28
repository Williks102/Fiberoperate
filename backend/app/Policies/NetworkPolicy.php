<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel réseau (OLT, plaques, zones, JDV, PCO) : lecture dans le périmètre
 * (scope `visibleTo` du modèle), écriture réservée à l'administrateur.
 */
abstract class NetworkPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $model::query()->visibleTo($user)->whereKey($model->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
