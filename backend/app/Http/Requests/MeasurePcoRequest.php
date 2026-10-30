<?php

namespace App\Http\Requests;

use App\Models\Pco;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Relevé de la position mesurée d'un PCO : dans la Côte d'Ivoire, jamais corrigée en silence. */
class MeasurePcoRequest extends FormRequest
{
    use ChecksCoordinates;

    public function authorize(): bool
    {
        return $this->user()->can('measure', Pco::class);
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkCoordinates($validator));
    }
}
