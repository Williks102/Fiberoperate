<?php

namespace App\Http\Requests;

use App\Models\Olt;

class OltRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Olt::class;
    }

    protected function routeKey(): string
    {
        return 'olt';
    }

    public function rules(): array
    {
        return [
            'name' => [$this->req(), 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
