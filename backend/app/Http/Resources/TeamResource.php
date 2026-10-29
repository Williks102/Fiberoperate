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
            'leader' => $this->whenLoaded('leader', fn () => $this->leader?->only(['id', 'name'])),
            'coordinator' => $this->whenLoaded('coordinator', fn () => $this->coordinator?->only(['id', 'name'])),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => $m->only(['id', 'name']))->values()),
            'member_ids' => $this->whenLoaded('members', fn () => $this->members->pluck('id')),
        ];
    }
}
