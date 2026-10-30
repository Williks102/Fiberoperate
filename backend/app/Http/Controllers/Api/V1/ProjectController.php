<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Project\CreateProject;
use App\Domain\Project\MoveProject;
use App\Enums\PcoStatus;
use App\Enums\ProjectPhase;
use App\Http\Requests\AdvanceProjectRequest;
use App\Http\Requests\DecideProjectRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\SubmitProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Plaque;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Project::class;
    }

    protected function resourceClass(): string
    {
        return ProjectResource::class;
    }

    protected function load(Model $model): Model
    {
        $model->load(['plaque.olt:id,name', 'plaque.supervisor:id,name', 'history.author:id,name']);
        $model->plaque?->load(['zones' => fn ($q) => $q->with('sro:id,zone_id,code')->withCount([
            'jdvs', 'pcos',
            'pcos as pcos_to_pull_count' => fn ($q) => $q->where('status', PcoStatus::ToPull->value),
            'pcos as pcos_pulled_count' => fn ($q) => $q->where('status', PcoStatus::Pulled->value),
            'pcos as pcos_spliced_count' => fn ($q) => $q->where('status', PcoStatus::Spliced->value),
            'pcos as pcos_activated_count' => fn ($q) => $q->where('status', PcoStatus::Activated->value),
            'pcos as pcos_measured_count' => fn ($q) => $q->whereNotNull('measured_at'),
        ])->orderBy('code_normalized')]);

        return $model;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderByDesc('phase_changed_at')->orderBy('id');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with('plaque:id,name')
            ->when(ProjectPhase::tryFrom((string) $request->query('phase')), fn ($q, $phase) => $q->where('phase', $phase->value))
            ->when($request->query('plaque_id'), fn ($q, $id) => $q->where('plaque_id', $id))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->whereIn('plaque_id', Plaque::query()->where('name', 'ilike', "%{$term}%")->select('id')));
    }

    public function store(ProjectRequest $request, CreateProject $create): JsonResponse
    {
        if ($request->filled('plaque_id')) {
            return $this->created(Project::startFor(Plaque::query()->findOrFail($request->validated('plaque_id')), $request->user()));
        }

        return $this->created($create->run($request->validated(), $request->user()));
    }

    /** Envoie l'avant-projet en validation (chef du BE, puis DT, puis client). */
    public function submit(SubmitProjectRequest $request, string $project, MoveProject $move): ProjectResource
    {
        return new ProjectResource($this->load($move->submit(Project::query()->findOrFail($project), $request->user())));
    }

    /** Valide ou refuse la phase de validation en cours ; un refus renvoie en avant-projet, avec son motif. */
    public function decision(DecideProjectRequest $request, string $project, MoveProject $move): ProjectResource
    {
        $moved = $move->decide(Project::query()->findOrFail($project), $request->user(), $request->input('decision') === 'approved', $request->input('reason'));

        return new ProjectResource($this->load($moved));
    }

    /** Fait avancer le projet après l'accord du client (déploiement, relevé final, exploitation). */
    public function advance(AdvanceProjectRequest $request, string $project, MoveProject $move): ProjectResource
    {
        return new ProjectResource($this->load($move->advance(Project::query()->findOrFail($project), $request->user())));
    }
}
