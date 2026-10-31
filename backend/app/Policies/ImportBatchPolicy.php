<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ImportBatch;
use App\Models\User;

/** Import des clients : le coordinateur et l'administrateur ; chacun ne voit que ses analyses (l'administrateur voit tout). */
class ImportBatchPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Coordinator);
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $this->create($user) && ($user->hasRole(Role::Admin) || $batch->created_by === $user->id);
    }
}
