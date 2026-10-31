<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Box d'un client : numéro de série de l'ONT et numéro fixe, uniques (une box et une ligne n'appartiennent qu'à un client). */
class AssignBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignBox', CustomerOrder::class);
    }

    protected function prepareForValidation(): void
    {
        $serial = mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('ont_serial')) ?? '');
        $line = preg_replace('/[\s.\-]+/', '', (string) $this->input('fixed_line'));

        $this->merge(['ont_serial' => $serial === '' ? null : $serial, 'fixed_line' => $line === '' ? null : $line]);
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $unique = fn (string $column) => Rule::unique('customer_orders', $column)->ignore($order, 'id')->whereNull('deleted_at');

        return [
            'ont_serial' => ['nullable', 'required_without:fixed_line', 'string', 'max:60', 'regex:/^[A-Z0-9\-_]+$/', $unique('ont_serial')],
            'fixed_line' => ['nullable', 'required_without:ont_serial', 'regex:/^\d{8,12}$/', $unique('fixed_line')],
        ];
    }

    public function messages(): array
    {
        return [
            'ont_serial.required_without' => 'Indiquez le numéro de série de l\'ONT ou le numéro fixe.',
            'fixed_line.required_without' => 'Indiquez le numéro fixe ou le numéro de série de l\'ONT.',
            'ont_serial.regex' => 'Le numéro de série ne contient que des lettres, chiffres, tirets et soulignés.',
            'ont_serial.unique' => 'Ce numéro de série est déjà attribué à un autre client.',
            'fixed_line.regex' => 'Le numéro fixe doit avoir de 8 à 12 chiffres.',
            'fixed_line.unique' => 'Ce numéro fixe est déjà attribué à un autre client.',
        ];
    }
}
