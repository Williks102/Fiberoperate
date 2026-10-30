<?php

namespace App\Domain\Project;

use App\Enums\ProjectPhase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Circuit de validation d'un projet : envoi en validation, puis décision du chef du BE, du DT et du client.
 * Un refus renvoie toujours en avant-projet, avec un motif obligatoire. Chaque passage écrit une ligne d'historique,
 * dans la même transaction, sur le projet verrouillé (deux décisions simultanées ne peuvent pas se doubler).
 */
class MoveProject
{
    public function submit(Project $project, User $by): Project
    {
        return $this->move($project, ProjectPhase::PreProject, ProjectPhase::BeManagerReview, $by, null, null);
    }

    public function decide(Project $project, User $by, bool $approved, ?string $reason): Project
    {
        return DB::transaction(function () use ($project, $by, $approved, $reason) {
            $current = $this->lock($project)->phase;
            $next = $approved ? $current->nextOnApproval() : ProjectPhase::PreProject;

            if ($current->deciders() === [] || $next === null) {
                throw ValidationException::withMessages(['phase' => "Le projet n'est pas en phase de validation."]);
            }

            return $this->move($project, $current, $next, $by, $approved ? 'approved' : 'rejected', $reason);
        });
    }

    private function move(Project $project, ProjectPhase $expected, ProjectPhase $to, User $by, ?string $decision, ?string $reason): Project
    {
        return DB::transaction(function () use ($project, $expected, $to, $by, $decision, $reason) {
            $from = $this->lock($project)->phase;

            if ($from !== $expected) {
                throw ValidationException::withMessages(['phase' => 'La phase du projet a changé : « '.$from->label().' ». Rechargez la page.']);
            }

            $project->forceFill(['phase' => $to, 'phase_changed_at' => now()])->save();
            $project->history()->create(['from_phase' => $from, 'to_phase' => $to, 'decision' => $decision, 'reason' => $reason, 'changed_by' => $by->id]);

            return $project;
        });
    }

    private function lock(Project $project): Project
    {
        $fresh = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
        $project->setRawAttributes($fresh->getAttributes(), true);

        return $project;
    }
}
