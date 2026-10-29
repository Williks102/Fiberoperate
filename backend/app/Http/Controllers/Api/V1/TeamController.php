<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends ApiResourceController
{
    private const RELATIONS = ['leader:id,name', 'coordinator:id,name', 'members:id,name'];

    protected function modelClass(): string
    {
        return Team::class;
    }

    protected function resourceClass(): string
    {
        return TeamResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('name');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with(self::RELATIONS);
    }

    protected function load(Model $model): Model
    {
        return $model->load(self::RELATIONS);
    }

    public function store(TeamRequest $request): JsonResponse
    {
        $team = DB::transaction(function () use ($request) {
            $team = Team::create($request->safe()->except('member_ids'));
            $team->members()->sync($request->validated('member_ids', []));

            return $team;
        });

        return $this->created($team);
    }

    public function update(TeamRequest $request, string $team): TeamResource
    {
        $model = Team::query()->findOrFail($team);

        DB::transaction(function () use ($request, $model) {
            $model->update($request->safe()->except('member_ids'));
            if ($request->has('member_ids')) {
                $model->members()->sync($request->validated('member_ids'));
            }
        });

        return new TeamResource($this->load($model));
    }
}
