<?php

namespace App\Http\Requests;

use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class ExcludeImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', ImportBatch::query()->where('type', 'customers')->findOrFail($this->route('batch')));
    }

    public function rules(): array
    {
        return ['excluded' => ['required', 'boolean']];
    }
}
