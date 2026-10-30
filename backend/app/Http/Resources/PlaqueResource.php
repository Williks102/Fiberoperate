<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaqueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'olt_id' => $this->olt_id,
            'supervisor_id' => $this->supervisor_id,
            'olt' => $this->whenLoaded('olt', fn () => $this->olt?->only(['id', 'name'])),
            'supervisor' => $this->whenLoaded('supervisor', fn () => $this->supervisor?->only(['id', 'name'])),
        ];
    }
}
