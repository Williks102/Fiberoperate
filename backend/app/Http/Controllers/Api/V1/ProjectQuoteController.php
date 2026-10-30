<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Project\SaveQuoteLine;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveQuoteLineRequest;
use App\Http\Resources\QuoteLineResource;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\ProjectQuoteLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Devis d'un projet : lignes du catalogue de prix, quantités d'étude et réalisées, totaux. */
class ProjectQuoteController extends Controller
{
    public function show(Request $request, string $project): JsonResponse
    {
        $model = Project::query()->findOrFail($project);
        $this->authorize('viewQuote', $model);

        $lines = ProjectQuoteLine::query()->where('project_id', $model->id)->orderBy('kind', 'desc')->orderBy('created_at')->get();

        return response()->json(['data' => [
            'lines' => QuoteLineResource::collection($lines)->resolve($request),
            'totals' => [
                'study' => $lines->sum(fn (ProjectQuoteLine $l) => $l->studyTotal() ?? 0),
                'done' => $lines->sum(fn (ProjectQuoteLine $l) => $l->doneTotal() ?? 0),
                // Écart sur les seules lignes déjà réalisées : réalisé − prévu.
                'gap' => $lines->filter(fn (ProjectQuoteLine $l) => $l->done_quantity !== null)->sum(fn (ProjectQuoteLine $l) => ($l->doneTotal() ?? 0) - ($l->studyTotal() ?? 0)),
                'missing_price' => $lines->whereNull('unit_price')->count(),
            ],
            'can' => [
                'study' => $request->user()->can('editStudyQuote', $model),
                'done' => $request->user()->can('editDoneQuote', $model),
            ],
        ]]);
    }

    public function save(SaveQuoteLineRequest $request, string $project, SaveQuoteLine $save): JsonResponse
    {
        $line = $save->run(
            Project::query()->findOrFail($project),
            PriceItem::query()->findOrFail($request->input('price_item_id')),
            $request->filled('study_quantity') ? $request->integer('study_quantity') : null,
            $request->filled('done_quantity') ? $request->integer('done_quantity') : null,
        );

        return (new QuoteLineResource($line))->response();
    }

    /** Retire une ligne du devis (avant-projet seulement, tant que rien n'est réalisé). */
    public function destroy(Request $request, string $project, string $line): JsonResponse
    {
        $model = Project::query()->findOrFail($project);
        $this->authorize('editStudyQuote', $model);

        $quoteLine = ProjectQuoteLine::query()->where('project_id', $model->id)->findOrFail($line);
        $quoteLine->delete();

        return response()->json(null, 204);
    }
}
