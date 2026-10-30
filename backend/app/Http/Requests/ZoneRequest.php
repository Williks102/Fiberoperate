<?php

namespace App\Http\Requests;

use App\Enums\Vendor;
use App\Enums\ZoneStatus;
use App\Models\Zone;
use Illuminate\Validation\Rule;

class ZoneRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Zone::class;
    }

    protected function routeKey(): string
    {
        return 'zone';
    }

    public function rules(): array
    {
        return [
            // Une sous-plaque ne change pas de plaque.
            'plaque_id' => $this->ignoreId() === null
                ? ['required', 'uuid', Rule::exists('plaques', 'id')->whereNull('deleted_at')]
                : ['missing'],
            'code' => [$this->req(), 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', Rule::enum(Vendor::class)],
            'status' => ['sometimes', Rule::enum(ZoneStatus::class)],
        ];
    }
}
