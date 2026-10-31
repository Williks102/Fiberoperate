<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_ref' => $this->customer_ref,
            'account_number' => $this->account_number,
            'ft_line' => $this->ft_line,
            'name' => $this->name,
            'phone_1' => $this->phone_1,
            'phone_2' => $this->phone_2,
            'address_raw' => $this->address_raw,
            'declared_latitude' => $this->declared_latitude,
            'declared_longitude' => $this->declared_longitude,
            'distance_m' => $this->distance_m,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'study_result' => $this->study_result?->value,
            'initial_study' => $this->initial_study?->value,
            'initial_study_raw' => $this->initial_study_raw,
            'registered_at' => $this->registered_at?->toDateString(),
            'sent_to_contractor_at' => $this->sent_to_contractor_at?->toDateString(),
            'operator_line_status' => $this->operator_line_status,
            'observation' => $this->observation,
            'zone' => $this->whenLoaded('zone', fn () => $this->zone?->only(['id', 'code'])),
            'pco' => $this->whenLoaded('pco', fn () => $this->pco?->only(['id', 'code'])),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn ($h) => [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'to_label' => $h->to_status->label(),
                'observation' => $h->observation,
                'author' => $h->relationLoaded('author') ? $h->author?->name : null,
                'at' => $h->created_at?->toIso8601String(),
            ])),
        ];
    }
}
