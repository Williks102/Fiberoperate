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
            'comment' => $this->comment,
            'observation' => $this->observation,
        ];
    }
}
