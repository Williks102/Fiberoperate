<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Import\ImportFileException;
use App\Domain\Import\NetworkImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\NetworkImportRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/** Import du réseau d'un projet : la plaque du projet est la seule que le fichier peut contenir. */
class NetworkImportController extends Controller
{
    /** Vérifie le fichier : même traitement que l'import, mais rien n'est écrit. */
    public function preview(NetworkImportRequest $request, string $project): JsonResponse
    {
        return $this->run($request, $project, commit: false);
    }

    public function store(NetworkImportRequest $request, string $project): JsonResponse
    {
        return $this->run($request, $project, commit: true);
    }

    private function run(NetworkImportRequest $request, string $project, bool $commit): JsonResponse
    {
        try {
            $table = NetworkImport::parse((string) file_get_contents($request->file('file')->getRealPath()));
        } catch (ImportFileException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'IMPORT_FILE_INVALID'], 422);
        }

        return response()->json(['data' => (new NetworkImport(Project::query()->with('plaque')->findOrFail($project)->plaque))->run($table, $commit)]);
    }
}
