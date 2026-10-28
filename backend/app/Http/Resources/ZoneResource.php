<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plaque_id' => $this->plaque_id,
            'code' => $this->code,
            'name' => $this->name,
            'vendor' => $this->vendor?->value,
            'access_point_label' => $this->accessPointLabel(),
            'status' => $this->status?->value,
        ];
    }
}
