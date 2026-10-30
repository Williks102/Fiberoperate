<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Import\ImportFileException;
use App\Domain\Import\NetworkImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\NetworkImportRequest;
use Illuminate\Http\JsonResponse;

class NetworkImportController extends Controller
{
    /** Vérifie le fichier : même traitement que l'import, mais rien n'est écrit. */
    public function preview(NetworkImportRequest $request): JsonResponse
    {
        return $this->run($request, commit: false);
    }

    public function store(NetworkImportRequest $request): JsonResponse
    {
        return $this->run($request, commit: true);
    }

    private function run(NetworkImportRequest $request, bool $commit): JsonResponse
    {
        try {
            $table = NetworkImport::parse((string) file_get_contents($request->file('file')->getRealPath()));
        } catch (ImportFileException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'IMPORT_FILE_INVALID'], 422);
        }

        return response()->json(['data' => (new NetworkImport)->run($table, $commit)]);
    }
}
