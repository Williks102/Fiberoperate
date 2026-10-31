<?php

namespace App\Domain\Project;

use App\Enums\DocumentType;
use App\Models\Project;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Ajoute une version d'un document : numérotée à la suite des précédentes, sous verrou du projet. */
class AddDocumentVersion
{
    public function run(Project $project, DocumentType $type, ?string $zoneId, string $fileName, string $bytes, ?string $comment, User $by): ProjectDocumentVersion
    {
        return DB::transaction(function () use ($project, $type, $zoneId, $fileName, $bytes, $comment, $by) {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $last = ProjectDocumentVersion::query()
                ->where('project_id', $project->id)->where('type', $type->value)
                ->when($zoneId === null, fn ($q) => $q->whereNull('zone_id'), fn ($q) => $q->where('zone_id', $zoneId))
                ->max('version');

            return ProjectDocumentVersion::create([
                'project_id' => $project->id,
                'type' => $type,
                'zone_id' => $zoneId,
                'version' => ((int) $last) + 1,
                'file_name' => $fileName,
                'size' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'content' => base64_encode($bytes),
                'comment' => $comment,
                'uploaded_by' => $by->id,
            ]);
        });
    }
}
