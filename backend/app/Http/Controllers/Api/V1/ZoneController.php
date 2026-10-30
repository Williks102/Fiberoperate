<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Zone::class;
    }

    protected function resourceClass(): string
    {
        return ZoneResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('code_normalized');
    }

    protected function load(Model $model): Model
    {
        return $model->load('plaque:id,name');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with('plaque:id,name')->when($request->query('plaque_id'), fn ($q, $id) => $q->where('plaque_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('q')->trim()->value(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('code_normalized', 'like', '%'.CodeNormalizer::zone($term).'%')
                    ->orWhere('name', 'ilike', "%{$term}%"));
            });
    }

    public function store(ZoneRequest $request): JsonResponse
    {
        return $this->created(Zone::create($request->validated()));
    }

    public function update(ZoneRequest $request, string $zone): ZoneResource
    {
        $model = Zone::query()->findOrFail($zone);
        $model->update($request->validated());

        return new ZoneResource($this->load($model));
    }
}
