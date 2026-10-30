<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Import\ImportFileException;
use App\Domain\Import\PriceCatalogImport;
use App\Http\Requests\PriceCatalogImportRequest;
use App\Http\Resources\PriceItemResource;
use App\Models\PriceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceItemController extends ApiResourceController
{
    protected function modelClass(): string
    {
        return PriceItem::class;
    }

    protected function resourceClass(): string
    {
        return PriceItemResource::class;
    }

    protected function orderBy(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    protected function filters(Request $request, Builder $query): void
    {
        $query->where('is_active', true)
            ->when(in_array($request->query('kind'), array_keys(PriceItem::KINDS), true), fn ($q) => $q->where('kind', $request->query('kind')))
            ->when($request->string('section')->trim()->value(), fn ($q, $section) => $q->where('section', $section))
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('designation', 'ilike', "%{$term}%")->orWhere('ref', 'ilike', "%{$term}%"),
            ));
    }

    /** Rubriques du catalogue, dans l'ordre du bordereau. */
    public function sections(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PriceItem::class);

        $sections = PriceItem::query()->where('is_active', true)->selectRaw('section, kind, min(sort_order) as first_order')
            ->groupBy('section', 'kind')->orderBy('first_order')->get()
            ->map(fn ($row) => ['section' => $row->section, 'kind' => $row->kind])->values();

        return response()->json(['data' => $sections]);
    }

    /** Vérifie le fichier : même traitement que l'import, mais rien n'est écrit. */
    public function previewImport(PriceCatalogImportRequest $request): JsonResponse
    {
        return $this->runImport($request, commit: false);
    }

    public function import(PriceCatalogImportRequest $request): JsonResponse
    {
        return $this->runImport($request, commit: true);
    }

    private function runImport(PriceCatalogImportRequest $request, bool $commit): JsonResponse
    {
        try {
            $table = PriceCatalogImport::parse((string) file_get_contents($request->file('file')->getRealPath()));
        } catch (ImportFileException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'IMPORT_FILE_INVALID'], 422);
        }

        return response()->json(['data' => (new PriceCatalogImport)->run($table, $commit)]);
    }
}
