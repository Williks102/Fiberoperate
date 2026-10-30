<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'plaque_id' => [
                'required', 'uuid',
                Rule::exists('plaques', 'id')->whereNull('deleted_at'),
                Rule::unique('projects', 'plaque_id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return ['plaque_id.unique' => 'Cette plaque a déjà un projet.'];
    }
}
