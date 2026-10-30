<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockReasonController;
use App\Http\Controllers\Api\V1\JdvController;
use App\Http\Controllers\Api\V1\NetworkImportController;
use App\Http\Controllers\Api\V1\OltController;
use App\Http\Controllers\Api\V1\PcoController;
use App\Http\Controllers\Api\V1\PlaqueController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SroController;
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
        // Une plaque naît avec son projet (POST /projects) : pas de création isolée.
        Route::apiResource('plaques', PlaqueController::class)->except(['store']);
        Route::post('zones/{zone}/generate', [ZoneController::class, 'generate']);
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('sros', SroController::class);
        Route::apiResource('jdvs', JdvController::class);
        Route::put('pcos/{pco}/measured-position', [PcoController::class, 'measure']);
        Route::apiResource('pcos', PcoController::class);
        Route::apiResource('projects', ProjectController::class)->only(['index', 'show', 'store']);
        Route::post('projects/{project}/submit', [ProjectController::class, 'submit']);
        Route::post('projects/{project}/decision', [ProjectController::class, 'decision']);
        Route::post('projects/{project}/imports/network/preview', [NetworkImportController::class, 'preview']);
        Route::post('projects/{project}/imports/network', [NetworkImportController::class, 'store']);
        Route::get('block-reasons', [BlockReasonController::class, 'index']);
    });
});
