<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Import\CustomerImportAnalyzer;
use App\Domain\Import\ImportFileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyseCustomerImportRequest;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Import des clients, premier temps : analyse du fichier, puis revue des lignes (rien n'est créé avant la validation). */
class CustomerImportController extends Controller
{
    public function store(AnalyseCustomerImportRequest $request, CustomerImportAnalyzer $analyzer): JsonResponse
    {
        $file = $request->file('file');

        try {
            $table = CustomerImportAnalyzer::parse((string) file_get_contents($file->getRealPath()));
        } catch (ImportFileException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'IMPORT_FILE_INVALID'], 422);
        }

        $batch = $analyzer->analyse($table, $request->user(), $file->getClientOriginalName());

        return response()->json(['data' => $this->presentBatch($batch)], 201);
    }

    /** Le lot et une page de ses lignes, filtrable par statut (ok, to_fix, rejected). */
    public function show(Request $request, string $batch): JsonResponse
    {
        $model = ImportBatch::query()->where('type', 'customers')->findOrFail($batch);
        $this->authorize('view', $model);

        $rows = $model->rows()
            ->when(in_array($request->query('status'), ['ok', 'to_fix', 'rejected'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json([
            'data' => $this->presentBatch($model) + [
                'rows' => $rows->getCollection()->map(fn (ImportRow $row) => [
                    'id' => $row->id,
                    'line' => $row->line,
                    'status' => $row->status,
                    'action' => $row->action,
                    'excluded' => $row->excluded,
                    'customer_ref' => $row->data['customer_ref'] ?? null,
                    'name' => $row->data['name'] ?? null,
                    'anomalies' => $row->anomalies,
                ])->values(),
            ],
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentBatch(ImportBatch $batch): array
    {
        return ['id' => $batch->id, 'file_name' => $batch->file_name, 'status' => $batch->status, 'counts' => $batch->counts, 'created_at' => $batch->created_at?->toIso8601String()];
    }
}
