<?php

namespace App\Http\Requests;

use App\Models\Jdv;
use App\Models\Pco;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création et modification d'un PCO par l'administrateur.
 *
 * Le statut, le libellé d'origine du fichier de suivi et le blocage ne se modifient pas ici : tout changement
 * de statut écrit une ligne d'historique (règle du cahier), et la liste des statuts normalisés reste à valider.
 */
class PcoRequest extends ManagesResource
{
    use ChecksCoordinates;

    protected function modelClass(): string
    {
        return Pco::class;
    }

    protected function routeKey(): string
    {
        return 'pco';
    }

    protected function targetPlaqueId(): ?string
    {
        $zoneId = Jdv::query()->whereKey($this->validUuid($this->input('jdv_id')))->value('zone_id');

        return $this->plaqueOfZone($zoneId);
    }

    public function rules(): array
    {
        return [
            'jdv_id' => [$this->req(), 'uuid', Rule::exists('jdvs', 'id')->whereNull('deleted_at')],
            'code' => [$this->req(), 'string', 'max:60'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'olt_slot' => ['nullable', 'string', 'max:255'],
            'olt_port' => ['nullable', 'string', 'max:255'],
            'fiber_strand' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
            // Hors de ce formulaire : la zone se déduit du splitter, le statut passe par son historique.
            'zone_id' => ['prohibited'],
            'status' => ['prohibited'],
            'status_raw' => ['prohibited'],
            'is_blocked' => ['prohibited'],
            'block_reason_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->checkSplitterStaysInZone($validator);
            $this->checkCoordinates($validator);
        });
    }

    /** Un PCO peut changer de splitter, mais seulement dans sa sous-plaque : sa zone se déduit de son splitter. */
    private function checkSplitterStaysInZone(Validator $validator): void
    {
        $id = $this->ignoreId();
        if ($id === null || ! $this->filled('jdv_id') || $validator->errors()->has('jdv_id')) {
            return;
        }

        $zoneId = Pco::query()->whereKey($id)->value('zone_id');
        $newZoneId = Jdv::query()->whereKey($this->input('jdv_id'))->value('zone_id');

        if ($zoneId !== null && $newZoneId !== $zoneId) {
            $validator->errors()->add('jdv_id', 'Le splitter doit appartenir à la même sous-plaque que le PCO.');
        }
    }
}
