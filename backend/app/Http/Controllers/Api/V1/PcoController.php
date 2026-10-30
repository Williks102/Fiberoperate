<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PcoRequest;
use App\Http\Resources\PcoResource;
use App\Models\Pco;
use App\Models\Zone;
use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcoController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Pco::class;
    }

    protected function resourceClass(): string
    {
        return PcoResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('code_normalized');
    }

    protected function load(Model $model): Model
    {
        return $model->load(['jdv:id,code', 'zone.plaque:id,name']);
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with(['jdv:id,code', 'zone.plaque:id,name'])
            ->when($request->query('plaque_id'), fn ($q, $id) => $q->whereIn('zone_id', Zone::query()->where('plaque_id', $id)->select('id')))
            ->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id))
            ->when($request->query('jdv_id'), fn ($q, $id) => $q->where('jdv_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->has('is_blocked'), fn ($q) => $q->where('is_blocked', $request->boolean('is_blocked')))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('code_normalized', 'like', '%'.CodeNormalizer::pco($term).'%')
                    ->orWhere('code_normalized', 'like', '%'.CodeNormalizer::zone($term).'%'),
            ));
    }

    public function store(PcoRequest $request): JsonResponse
    {
        return $this->created(Pco::create($request->validated()));
    }

    public function update(PcoRequest $request, string $pco): PcoResource
    {
        $model = Pco::query()->findOrFail($pco);
        $model->update($request->validated());

        return new PcoResource($this->load($model));
    }
}
