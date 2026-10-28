<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JdvResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'zone_id' => $this->zone_id, 'code' => $this->code];
    }
}
