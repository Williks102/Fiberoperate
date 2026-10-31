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
            // Caractéristiques du réseau du projet (fiche seulement).
            'network' => $this->whenLoaded('plaque', fn () => $this->plaque?->relationLoaded('zones') ? [
                'olt' => $this->plaque->olt?->only(['id', 'name']),
                'supervisor' => $this->plaque->supervisor?->only(['id', 'name']),
                'zones' => $this->plaque->zones->map(fn ($zone) => [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'status' => $zone->status?->value,
                    'sro' => $zone->sro?->code,
                    'splitters' => $zone->jdvs_count,
                    'pcos' => $zone->pcos_count,
                    // Prévu (le plan) et réel (l'avancement) : PCO par statut et PCO dont la position a été relevée.
                    'progress' => [
                        'to_pull' => $zone->pcos_to_pull_count,
                        'pulled' => $zone->pcos_pulled_count,
                        'spliced' => $zone->pcos_spliced_count,
                        'activated' => $zone->pcos_activated_count,
                        'measured' => $zone->pcos_measured_count,
                    ],
                ])->values(),
            ] : null),
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'phase' => $this->phase->value,
            // Actions du circuit de validation permises à l'utilisateur dans la phase courante (fiche seulement).
            'actions' => $this->whenLoaded('history', fn () => [
                'submit' => $request->user()->can('submit', $this->resource),
                'decide' => $request->user()->can('decide', $this->resource),
                'advance' => $request->user()->can('advance', $this->resource),
                'advance_to' => $this->phase->nextOnAdvance()?->label(),
            ]),
            'phase_label' => $this->phase->label(),
            'phase_changed_at' => $this->phase_changed_at?->toIso8601String(),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn ($line) => [
                'id' => $line->id,
                'from_phase' => $line->from_phase?->value,
                'from_phase_label' => $line->from_phase?->label(),
                'to_phase' => $line->to_phase->value,
                'to_phase_label' => $line->to_phase->label(),
                'decision' => $line->decision,
                'reason' => $line->reason,
                'author' => $line->author?->only(['id', 'name']),
                'created_at' => $line->created_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
