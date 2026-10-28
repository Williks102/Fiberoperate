<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Dg, Role::Dt);
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole(Role::Admin);
    }

    /** Un compte ne se supprime jamais lui-même. */
    public function delete(User $user, User $target): bool
    {
        return $user->hasRole(Role::Admin) && ! $user->is($target);
    }
}
