<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\SroRequest;
use App\Http\Resources\SroResource;
use App\Models\Sro;
use App\Models\Zone;
use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SroController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Sro::class;
    }

    protected function resourceClass(): string
    {
        return SroResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('code_normalized');
    }

    protected function load(Model $model): Model
    {
        return $model->load('zone.plaque:id,name');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with('zone.plaque:id,name')
            ->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id))
            ->when($request->query('plaque_id'), fn ($q, $id) => $q->whereIn('zone_id', Zone::query()->where('plaque_id', $id)->select('id')))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('code_normalized', 'like', '%'.CodeNormalizer::generic($term).'%'));
    }

    public function store(SroRequest $request): JsonResponse
    {
        return $this->created(Sro::create($request->validated()));
    }

    public function update(SroRequest $request, string $sro): SroResource
    {
        $model = Sro::query()->findOrFail($sro);
        $model->update($request->validated());

        return new SroResource($this->load($model));
    }
}
