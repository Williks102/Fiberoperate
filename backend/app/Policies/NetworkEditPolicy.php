<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Structure du réseau d'une plaque (sous-plaques, SRO, splitters, PCO) : l'administrateur et le bureau d'études
 * (chef du BE et techniciens du BE, habilitation « dessin ») écrivent, sans limite de phase du projet.
 */
abstract class NetworkEditPolicy extends NetworkPolicy
{
    public function create(User $user): bool
    {
        return $this->canEdit($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canEdit($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canEdit($user);
    }

    private function canEdit(User $user): bool
    {
        return $user->hasRole(Role::Admin) || $user->isBeMember();
    }
}
