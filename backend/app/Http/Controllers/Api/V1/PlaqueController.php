<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PlaqueRequest;
use App\Http\Resources\PlaqueResource;
use App\Models\Plaque;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaqueController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Plaque::class;
    }

    protected function resourceClass(): string
    {
        return PlaqueResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function load(Model $model): Model
    {
        return $model->load(['olt:id,name', 'supervisor:id,name']);
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with(['olt:id,name', 'supervisor:id,name'])->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('name', 'ilike', "%{$term}%"));
    }

    public function store(PlaqueRequest $request): JsonResponse
    {
        return $this->created(Plaque::create($request->validated()));
    }

    public function update(PlaqueRequest $request, string $plaque): PlaqueResource
    {
        $model = Plaque::query()->findOrFail($plaque);
        $model->update($request->validated());

        return new PlaqueResource($this->load($model));
    }
}
