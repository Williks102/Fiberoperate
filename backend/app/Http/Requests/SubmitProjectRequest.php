<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class SubmitProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', Project::query()->findOrFail($this->route('project')));
    }

    public function rules(): array
    {
        return [];
    }
}
