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

    protected function targetPlaqueId(): ?string
    {
        return $this->plaqueOfZone($this->input('zone_id'));
    }

    public function rules(): array
    {
        return [
            // Un splitter ne change pas de sous-plaque : ses PCO y sont rattachés.
            'zone_id' => $this->ignoreId() === null
                ? ['required', 'uuid', Rule::exists('zones', 'id')->whereNull('deleted_at')]
                : ['prohibited'],
            'code' => [$this->req(), 'string', 'max:40'],
        ];
    }
}
