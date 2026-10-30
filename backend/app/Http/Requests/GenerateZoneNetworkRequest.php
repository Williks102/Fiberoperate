<?php

namespace App\Http\Requests;

use App\Models\Sro;
use App\Models\Zone;
use App\Rules\UniqueSroCode;

class GenerateZoneNetworkRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return Zone::class;
    }

    protected function routeKey(): string
    {
        return 'zone';
    }

    public function rules(): array
    {
        $hasSro = Sro::query()->where('zone_id', $this->route('zone'))->exists();

        return [
            // Nombre libre (32 et 64 sont seulement suggérés) ; 8 PCO par splitter par défaut, modifiable.
            'pco_count' => ['required', 'integer', 'min:1', 'max:1024'],
            'splitter_size' => ['sometimes', 'integer', 'min:1', 'max:64'],
            // Le numéro de la SRO est donné par l'opérateur : saisi, jamais déduit.
            'sro_code' => [$hasSro ? 'nullable' : 'required', 'string', 'max:40', new UniqueSroCode],
        ];
    }
}
