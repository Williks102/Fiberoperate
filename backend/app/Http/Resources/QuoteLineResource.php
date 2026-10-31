<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $study = $this->studyTotal();
        $done = $this->doneTotal();

        return [
            'id' => $this->id,
            'price_item_id' => $this->price_item_id,
            'kind' => $this->kind,
            'section' => $this->section,
            'ref' => $this->ref,
            'designation' => $this->designation,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'study_quantity' => $this->study_quantity,
            'study_total' => $study,
            'done_quantity' => $this->done_quantity,
            'done_total' => $done,
            // Écart = réalisé − prévu, quand les deux montants existent.
            'gap_total' => $study === null || $done === null ? null : $done - $study,
        ];
    }
}
