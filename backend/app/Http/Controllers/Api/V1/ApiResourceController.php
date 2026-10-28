<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** Liste paginée, détail et suppression logique, avec contrôle d'accès par Policy. */
abstract class ApiResourceController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return class-string<JsonResource> */
    abstract protected function resourceClass(): string;

    /** Requête de départ, limitée au périmètre de l'utilisateur quand le modèle en a un. */
    protected function query(Request $request): Builder
    {
        $query = $this->modelClass()::query();

        return method_exists($this->modelClass(), 'scopeVisibleTo') ? $query->visibleTo($request->user()) : $query;
    }

    protected function filters(Request $request, Builder $query): void {}

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('created_at')->orderBy('id');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', $this->modelClass());

        $query = $this->query($request);
        $this->filters($request, $query);
        $this->orderBy($query);

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        return $this->resourceClass()::collection($query->paginate($perPage));
    }

    public function show(string $id): JsonResource
    {
        $model = $this->modelClass()::query()->findOrFail($id);
        $this->authorize('view', $model);

        return new ($this->resourceClass())($this->load($model));
    }

    public function destroy(string $id): JsonResponse
    {
        $model = $this->modelClass()::query()->findOrFail($id);
        $this->authorize('delete', $model);
        $model->delete();

        return response()->json(null, 204);
    }

    protected function load(Model $model): Model
    {
        return $model;
    }

    protected function created(Model $model): JsonResponse
    {
        return (new ($this->resourceClass())($this->load($model)))->response()->setStatusCode(201);
    }
}
