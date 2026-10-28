<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BlockReasonResource;
use App\Models\BlockReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/** Référentiel en lecture seule pour le moment (liste à valider avec le client, cahier §3.6). */
class BlockReasonController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return BlockReason::class;
    }

    protected function resourceClass(): string
    {
        return BlockReasonResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('label');
    }

    public function show(string $id): JsonResource
    {
        return new BlockReasonResource(BlockReason::query()->findOrFail($id));
    }

    public function destroy(string $id): JsonResponse
    {
        abort(405);
    }
}
