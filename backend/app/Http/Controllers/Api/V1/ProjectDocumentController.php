<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Project\AddDocumentVersion;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddDocumentVersionRequest;
use App\Models\Project;
use App\Models\ProjectDocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Documents d'un projet : toutes les versions (sans le contenu), ajout d'une version, téléchargement. */
class ProjectDocumentController extends Controller
{
    public function index(Request $request, string $project): JsonResponse
    {
        $model = Project::query()->findOrFail($project);
        $this->authorize('viewDocuments', $model);

        $versions = ProjectDocumentVersion::query()->where('project_id', $model->id)->with(['zone:id,code', 'author:id,name'])
            ->orderBy('type')->orderBy('zone_id')->orderByDesc('version')->get();

        return response()->json(['data' => [
            'versions' => $versions->map(fn (ProjectDocumentVersion $v) => $this->present($v))->values(),
            'uploadable' => collect(DocumentType::cases())
                ->filter(fn (DocumentType $type) => $request->user()->can('uploadDocument', [$model, $type]))
                ->map(fn (DocumentType $type) => ['type' => $type->value, 'label' => $type->label(), 'needs_zone' => $type->needsZone()])->values(),
        ]]);
    }

    public function store(AddDocumentVersionRequest $request, string $project, AddDocumentVersion $add): JsonResponse
    {
        $file = $request->file('file');
        $version = $add->run(
            Project::query()->findOrFail($project),
            DocumentType::from($request->input('type')),
            $request->input('zone_id'),
            $this->safeName($file->getClientOriginalName()),
            (string) file_get_contents($file->getRealPath()),
            $request->input('comment'),
            $request->user(),
        );

        return response()->json(['data' => $this->present($version->load(['zone:id,code', 'author:id,name']))], 201);
    }

    public function download(Request $request, string $project, string $version): StreamedResponse
    {
        $model = Project::query()->findOrFail($project);
        $this->authorize('viewDocuments', $model);

        $document = ProjectDocumentVersion::query()->where('project_id', $model->id)->findOrFail($version);

        return response()->streamDownload(
            fn () => print ($document->bytes()),
            $document->file_name,
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Content-Length' => (string) $document->size],
        );
    }

    /** @return array<string, mixed> */
    private function present(ProjectDocumentVersion $v): array
    {
        return [
            'id' => $v->id,
            'type' => $v->type->value,
            'type_label' => $v->type->label(),
            'zone' => $v->zone?->only(['id', 'code']),
            'version' => $v->version,
            'file_name' => $v->file_name,
            'size' => $v->size,
            'comment' => $v->comment,
            'author' => $v->author?->only(['id', 'name']),
            'created_at' => $v->created_at?->toIso8601String(),
        ];
    }

    /** Nom de fichier sans chemin ni caractère de contrôle. */
    private function safeName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '_', basename($name)));

        return $clean === '' ? 'document' : mb_substr($clean, 0, 200);
    }
}
