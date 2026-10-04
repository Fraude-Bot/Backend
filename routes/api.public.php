<?php

use App\Http\Controllers\Public\OrganizationController;
use App\Http\Controllers\Public\ReportController;
use App\Http\Controllers\Public\ScammerController;
use App\Http\Middleware\AuditApiRequest;
use App\OpenApi\OpenApiDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->middleware('throttle:public-api')->group(function () {
    Route::get('organizations/suggest', [OrganizationController::class, 'suggest']);
    Route::get('organizations/{id}', [OrganizationController::class, 'show']);
    Route::get('organizations/{id}/calendar/{year}', [OrganizationController::class, 'calendar']);
    Route::get('organizations/{id}/contacts', [OrganizationController::class, 'contacts']);
    Route::get('organizations/{id}/reports', [OrganizationController::class, 'reports']);
    Route::get('organizations/{id}/map', [OrganizationController::class, 'map']);

    Route::get('scammers/suggest', [ScammerController::class, 'suggest']);
    Route::get('scammers/{id}', [ScammerController::class, 'show']);
    Route::get('scammers/{id}/calendar/{year}', [ScammerController::class, 'calendar']);
    Route::get('scammers/{id}/contacts', [ScammerController::class, 'contacts']);
    Route::get('scammers/{id}/reports', [ScammerController::class, 'reports']);
    Route::get('scammers/{id}/map', [ScammerController::class, 'map']);

    Route::get('reports', [ReportController::class, 'index'])
        ->middleware(['throttle:public-search', AuditApiRequest::class])
        ->name('public.reports.search');

    Route::post('reports/media/profiles', [ReportController::class, 'storeTemporaryProfilePicture']);
    Route::post('reports/media/proofs', [ReportController::class, 'storeTemporaryProof']);
    
    Route::post('reports/organizations', [ReportController::class, 'storeOrganization']);
    Route::post('reports/scammers', [ReportController::class, 'storeScammer']);

    Route::get('healthcheck', function () {
        return response()->json(['status' => 'ok']);
    });

    Route::get('readiness', function () {
        DB::select('select 1');
        Cache::put('health:readiness', true, 5);

        return response()->json([
            'status' => Cache::get('health:readiness') === true ? 'ready' : 'degraded',
            'checks' => ['database' => 'ok', 'cache' => 'ok'],
        ]);
    });

    Route::get('openapi', function (OpenApiDocument $document) {
        return response($document->bundledJson(), 200, [
            'Content-Type' => 'application/json',
        ]);
    })->name('public.openapi');
});
