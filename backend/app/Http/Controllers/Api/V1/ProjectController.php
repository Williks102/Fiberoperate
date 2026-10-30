<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectPhase;
use App\Http\Requests\ProjectRequest;
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
        return $model->load(['plaque:id,name', 'history.author:id,name']);
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

    public function store(ProjectRequest $request): JsonResponse
    {
        $plaque = Plaque::query()->findOrFail($request->validated('plaque_id'));

        return $this->created(Project::startFor($plaque, $request->user()));
    }
}
