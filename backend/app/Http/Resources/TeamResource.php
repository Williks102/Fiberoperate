<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'leader_id' => $this->leader_id,
            'coordinator_id' => $this->coordinator_id,
            'member_ids' => $this->whenLoaded('members', fn () => $this->members->pluck('id')),
        ];
    }
}
