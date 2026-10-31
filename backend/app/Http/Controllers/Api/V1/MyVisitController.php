<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InterventionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MyVisitResource;
use App\Models\Intervention;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Visites (études et installations) à faire par le technicien connecté, de la plus ancienne à la plus lointaine. */
class MyVisitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Intervention::class);

        $visits = Intervention::query()
            ->where('technician_id', $request->user()->getKey())
            ->where('status', InterventionStatus::Planned->value)
            ->with(['order.zone:id,code', 'order.pco:id,code'])
            ->orderBy('planned_date')->orderBy('created_at')
            ->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return MyVisitResource::collection($visits);
    }
}
