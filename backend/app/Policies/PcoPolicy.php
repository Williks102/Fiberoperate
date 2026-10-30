<?php

namespace App\Policies;

use App\Models\User;

class PcoPolicy extends NetworkEditPolicy
{
    /** Relevé de terrain : la position mesurée est enregistrée par le BE et les techniciens « relevés ». */
    public function measure(User $user): bool
    {
        return $user->canSurvey();
    }
}
