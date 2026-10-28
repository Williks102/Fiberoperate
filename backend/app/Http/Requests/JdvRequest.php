<?php

namespace App\Http\Requests;

use App\Models\Jdv;
use Illuminate\Validation\Rule;

class JdvRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Jdv::class;
    }

    protected function routeKey(): string
    {
        return 'jdv';
    }

    public function rules(): array
    {
        return [
            'zone_id' => [$this->req(), 'uuid', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'code' => [$this->req(), 'string', 'max:40'],
        ];
    }
}
