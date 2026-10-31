<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Study\ProcessFieldOperations;
use App\Http\Controllers\Controller;
use App\Http\Requests\PushFieldOperationsRequest;
use Illuminate\Http\JsonResponse;

/** Réception des opérations du terrain : une issue par opération (appliquée, doublon, conflit ou rejet), jamais d'échec en bloc. */
class FieldSyncController extends Controller
{
    public function push(PushFieldOperationsRequest $request, ProcessFieldOperations $process): JsonResponse
    {
        return response()->json(['data' => $process($request->user(), $request->validated('operations'))]);
    }
}
