<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OltPolicy extends NetworkPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->hasRole(Role::Technician) || $user->seesWholeNetwork();
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }
}
