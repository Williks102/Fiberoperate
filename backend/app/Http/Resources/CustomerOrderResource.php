<?php

namespace App\Http\Resources;

use App\Enums\Role;
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
            'stage' => $this->stage(),
            'study_result' => $this->study_result?->value,
            'initial_study' => $this->initial_study?->value,
            'initial_study_raw' => $this->initial_study_raw,
            'registered_at' => $this->registered_at?->toDateString(),
            'sent_to_contractor_at' => $this->sent_to_contractor_at?->toDateString(),
            'operator_line_status' => $this->operator_line_status,
            'observation' => $this->observation,
            'zone' => $this->whenLoaded('zone', fn () => $this->zone?->only(['id', 'code'])),
            'pco' => $this->whenLoaded('pco', fn () => $this->pco?->only(['id', 'code'])),
            'interventions' => $this->whenLoaded('interventions', fn () => $this->interventions->map(fn ($i) => [
                'id' => $i->id,
                'type' => $i->type->value,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'planned_date' => $i->planned_date->toDateString(),
                'technician_id' => $i->technician_id,
                'technician' => $i->relationLoaded('technician') ? $i->technician?->name : null,
                'observation' => $i->observation,
            ])),
            'studies' => $this->whenLoaded('studies', fn () => $this->studies->map(fn ($st) => collect([
                'id' => $st->id,
                'result' => $st->result->value,
                'result_label' => $st->result->label(),
                'concrete_poles_existing' => $st->concrete_poles_existing,
                'concrete_poles_to_install' => $st->concrete_poles_to_install,
                'metal_poles_existing' => $st->metal_poles_existing,
                'metal_poles_to_install' => $st->metal_poles_to_install,
                'distance_m' => $st->distance_m,
                'problem' => $st->problem,
                'field_comments' => $st->field_comments,
                'internal_comments' => $st->internal_comments,
                'at' => $st->created_at?->toIso8601String(),
            ])->when(! $request->user()->hasRole(Role::Admin, Role::Dg, Role::Dt, Role::Coordinator), fn ($c) => $c->except('internal_comments'))->all())),
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
