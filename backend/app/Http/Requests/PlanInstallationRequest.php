<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanInstallationRequest extends FormRequest
{
    use ChecksBox;

    public function authorize(): bool
    {
        return $this->user()->can('plan', CustomerOrder::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBox();
    }

    public function rules(): array
    {
        return $this->boxRules() + [
            'technician_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'planned_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return $this->boxMessages() + [
            'technician_id.required' => "Choisissez le technicien qui fera l'installation.",
            'planned_date.required' => "Indiquez la date de l'installation.",
            'planned_date.after_or_equal' => "La date de l'installation ne peut pas être dans le passé.",
        ];
    }
}
