<?php

namespace App\Domain\Project;

use App\Enums\ProjectPhase;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectPhaseChanged;
use Illuminate\Support\Collection;

/** Prévient les bonnes personnes quand un projet change de phase (cloche de l'application). */
class ProjectNotifier
{
    public function phaseMoved(Project $project, ProjectPhase $to, User $by, ?string $decision, ?string $reason): void
    {
        $plaque = $project->plaque()->value('name');

        [$recipients, $title, $message] = match (true) {
            $decision === 'rejected' => [$this->beTeam($project), 'Avant-projet refusé', "{$plaque} : refusé, retour en avant-projet. Motif : {$reason}"],
            $to === ProjectPhase::BeManagerReview => [$this->byRole(Role::BeManager), 'Avant-projet à valider', "{$plaque} : l'avant-projet attend la validation du chef du BE."],
            $to === ProjectPhase::DtReview => [$this->byRole(Role::Dt), 'Avant-projet à valider', "{$plaque} : validé par le chef du BE, il attend la validation du DT."],
            $to === ProjectPhase::ClientReview => [$this->beTeam($project), 'En attente du client', "{$plaque} : validé par le DT, il attend l'accord du client (le DT l'enregistre)."],
            $to === ProjectPhase::ImplantationSurvey => [$this->beTeam($project), 'Accord du client enregistré', "{$plaque} : le client a donné son accord, le survey d'implantation peut commencer."],
            $to === ProjectPhase::Deployment => [collect([$project->plaque()->first()?->supervisor]), 'Déploiement lancé', "{$plaque} : le déploiement commence, la plaque est à votre charge."],
            default => [$this->byRole(Role::Dt), 'Projet : '.$to->label(), "{$plaque} est passé en phase « {$to->label()} »."],
        };

        $recipients->filter()->reject(fn (User $user) => $user->id === $by->id)->unique('id')->each(
            fn (User $user) => $user->notify(new ProjectPhaseChanged($project->id, $title, $message)),
        );
    }

    /** @return Collection<int, User> */
    private function byRole(Role $role): Collection
    {
        return User::query()->where('role', $role->value)->where('is_active', true)->get();
    }

    /** Le chef du BE et celui qui a envoyé l'avant-projet en validation. */
    private function beTeam(Project $project): Collection
    {
        $submitter = $project->history()->where('to_phase', ProjectPhase::BeManagerReview->value)->latest('created_at')->first()?->author;

        return $this->byRole(Role::BeManager)->push($submitter);
    }
}
