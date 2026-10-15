<?php

use App\Http\Controllers\Admin\DevelopmentController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\ScammerController;
use App\Http\Middleware\AuditApiRequest;
use App\Http\Middleware\EnsureUserIsActive;

Route::prefix('admin')->middleware(['auth:sanctum', EnsureUserIsActive::class, 'abilities:admin:write', 'can:manage-fraud-data', 'throttle:admin', AuditApiRequest::class])->group(function () {
    Route::get('organizations/{organization}/scammers', [OrganizationController::class, 'getScammers']);
    Route::post('organizations/{organization}/scammers', [OrganizationController::class, 'addScammer']);
    Route::post('organizations/{organization}/payment-methods', [OrganizationController::class, 'createPaymentMethod']);
    Route::post('organizations/{organization}/contacts', [OrganizationController::class, 'createContact']);

    Route::post('scammers/{scammer}/restore', [ScammerController::class, 'restore']);
    Route::post('organizations/{organization}/restore', [OrganizationController::class, 'restore']);

    Route::put('scammers/{scammer}/contacts/{contact}', [ScammerController::class, 'updateContact']);
    Route::post('scammers/{scammer}/contacts', [ScammerController::class, 'createContact']);
    Route::post('scammers/{scammer}/payment-methods', [ScammerController::class, 'createPaymentMethod']);

    Route::apiResource('scammers', ScammerController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('organizations', OrganizationController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
});

if (app()->environment('local') && config('app.dev_token_enabled')) {
    Route::post('admin/token', [DevelopmentController::class, 'token'])
        ->middleware('throttle:dev-token');
}
