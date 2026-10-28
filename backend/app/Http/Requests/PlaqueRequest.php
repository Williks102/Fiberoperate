<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\Plaque;
use Illuminate\Validation\Rule;

class PlaqueRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Plaque::class;
    }

    protected function routeKey(): string
    {
        return 'plaque';
    }

    public function rules(): array
    {
        return [
            'name' => [$this->req(), 'string', 'max:120'],
            'olt_id' => ['nullable', 'uuid', Rule::exists('olts', 'id')->whereNull('deleted_at')],
            'supervisor_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id')->where('role', Role::Supervisor->value)->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return ['supervisor_id.exists' => 'Le superviseur choisi n\'existe pas ou n\'a pas le rôle superviseur.'];
    }
}
