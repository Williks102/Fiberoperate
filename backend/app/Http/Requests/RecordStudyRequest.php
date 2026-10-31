<?php

namespace App\Http\Requests;

use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Résultat d'une étude : poteaux par type, distance, PCO de rattachement (si faisable), motif (si non faisable). */
class RecordStudyRequest extends FormRequest
{
    use ChecksCoordinates;

    public function authorize(): bool
    {
        return $this->user()->can('recordStudy', CustomerOrder::query()->findOrFail($this->route('order')));
    }

    public function rules(): array
    {
        $pole = ['nullable', 'integer', 'min:0', 'max:500'];

        return [
            'result' => ['required', Rule::enum(StudyResult::class)],
            'pco_id' => ['required_if:result,feasible', 'nullable', 'uuid', Rule::exists('pcos', 'id')->whereNull('deleted_at')],
            'concrete_poles_existing' => $pole,
            'concrete_poles_to_install' => $pole,
            'metal_poles_existing' => $pole,
            'metal_poles_to_install' => $pole,
            'distance_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'problem' => ['required_if:result,not_feasible', 'nullable', 'string', 'max:1000'],
            'field_comments' => ['nullable', 'string', 'max:2000'],
            'internal_comments' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'result.required' => "Indiquez le résultat de l'étude.",
            'pco_id.required_if' => 'Choisissez le PCO de rattachement : le client est raccordable.',
            'problem.required_if' => 'Indiquez le problème rencontré : le client est non raccordable.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkCoordinates($validator));
    }
}
