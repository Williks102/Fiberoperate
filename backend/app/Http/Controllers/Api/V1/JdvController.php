<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\JdvRequest;
use App\Http\Resources\JdvResource;
use App\Models\Jdv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JdvController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return Jdv::class;
    }

    protected function resourceClass(): string
    {
        return JdvResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('code_normalized');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id));
    }

    public function store(JdvRequest $request): JsonResponse
    {
        return $this->created(Jdv::create($request->validated()));
    }

    public function update(JdvRequest $request, string $jdv): JdvResource
    {
        $model = Jdv::query()->findOrFail($jdv);
        $model->update($request->validated());

        return new JdvResource($model);
    }
}
