<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockReasonController;
use App\Http\Controllers\Api\V1\JdvController;
use App\Http\Controllers\Api\V1\NetworkImportController;
use App\Http\Controllers\Api\V1\OltController;
use App\Http\Controllers\Api\V1\PcoController;
use App\Http\Controllers\Api\V1\PlaqueController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('teams', TeamController::class);
        Route::apiResource('olts', OltController::class);
        Route::apiResource('plaques', PlaqueController::class);
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('jdvs', JdvController::class);
        Route::apiResource('pcos', PcoController::class);
        Route::apiResource('projects', ProjectController::class)->only(['index', 'store']);
        Route::post('imports/network/preview', [NetworkImportController::class, 'preview']);
        Route::post('imports/network', [NetworkImportController::class, 'store']);
        Route::get('block-reasons', [BlockReasonController::class, 'index']);
    });
});
