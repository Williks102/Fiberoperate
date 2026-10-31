<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;

/** Box d'un client : numéro de série de l'ONT et numéro fixe, uniques (une box et une ligne n'appartiennent qu'à un client). */
class AssignBoxRequest extends FormRequest
{
    use ChecksBox;

    public function authorize(): bool
    {
        return $this->user()->can('assignBox', CustomerOrder::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBox();
    }

    public function rules(): array
    {
        return $this->boxRules();
    }

    public function messages(): array
    {
        return $this->boxMessages();
    }
}
