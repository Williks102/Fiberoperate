<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Study\RecordStudy;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordStudyRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerOrder;
use App\Models\Pco;
use App\Support\CodeNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Résultat d'une étude : réglages de la saisie, choix du PCO de rattachement, enregistrement. */
class StudyResultController extends Controller
{
    /** Écart entre poteaux et types comptés par défaut (le formulaire en déduit la distance proposée). */
    public function defaults(): JsonResponse
    {
        $this->authorize('viewAny', CustomerOrder::class);

        return response()->json(['data' => [
            'pole_spacing_m' => config('study.pole_spacing_m'),
            'counted_pole_types' => config('study.distance_pole_types'),
        ]]);
    }

    /** PCO que l'on peut choisir comme rattachement, tous sites confondus : une étude peut concerner n'importe quelle sous-plaque. */
    public function pcos(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerOrder::class);

        $term = $request->string('q')->trim()->value();
        $pcos = Pco::query()->with(['zone:id,code', 'jdv:id,code'])
            ->when($request->query('zone_id'), fn ($q, $id) => $q->where('zone_id', $id))
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('code_normalized', 'like', '%'.CodeNormalizer::pco($term).'%')
                    ->orWhere('code_normalized', 'like', '%'.CodeNormalizer::zone($term).'%'),
            ))
            ->orderBy('code_normalized')->limit(30)->get();

        return response()->json(['data' => $pcos->map(fn (Pco $p) => [
            'id' => $p->id, 'code' => $p->code, 'zone' => $p->zone?->code, 'splitter' => $p->jdv?->code,
        ])]);
    }

    public function store(RecordStudyRequest $request, RecordStudy $recordStudy, string $order): CustomerOrderResource
    {
        $model = CustomerOrder::query()->findOrFail($order);
        $recordStudy($model, $request->user(), $request->validated());

        return new CustomerOrderResource($model->fresh()->load(['zone:id,code', 'pco:id,code', 'history.author:id,name', 'interventions.technician:id,name', 'studies']));
    }
}
