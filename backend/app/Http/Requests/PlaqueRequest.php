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

    /** Le DT désigne seul le superviseur ; l'administrateur et le BE modifient le reste (nom, OLT envoyé par le client). */
    public function rules(): array
    {
        $isDt = $this->user()->hasRole(Role::Dt);

        return [
            'name' => $isDt ? ['missing'] : [$this->req(), 'string', 'max:120'],
            'olt_id' => $isDt ? ['missing'] : ['nullable', 'uuid', Rule::exists('olts', 'id')->whereNull('deleted_at')],
            'supervisor_id' => $isDt ? [
                'nullable', 'uuid',
                Rule::exists('users', 'id')->where('role', Role::Supervisor->value)->whereNull('deleted_at'),
            ] : ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'supervisor_id.exists' => 'Le superviseur choisi n\'existe pas ou n\'a pas le rôle superviseur.',
            'supervisor_id.missing' => 'Seul le DT désigne le superviseur d\'une plaque.',
            'name.missing' => 'Le DT ne modifie que le superviseur de la plaque.',
            'olt_id.missing' => 'Le DT ne modifie que le superviseur de la plaque.',
        ];
    }
}
