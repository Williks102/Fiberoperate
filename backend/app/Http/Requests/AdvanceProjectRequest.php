<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('advance', Project::query()->findOrFail($this->route('project')));
    }

    public function rules(): array
    {
        return [];
    }
}
