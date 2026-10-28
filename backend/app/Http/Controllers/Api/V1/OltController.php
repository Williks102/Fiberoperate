<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\OltRequest;
use App\Http\Resources\OltResource;
use App\Models\Olt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OltController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Olt::class;
    }

    protected function resourceClass(): string
    {
        return OltResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('name', 'ilike', "%{$term}%"));
    }

    public function store(OltRequest $request): JsonResponse
    {
        return $this->created(Olt::create($request->validated()));
    }

    public function update(OltRequest $request, string $olt): OltResource
    {
        $model = Olt::query()->findOrFail($olt);
        $model->update($request->validated());

        return new OltResource($model);
    }
}
