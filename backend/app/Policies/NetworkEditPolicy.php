<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Structure du réseau d'une plaque (sous-plaques, SRO, splitters, PCO) : l'administrateur écrit toujours ;
 * le chef du BE et les techniciens du BE (habilitation « dessin ») l'ajustent tant que le projet de la plaque
 * est en avant-projet (ensuite le plan est soumis aux validations).
 */
abstract class NetworkEditPolicy extends NetworkPolicy
{
    /** Création : `$plaqueId` est la plaque visée (null si inconnue : seul l'administrateur peut alors créer). */
    public function create(User $user, ?string $plaqueId = null): bool
    {
        return $this->canEdit($user, $plaqueId);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canEdit($user, $model->plaqueId());
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canEdit($user, $model->plaqueId());
    }

    private function canEdit(User $user, ?string $plaqueId): bool
    {
        if ($user->hasRole(Role::Admin)) {
            return true;
        }

        if ($plaqueId === null || ! $user->isBeMember()) {
            return false;
        }

        return Project::query()->where('plaque_id', $plaqueId)->first()?->phase->beMayEditNetwork() === true;
    }
}
