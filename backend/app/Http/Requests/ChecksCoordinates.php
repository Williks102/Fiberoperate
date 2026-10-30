<?php

namespace App\Http\Requests;

use App\Support\IvoryCoastBounds;
use Illuminate\Validation\Validator;

/** Coordonnées : dans la Côte d'Ivoire, jamais corrigées en silence (latitude et longitude inversées signalées). */
trait ChecksCoordinates
{
    private function checkCoordinates(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['latitude', 'longitude']) || ! $this->filled('latitude') || ! $this->filled('longitude')) {
            return;
        }

        $latitude = (float) $this->input('latitude');
        $longitude = (float) $this->input('longitude');

        if (IvoryCoastBounds::looksInverted($latitude, $longitude)) {
            $validator->errors()->add('latitude', 'La latitude et la longitude semblent inversées. Vérifiez les coordonnées.');
        } elseif (! IvoryCoastBounds::contains($latitude, $longitude)) {
            $validator->errors()->add('latitude', "Ces coordonnées ne sont pas en Côte d'Ivoire. Vérifiez-les.");
        }
    }
}
