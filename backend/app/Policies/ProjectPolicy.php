<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Project;
use App\Models\User;

/**
 * Lecture : administrateur, DG, DT, chef du BE (tous les projets) et superviseur (celui de sa plaque).
 * Ouverture d'un projet : administrateur et chef du BE (décision du 30/10/2026). Les changements de phase viennent avec le circuit de validation.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dg, Role::Dt, Role::BeManager, Role::Supervisor);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user) && Project::query()->visibleTo($user)->whereKey($project->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::BeManager);
    }
}
