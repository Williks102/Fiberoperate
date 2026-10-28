<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Team;
use Illuminate\Validation\Rule;

class TeamRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Team::class;
    }

    protected function routeKey(): string
    {
        return 'team';
    }

    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->whereNull('deleted_at');

        return [
            'name' => [$this->req(), 'string', 'max:120'],
            'leader_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('role', Role::Technician->value)->whereNull('deleted_at')],
            'coordinator_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('role', Role::Coordinator->value)->whereNull('deleted_at')],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['uuid', 'distinct', $activeUser],
        ];
    }
}
