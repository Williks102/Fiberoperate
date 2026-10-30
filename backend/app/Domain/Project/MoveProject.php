<?php

namespace App\Domain\Project;

use App\Enums\ProjectPhase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parcours d'un projet : envoi en validation, décisions du chef du BE, du DT et du client, puis avancement par le BE.
 * Un refus renvoie toujours en avant-projet, avec un motif obligatoire. Chaque passage écrit une ligne d'historique,
 * dans la même transaction, sur le projet verrouillé (deux décisions simultanées ne peuvent pas se doubler) ;
 * les notifications partent une fois la transaction validée.
 */
class MoveProject
{
    public function __construct(private readonly ProjectNotifier $notifier) {}

    public function submit(Project $project, User $by): Project
    {
        return $this->run($project, $by, fn (ProjectPhase $current) => $current === ProjectPhase::PreProject ? ProjectPhase::BeManagerReview : null, null, null);
    }

    public function decide(Project $project, User $by, bool $approved, ?string $reason): Project
    {
        return $this->run(
            $project,
            $by,
            fn (ProjectPhase $current) => $current->deciders() === [] ? null : ($approved ? $current->nextOnApproval() : ProjectPhase::PreProject),
            $approved ? 'approved' : 'rejected',
            $reason,
        );
    }

    /** Fait avancer le projet après l'accord du client : survey, déploiement, relevé final, exploitation. */
    public function advance(Project $project, User $by): Project
    {
        return $this->run($project, $by, fn (ProjectPhase $current) => $current->nextOnAdvance(), null, null);
    }

    /** @param  callable(ProjectPhase): ?ProjectPhase  $target  phase à atteindre depuis la phase courante, null si impossible */
    private function run(Project $project, User $by, callable $target, ?string $decision, ?string $reason): Project
    {
        $to = DB::transaction(function () use ($project, $by, $target, $decision, $reason) {
            $fresh = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $project->setRawAttributes($fresh->getAttributes(), true);

            $from = $project->phase;
            $to = $target($from);
            if ($to === null) {
                throw ValidationException::withMessages(['phase' => "Cette action n'est pas possible en phase « {$from->label()} ». Rechargez la page."]);
            }

            $project->forceFill(['phase' => $to, 'phase_changed_at' => now()])->save();
            $project->history()->create(['from_phase' => $from, 'to_phase' => $to, 'decision' => $decision, 'reason' => $reason, 'changed_by' => $by->id]);

            return $to;
        });

        $this->notifier->phaseMoved($project, $to, $by, $decision, $reason);

        return $project;
    }
}
