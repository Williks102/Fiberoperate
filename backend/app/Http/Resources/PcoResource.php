<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PcoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'jdv_id' => $this->jdv_id,
            'code' => $this->code,
            'number' => $this->number,
            'status' => $this->status?->value,
            'status_raw' => $this->status_raw,
            'is_blocked' => $this->is_blocked,
            'block_reason_id' => $this->block_reason_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'olt_slot' => $this->olt_slot,
            'olt_port' => $this->olt_port,
            'fiber_strand' => $this->fiber_strand,
            'capacity' => $this->capacity,
            'measured_latitude' => $this->measured_latitude,
            'measured_longitude' => $this->measured_longitude,
            'measured_at' => $this->measured_at?->toIso8601String(),
            'measured_by' => $this->whenLoaded('measurer', fn () => $this->measurer?->only(['id', 'name'])),
            'deviation_m' => $this->deviationMeters(),
            'comment' => $this->comment,
            'observation' => $this->observation,
            'jdv' => $this->whenLoaded('jdv', fn () => $this->jdv?->only(['id', 'code'])),
            'zone' => $this->whenLoaded('zone', fn () => $this->zone === null ? null : [
                'id' => $this->zone->id,
                'code' => $this->zone->code,
                'plaque' => $this->zone->relationLoaded('plaque') ? $this->zone->plaque?->only(['id', 'name']) : null,
            ]),
        ];
    }
}
