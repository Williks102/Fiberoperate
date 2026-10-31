<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Issue d'une installation : installé (n° de série ONT et ligne fixe facultatifs), reporté ou en échec (motif obligatoire). */
class RecordInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordInstallation', CustomerOrder::query()->findOrFail($this->route('order')));
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(['installed', 'postponed', 'failed'])],
            'reason_id' => ['required_if:result,postponed,failed', 'nullable', 'uuid', Rule::exists('outcome_reasons', 'id')->where('is_active', true)],
            'ont_serial' => ['nullable', 'string', 'max:60'],
            'fixed_line' => ['nullable', 'string', 'max:30'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'result.required' => "Indiquez le résultat de l'installation.",
            'reason_id.required_if' => 'Choisissez le motif du report ou de l\'échec.',
        ];
    }
}
