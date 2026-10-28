<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function resourceClass(): string
    {
        return UserResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }

    protected function load(Model $model): Model
    {
        return $model->load('skills');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->with('skills')
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'ilike', "%{$term}%")->orWhere('email', 'ilike', "%{$term}%"),
            ));
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertSkillsAllowed($data['role'], $data['skills'] ?? []);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except('skills')->all());
            $this->syncSkills($user, $data['skills'] ?? []);

            return $user;
        });

        return $this->created($user);
    }

    public function update(UserRequest $request, string $user): UserResource
    {
        $model = User::query()->findOrFail($user);
        $data = $request->validated();

        if ($request->user()->is($model) && ($data['is_active'] ?? true) === false) {
            throw ValidationException::withMessages(['is_active' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }
        if ($request->user()->is($model) && isset($data['role']) && $data['role'] !== $model->role->value) {
            throw ValidationException::withMessages(['role' => 'Vous ne pouvez pas changer votre propre rôle.']);
        }

        $role = $data['role'] ?? $model->role->value;
        $this->assertSkillsAllowed($role, $data['skills'] ?? []);

        DB::transaction(function () use ($model, $data, $role) {
            $model->update(collect($data)->except('skills')->all());
            if (array_key_exists('skills', $data) || $role !== Role::Technician->value) {
                $this->syncSkills($model, $role === Role::Technician->value ? $data['skills'] : []);
            }
        });

        // Un compte désactivé perd immédiatement ses jetons.
        if (! $model->is_active) {
            $model->tokens()->delete();
        }

        return new UserResource($this->load($model));
    }

    public function destroy(string $id): JsonResponse
    {
        $model = User::query()->findOrFail($id);
        $this->authorize('delete', $model);
        $model->tokens()->delete();
        $model->delete();

        return response()->json(null, 204);
    }

    /** Les habilitations de mission n'existent que pour le rôle technicien. */
    private function assertSkillsAllowed(string $role, array $skills): void
    {
        if ($skills !== [] && $role !== Role::Technician->value) {
            throw ValidationException::withMessages(['skills' => 'Seul un technicien peut avoir des habilitations de mission.']);
        }
    }

    private function syncSkills(User $user, array $skills): void
    {
        $user->skills()->whereNotIn('mission_type', $skills)->delete();
        foreach ($skills as $mission) {
            $user->skills()->firstOrCreate(['mission_type' => $mission]);
        }
    }
}
