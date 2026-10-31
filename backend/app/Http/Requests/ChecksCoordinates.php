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

        $problem = IvoryCoastBounds::problem((float) $this->input('latitude'), (float) $this->input('longitude'));
        if ($problem !== null) {
            $validator->errors()->add('latitude', $problem);
        }
    }
}
