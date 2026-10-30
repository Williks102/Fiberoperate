<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plaque_id' => $this->plaque_id,
            'plaque' => $this->whenLoaded('plaque', fn () => $this->plaque?->only(['id', 'name'])),
            'phase' => $this->phase->value,
            'phase_label' => $this->phase->label(),
            'phase_changed_at' => $this->phase_changed_at?->toIso8601String(),
        ];
    }
}
