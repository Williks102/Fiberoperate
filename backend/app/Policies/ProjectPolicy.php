<?php

namespace App\Policies;

use App\Enums\ProjectPhase;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;

/**
 * Lecture : administrateur, DG, DT, chef du BE (tous les projets) et superviseur (celui de sa plaque).
 * Ouverture d'un projet : administrateur et chef du BE (décision du 30/10/2026). Circuit de validation : voir submit() et decide().
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

    /** Envoi en validation : le BE (ou l'administrateur) depuis l'avant-projet. */
    public function submit(User $user, Project $project): bool
    {
        return $project->phase === ProjectPhase::PreProject && ($user->hasRole(Role::Admin) || $user->isBeMember());
    }

    /** Devis du projet (prix, quantités) : direction, administration et BE ; pas le superviseur. */
    public function viewQuote(User $user, Project $project): bool
    {
        return $user->hasRole(Role::Admin, Role::Dg, Role::Dt) || $user->isBeMember();
    }

    /** Rédacteurs du devis : le BE et l'administrateur (les phases limitent ensuite ce qui se modifie). */
    public function writeQuote(User $user, Project $project): bool
    {
        return $user->hasRole(Role::Admin) || $user->isBeMember();
    }

    /** Quantités d'étude : le BE (ou l'administrateur) en avant-projet seulement. */
    public function editStudyQuote(User $user, Project $project): bool
    {
        return $this->writeQuote($user, $project) && $project->phase->quoteStudyEditable();
    }

    /** Quantités réalisées : le BE (ou l'administrateur) pendant et après le déploiement. */
    public function editDoneQuote(User $user, Project $project): bool
    {
        return $this->writeQuote($user, $project) && $project->phase->quoteDoneEditable();
    }

    /** Fait avancer le projet après l'accord du client (survey, déploiement, relevé final, exploitation) : le BE ou l'administrateur. */
    public function advance(User $user, Project $project): bool
    {
        return $project->phase->nextOnAdvance() !== null && ($user->hasRole(Role::Admin) || $user->isBeMember());
    }

    /** Décision d'une phase de validation : le rôle prévu pour cette phase (chef du BE, DT, ou celui qui enregistre l'accord du client). */
    public function decide(User $user, Project $project): bool
    {
        return in_array($user->role, $project->phase->deciders(), true);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::BeManager);
    }
}
