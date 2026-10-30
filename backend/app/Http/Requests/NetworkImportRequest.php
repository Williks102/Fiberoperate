<?php

namespace App\Http\Requests;

use App\Models\Plaque;
use Illuminate\Foundation\Http\FormRequest;

/** Import du réseau : réservé à ceux qui peuvent créer des plaques (l'administrateur). */
class NetworkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Plaque::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier CSV.',
            'file.max' => 'Le fichier dépasse 2 Mo : découpez-le en plusieurs fichiers.',
            'file.mimes' => 'Le fichier doit être au format CSV (Excel : « Enregistrer sous » puis « CSV »).',
        ];
    }
}
