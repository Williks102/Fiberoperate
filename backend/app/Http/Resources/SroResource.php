<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'code' => $this->code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'olt_slot' => $this->olt_slot,
            'olt_port' => $this->olt_port,
            'fiber_strand' => $this->fiber_strand,
            'zone' => $this->whenLoaded('zone', fn () => [
                'id' => $this->zone->id,
                'code' => $this->zone->code,
                'plaque' => $this->zone->relationLoaded('plaque') ? $this->zone->plaque?->only(['id', 'name']) : null,
            ]),
        ];
    }
}
