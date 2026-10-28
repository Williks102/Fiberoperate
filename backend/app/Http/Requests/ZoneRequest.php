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
            'plaque_id' => [$this->req(), 'uuid', Rule::exists('plaques', 'id')->whereNull('deleted_at')],
            'code' => [$this->req(), 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', Rule::enum(Vendor::class)],
            'status' => ['sometimes', Rule::enum(ZoneStatus::class)],
        ];
    }
}
