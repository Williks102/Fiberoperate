<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockReasonController;
use App\Http\Controllers\Api\V1\CustomerImportController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\FieldSyncController;
use App\Http\Controllers\Api\V1\InstallationPlanningController;
use App\Http\Controllers\Api\V1\InstallationResultController;
use App\Http\Controllers\Api\V1\JdvController;
use App\Http\Controllers\Api\V1\MyVisitController;
use App\Http\Controllers\Api\V1\NetworkImportController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OltController;
use App\Http\Controllers\Api\V1\PcoController;
use App\Http\Controllers\Api\V1\PlaqueController;
use App\Http\Controllers\Api\V1\PriceItemController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectDocumentController;
use App\Http\Controllers\Api\V1\ProjectQuoteController;
use App\Http\Controllers\Api\V1\SroController;
use App\Http\Controllers\Api\V1\StudyPlanningController;
use App\Http\Controllers\Api\V1\StudyResultController;
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
        Route::get('projects/{project}/documents', [ProjectDocumentController::class, 'index']);
        Route::post('projects/{project}/documents', [ProjectDocumentController::class, 'store']);
        Route::get('projects/{project}/documents/{version}/download', [ProjectDocumentController::class, 'download']);
        Route::get('projects/{project}/quote', [ProjectQuoteController::class, 'show']);
        Route::put('projects/{project}/quote/lines', [ProjectQuoteController::class, 'save']);
        Route::delete('projects/{project}/quote/lines/{line}', [ProjectQuoteController::class, 'destroy']);
        Route::post('projects/{project}/submit', [ProjectController::class, 'submit']);
        Route::post('projects/{project}/decision', [ProjectController::class, 'decision']);
        Route::post('projects/{project}/advance', [ProjectController::class, 'advance']);
        Route::post('projects/{project}/imports/network/preview', [NetworkImportController::class, 'preview']);
        Route::post('projects/{project}/imports/network', [NetworkImportController::class, 'store']);
        Route::get('price-items/sections', [PriceItemController::class, 'sections']);
        Route::post('price-items/import/preview', [PriceItemController::class, 'previewImport']);
        Route::post('price-items/import', [PriceItemController::class, 'import']);
        Route::get('price-items', [PriceItemController::class, 'index']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('customer-imports', [CustomerImportController::class, 'store']);
        Route::get('customer-imports/{batch}', [CustomerImportController::class, 'show']);
        Route::patch('customer-imports/{batch}/rows/{row}', [CustomerImportController::class, 'exclude']);
        Route::post('customer-imports/{batch}/validate', [CustomerImportController::class, 'validateBatch']);
        Route::get('my-visits', [MyVisitController::class, 'index']);
        Route::post('sync/push', [FieldSyncController::class, 'push']);
        Route::get('customer-orders/study-technicians', [StudyPlanningController::class, 'technicians']);
        Route::get('customer-orders/study-defaults', [StudyResultController::class, 'defaults']);
        Route::get('customer-orders/pco-catalog', [StudyResultController::class, 'catalog']);
        Route::get('customer-orders/study-pcos', [StudyResultController::class, 'pcos']);
        Route::get('customer-orders/installation-technicians', [InstallationPlanningController::class, 'technicians']);
        Route::post('customer-orders/{order}/study', [StudyPlanningController::class, 'store']);
        Route::post('customer-orders/{order}/installation', [InstallationPlanningController::class, 'store']);
        Route::put('customer-orders/{order}/box', [InstallationPlanningController::class, 'assignBox']);
        Route::get('customer-orders/outcome-reasons', [InstallationResultController::class, 'reasons']);
        Route::post('customer-orders/{order}/installation-result', [InstallationResultController::class, 'store']);
        Route::post('customer-orders/{order}/study-result', [StudyResultController::class, 'store']);
        Route::apiResource('customer-orders', CustomerOrderController::class)->only(['index', 'show']);
        Route::get('block-reasons', [BlockReasonController::class, 'index']);
    });
});
