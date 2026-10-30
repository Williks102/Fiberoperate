<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class DecideProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', Project::query()->findOrFail($this->route('project')));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required_if' => 'Le motif du refus est obligatoire.'];
    }
}
