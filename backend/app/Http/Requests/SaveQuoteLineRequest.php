<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Ajout ou modification d'une ligne du devis : chaque quantité n'est modifiable qu'à la bonne phase du projet. */
class SaveQuoteLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::query()->findOrFail($this->route('project'));

        return $this->user()->can('writeQuote', $project);
    }

    public function rules(): array
    {
        return [
            'price_item_id' => ['required', 'uuid', Rule::exists('price_items', 'id')->whereNull('deleted_at')],
            'study_quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'done_quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $project = Project::query()->findOrFail($this->route('project'));
            $user = $this->user();

            if ($this->filled('study_quantity') && ! $user->can('editStudyQuote', $project)) {
                $validator->errors()->add('study_quantity', 'Les quantités d\'étude ne sont modifiables que par le BE, tant que l\'avant-projet n\'est pas envoyé en validation.');
            }
            if ($this->filled('done_quantity') && ! $user->can('editDoneQuote', $project)) {
                $validator->errors()->add('done_quantity', 'Les quantités réalisées se saisissent pendant et après le déploiement, par le BE.');
            }
            if (! $this->filled('study_quantity') && ! $this->filled('done_quantity')) {
                $validator->errors()->add('study_quantity', 'Indiquez une quantité.');
            }
        });
    }
}
