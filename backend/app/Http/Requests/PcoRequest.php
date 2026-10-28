<?php

namespace App\Http\Requests;

use App\Enums\PcoStatus;
use App\Models\Pco;
use Illuminate\Validation\Rule;

class PcoRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Pco::class;
    }

    protected function routeKey(): string
    {
        return 'pco';
    }

    public function rules(): array
    {
        return [
            'jdv_id' => [$this->req(), 'uuid', Rule::exists('jdvs', 'id')->whereNull('deleted_at')],
            'zone_id' => ['nullable', 'uuid', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'code' => [$this->req(), 'string', 'max:60'],
            'status' => ['nullable', Rule::enum(PcoStatus::class)],
            'status_raw' => ['nullable', 'string', 'max:255'],
            'is_blocked' => ['sometimes', 'boolean'],
            'block_reason_id' => ['nullable', 'uuid', Rule::exists('block_reasons', 'id')],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'olt_slot' => ['nullable', 'string', 'max:20'],
            'olt_port' => ['nullable', 'string', 'max:20'],
            'fiber_strand' => ['nullable', 'string', 'max:20'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
