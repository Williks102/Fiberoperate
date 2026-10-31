<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Study\PlanInstallation;
use App\Enums\MissionType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanInstallationRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/** Programmation de l'installation par le coordinateur : choix du technicien habilité et de la date. */
class InstallationPlanningController extends Controller
{
    /** Techniciens que l'on peut charger d'une installation : actifs et habilités « raccordement client » (id et nom seulement). */
    public function technicians(): JsonResponse
    {
        $this->authorize('plan', CustomerOrder::class);

        $users = User::query()
            ->where('role', Role::Technician->value)->where('is_active', true)
            ->whereHas('skills', fn ($q) => $q->where('mission_type', MissionType::CustomerConnection->value))
            ->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $users]);
    }

    public function store(PlanInstallationRequest $request, PlanInstallation $planInstallation, string $order): CustomerOrderResource
    {
        $model = CustomerOrder::query()->findOrFail($order);
        $planInstallation(
            $model,
            User::query()->findOrFail($request->validated('technician_id')),
            Carbon::createFromFormat('Y-m-d', $request->validated('planned_date')),
            $request->user(),
            $request->validated('observation'),
            $request->validated('reason'),
        );

        return new CustomerOrderResource($model->fresh()->load(['zone:id,code', 'pco:id,code', 'history.author:id,name', 'interventions.technician:id,name', 'studies']));
    }
}
