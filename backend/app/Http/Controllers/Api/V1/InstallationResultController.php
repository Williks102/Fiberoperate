<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Study\RecordInstallation;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordInstallationRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerOrder;
use App\Models\OutcomeReason;
use Illuminate\Http\JsonResponse;

/** Issue d'une installation (installé, reporté, en échec) et motifs de report ou d'échec. */
class InstallationResultController extends Controller
{
    /** Motifs actifs, proposés dans le formulaire. */
    public function reasons(): JsonResponse
    {
        $this->authorize('viewAny', CustomerOrder::class);

        return response()->json(['data' => OutcomeReason::query()->where('is_active', true)->orderBy('label')->get(['id', 'code', 'label'])]);
    }

    public function store(RecordInstallationRequest $request, RecordInstallation $recordInstallation, string $order): CustomerOrderResource
    {
        $model = CustomerOrder::query()->findOrFail($order);
        $recordInstallation($model, $request->user(), $request->validated());

        return new CustomerOrderResource($model->fresh()->load(['zone:id,code', 'pco:id,code', 'history.author:id,name', 'interventions.technician:id,name', 'interventions.reason', 'studies']));
    }
}
