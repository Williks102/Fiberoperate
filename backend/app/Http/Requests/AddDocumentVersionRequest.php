<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Nouvelle version d'un document de projet : type autorisé pour l'utilisateur, fichier de 2 Mo au plus, extension et contenu cohérents. */
class AddDocumentVersionRequest extends FormRequest
{
    public const EXTENSIONS = ['dwg', 'dxf', 'kmz', 'kml', 'pdf', 'xlsx', 'xls', 'csv', 'png', 'jpg', 'jpeg'];

    /** Début de fichier attendu pour les formats dont on peut vérifier la signature. */
    private const SIGNATURES = ['dwg' => 'AC', 'kmz' => 'PK', 'xlsx' => 'PK', 'pdf' => '%PDF', 'png' => "\x89PNG"];

    public function authorize(): bool
    {
        $type = DocumentType::tryFrom((string) $this->input('type'));

        return $this->user()->can('uploadDocument', [Project::query()->findOrFail($this->route('project')), $type]);
    }

    public function rules(): array
    {
        $type = DocumentType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'zone_id' => [
                $type?->needsZone() ? 'required' : 'prohibited', 'uuid',
                Rule::exists('zones', 'id')->whereNull('deleted_at')->where('plaque_id', Project::query()->whereKey($this->route('project'))->value('plaque_id')),
            ],
            'file' => ['required', 'file', 'max:2048'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier.',
            'file.max' => 'Le fichier dépasse 2 Mo.',
            'zone_id.required' => 'Choisissez la sous-plaque de ce plan.',
            'zone_id.prohibited' => 'Seul un plan de sous-plaque se rattache à une sous-plaque.',
            'zone_id.exists' => "Cette sous-plaque n'appartient pas à la plaque du projet.",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('file');
            if ($file === null || $validator->errors()->has('file')) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (! in_array($extension, self::EXTENSIONS, true)) {
                $validator->errors()->add('file', 'Format non accepté (.'.$extension.'). Formats : '.implode(', ', self::EXTENSIONS).'.');

                return;
            }

            $signature = self::SIGNATURES[$extension] ?? null;
            if ($signature !== null && ! str_starts_with((string) file_get_contents($file->getRealPath()), $signature)) {
                $validator->errors()->add('file', "Le contenu du fichier ne correspond pas à son extension (.{$extension}).");
            }
        });
    }
}
