<?php

namespace App\Http\Requests;

use App\Enums\ProjectPhase;
use App\Models\Project;
use App\Rules\UniqueSroCode;
use App\Support\CodeNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Deux façons d'ouvrir un projet : sur une plaque existante qui n'en a pas (`plaque_id`), ou en la créant avec lui
 * (`plaque_name`, OLT choisi `olt_id` ou créé `olt_name`, sous-plaques `zones` dont la génération du réseau est facultative).
 */
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
                'required_without:plaque_name', 'prohibits:plaque_name,olt_id,olt_name,zones', 'uuid',
                Rule::exists('plaques', 'id')->whereNull('deleted_at'),
                // Une plaque a un seul projet en cours : un nouveau (désaturation) n'est possible qu'une fois le précédent en exploitation.
                Rule::unique('projects', 'plaque_id')->whereNull('deleted_at')->where(fn ($query) => $query->where('phase', '<>', ProjectPhase::InOperation->value)),
            ],
            'plaque_name' => ['required_without:plaque_id', 'string', 'max:120', Rule::unique('plaques', 'name')->whereNull('deleted_at')],
            'olt_id' => ['nullable', 'prohibits:olt_name', 'uuid', Rule::exists('olts', 'id')->whereNull('deleted_at')],
            'olt_name' => ['nullable', 'string', 'max:120', Rule::unique('olts', 'name')->whereNull('deleted_at')],
            'zones' => ['nullable', 'array', 'max:100'],
            'zones.*.code' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'zones.*.pco_count' => ['nullable', 'integer', 'min:1', 'max:1024'],
            'zones.*.sro_code' => ['nullable', 'required_with:zones.*.pco_count', 'string', 'max:40', 'distinct:ignore_case', new UniqueSroCode],
        ];
    }

    public function messages(): array
    {
        return [
            'plaque_id.unique' => 'Cette plaque a déjà un projet en cours : le suivant (désaturation) s\'ouvre une fois le précédent en exploitation.',
            'plaque_name.unique' => 'Une plaque porte déjà ce nom.',
            'olt_name.unique' => 'Un OLT porte déjà ce nom : choisissez-le dans la liste.',
            'zones.*.code.distinct' => 'Cette sous-plaque est saisie deux fois.',
            'zones.*.sro_code.distinct' => 'Ce numéro de SRO est saisi deux fois.',
            'zones.*.sro_code.required_with' => 'Le numéro de la SRO est obligatoire pour générer le réseau.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // « ABB-01 » et « ABB01 » sont la même sous-plaque.
        $validator->after(function (Validator $validator) {
            $seen = [];
            foreach ((array) $this->input('zones', []) as $i => $zone) {
                $key = CodeNormalizer::zone($zone['code'] ?? '');
                if ($key !== '' && isset($seen[$key]) && ! $validator->errors()->has("zones.$i.code")) {
                    $validator->errors()->add("zones.$i.code", 'Cette sous-plaque est saisie deux fois.');
                }
                $seen[$key] = true;
            }
        });
    }
}
