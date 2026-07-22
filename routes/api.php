<?php

use App\Http\Controllers\AdminApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

/**
 * Internal admin API — the identity provider's control surface, consumed by the
 * Spurs admin control plane over the shared internal secret. Roles and
 * permissions created here are authoritative for the whole platform.
 */
Route::middleware('internal')->prefix('admin')->group(function () {
    Route::get('/overview', [AdminApiController::class, 'overview']);

    Route::get('/users', [AdminApiController::class, 'users']);
    Route::get('/users/{id}', [AdminApiController::class, 'user']);
    Route::post('/users/{id}/roles', [AdminApiController::class, 'assignRoles']);

    Route::get('/roles', [AdminApiController::class, 'roles']);
    Route::post('/roles', [AdminApiController::class, 'createRole']);
    Route::put('/roles/{id}', [AdminApiController::class, 'updateRole']);
    Route::delete('/roles/{id}', [AdminApiController::class, 'deleteRole']);

    Route::get('/permissions', [AdminApiController::class, 'permissions']);
    Route::get('/analytics', [AdminApiController::class, 'analytics']);
    Route::get('/security-events', [AdminApiController::class, 'securityEvents']);

    // Anti-fraud / risk monitoring.
    Route::get('/fraud/overview', [AdminApiController::class, 'fraudOverview']);
    Route::get('/fraud/alerts', [AdminApiController::class, 'fraudAlerts']);

    // KYC review.
    Route::get('/kyc', [AdminApiController::class, 'kycQueue']);
    Route::post('/kyc/{id}/review', [AdminApiController::class, 'reviewKyc']);

    // Platform settings.
    Route::get('/settings', [AdminApiController::class, 'settings']);
    Route::put('/settings', [AdminApiController::class, 'updateSettings']);
});
