<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('plan', CustomerOrder::class);
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'planned_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.required' => 'Choisissez le technicien qui réalisera l\'étude.',
            'planned_date.required' => 'Indiquez la date de l\'étude.',
            'planned_date.after_or_equal' => 'La date de l\'étude ne peut pas être dans le passé.',
        ];
    }
}
