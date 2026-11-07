<?php

use App\Http\Controllers\Public\MediaController;
use App\Http\Controllers\Public\OrganizationController;
use App\Http\Controllers\Public\ReportController;
use App\Http\Controllers\Public\ScammerController;
use App\Http\Controllers\Public\SeoController;
use App\Http\Middleware\AuditApiRequest;
use App\Http\Middleware\LogIpActivity;
use App\OpenApi\OpenApiDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->middleware('throttle:public-api')->group(function () {
    Route::get('seo/profiles', [SeoController::class, 'index']);

    Route::get('organizations/suggest', [OrganizationController::class, 'suggest']);
    Route::get('organizations/{id}', [OrganizationController::class, 'show']);
    Route::patch('organizations/{id}/profiles', [OrganizationController::class, 'updateProfile'])
        ->middleware(LogIpActivity::class);
    Route::get('organizations/{id}/calendar/{year}', [OrganizationController::class, 'calendar']);
    Route::get('organizations/{id}/contacts', [OrganizationController::class, 'contacts']);
    Route::post('organizations/{id}/contacts', [OrganizationController::class, 'createContact'])
        ->middleware(LogIpActivity::class);
    Route::get('organizations/{id}/payment-methods', [OrganizationController::class, 'paymentMethods']);
    Route::post('organizations/{id}/payment-methods', [OrganizationController::class, 'createPaymentMethod'])
        ->middleware(LogIpActivity::class);
    Route::get('organizations/{id}/reports', [OrganizationController::class, 'reports']);
    Route::post('organizations/{id}/reports', [ReportController::class, 'storeForOrganization'])
        ->middleware(LogIpActivity::class);
    Route::get('organizations/{id}/scammers', [OrganizationController::class, 'scammers']);
    Route::post('organizations/{id}/scammers', [OrganizationController::class, 'createScammer'])
        ->middleware(LogIpActivity::class);
    Route::get('organizations/{id}/map', [OrganizationController::class, 'map']);

    Route::get('scammers/suggest', [ScammerController::class, 'suggest']);
    Route::get('scammers/{id}', [ScammerController::class, 'show']);
    Route::patch('scammers/{id}/profiles', [ScammerController::class, 'updateProfile'])
        ->middleware(LogIpActivity::class);
    Route::get('scammers/{id}/calendar/{year}', [ScammerController::class, 'calendar']);
    Route::get('scammers/{id}/contacts', [ScammerController::class, 'contacts']);
    Route::post('scammers/{id}/contacts', [ScammerController::class, 'createContact'])
        ->middleware(LogIpActivity::class);
    Route::get('scammers/{id}/payment-methods', [ScammerController::class, 'paymentMethods']);
    Route::post('scammers/{id}/payment-methods', [ScammerController::class, 'createPaymentMethod'])
        ->middleware(LogIpActivity::class);
    Route::get('scammers/{id}/reports', [ScammerController::class, 'reports']);
    Route::post('scammers/{id}/reports', [ReportController::class, 'storeForScammer'])
        ->middleware(LogIpActivity::class);
    Route::get('scammers/{id}/organizations', [ScammerController::class, 'organizations']);
    Route::post('scammers/{id}/organizations', [ScammerController::class, 'createOrganization'])
        ->middleware(LogIpActivity::class);
    Route::get('scammers/{id}/map', [ScammerController::class, 'map']);

    Route::get('reports/search-by-title', [ReportController::class, 'searchByTitle'])
        ->middleware(['throttle:public-search', AuditApiRequest::class])
        ->name('public.reports.search-by-title');

    Route::get('reports', [ReportController::class, 'index'])
        ->middleware(['throttle:public-search', AuditApiRequest::class])
        ->name('public.reports.search');

    Route::post('media/profiles', [MediaController::class, 'storeProfilePicture']);
    Route::post('reports/media/proofs', [ReportController::class, 'storeTemporaryProof']);

    Route::post('reports/organizations', [ReportController::class, 'storeOrganization'])
        ->middleware(LogIpActivity::class);
    Route::post('reports/scammers', [ReportController::class, 'storeScammer'])
        ->middleware(LogIpActivity::class);

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
