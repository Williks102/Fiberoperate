<?php

namespace App\Http\Requests;

use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class AnalyseCustomerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ImportBatch::class);
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:4096', 'mimes:csv,txt']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier CSV.',
            'file.max' => 'Le fichier dépasse 4 Mo : découpez-le en plusieurs fichiers.',
            'file.mimes' => 'Le fichier doit être au format CSV (Excel : « Enregistrer sous » puis « CSV »).',
        ];
    }
}
