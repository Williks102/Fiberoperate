<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Box d'un client (n° de série de l'ONT et numéro fixe, tous deux obligatoires) : normalisation et règles communes à l'attribution et à la programmation. */
trait ChecksBox
{
    /** Majuscules sans espaces pour le n° de série ; chiffres seuls pour le numéro fixe ; vide = absent. */
    private function normalizeBox(): void
    {
        $serial = mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('ont_serial')) ?? '');
        $line = preg_replace('/[\s.\-]+/', '', (string) $this->input('fixed_line'));

        $this->merge(['ont_serial' => $serial === '' ? null : $serial, 'fixed_line' => $line === '' ? null : $line]);
    }

    /** @return array<string, list<mixed>> */
    private function boxRules(): array
    {
        $order = $this->route('order');
        $unique = fn (string $column) => Rule::unique('customer_orders', $column)->ignore($order, 'id')->whereNull('deleted_at');

        return [
            'ont_serial' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9\-_]+$/', $unique('ont_serial')],
            'fixed_line' => ['required', 'regex:/^\d{8,12}$/', $unique('fixed_line')],
        ];
    }

    /** @return array<string, string> */
    private function boxMessages(): array
    {
        return [
            'ont_serial.required' => 'Indiquez le numéro de série de l\'ONT : la box est obligatoire.',
            'fixed_line.required' => 'Indiquez le numéro fixe : il est obligatoire.',
            'ont_serial.regex' => 'Le numéro de série ne contient que des lettres, chiffres, tirets et soulignés.',
            'ont_serial.unique' => 'Ce numéro de série est déjà attribué à un autre client.',
            'fixed_line.regex' => 'Le numéro fixe doit avoir de 8 à 12 chiffres.',
            'fixed_line.unique' => 'Ce numéro fixe est déjà attribué à un autre client.',
        ];
    }
}
