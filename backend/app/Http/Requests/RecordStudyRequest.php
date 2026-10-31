<?php

namespace App\Http\Requests;

use App\Domain\Study\ResultRules;
use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
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
        return ResultRules::study();
    }

    public function messages(): array
    {
        return ResultRules::studyMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkCoordinates($validator));
    }
}
