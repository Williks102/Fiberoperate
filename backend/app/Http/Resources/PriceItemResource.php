<?php

namespace App\Http\Resources;

use App\Models\PriceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'kind_label' => PriceItem::KINDS[$this->kind] ?? $this->kind,
            'section' => $this->section,
            'ref' => $this->ref,
            'designation' => $this->designation,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
        ];
    }
}
