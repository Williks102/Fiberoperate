<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dg, Role::Dt, Role::Coordinator);
    }

    public function view(User $user, Team $team): bool
    {
        return Team::query()->visibleTo($user)->whereKey($team->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dt);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasRole(Role::Admin, Role::Dt);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasRole(Role::Admin, Role::Dt);
    }
}
