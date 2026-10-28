<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'manager_id' => $this->manager_id,
            'is_active' => $this->is_active,
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($s) => $s->mission_type->value)->values()),
        ];
    }
}
