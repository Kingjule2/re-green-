<?php

use App\Http\Controllers\Api\AnalysisRunController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarbonController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DroneAnalysisController;
use App\Http\Controllers\Api\LandAnalysisController;
use App\Http\Controllers\Api\LandController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\PublicSummaryController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Versioned API
|--------------------------------------------------------------------------
|
| The SPA is served by this same application, so these routes run inside the
| `web` middleware group: the session cookie the Blade page already has is the
| authentication, and the CSRF token rendered into that page covers every
| mutating request. Nothing here needs a token in JavaScript storage.
|
| Roles decide what a session may read: a farmer sees the lands they registered,
| a pemda/NGO or corporate account sees the aggregate (see LandPolicy).
|
*/
Route::prefix('v1')->middleware('web')->group(function (): void {
    // Public: aggregate numbers for the landing page, nothing personal.
    Route::get('public/summary', PublicSummaryController::class);

    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Drone analysis: currently no auth guard — the controller scopes by
    // session when one is present.  Protect before production.
    Route::get('drone-analyses', [DroneAnalysisController::class, 'index']);
    Route::post('drone-analyses', [DroneAnalysisController::class, 'store']);
    Route::get('drone-analyses/{analysis}', [DroneAnalysisController::class, 'show']);
    Route::delete('drone-analyses/{analysis}', [DroneAnalysisController::class, 'destroy']);

    Route::middleware('auth')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', DashboardController::class);
        Route::get('map/lands', MapController::class);

        // Lands: the farmer's own sites, or every monitored land for the
        // aggregate roles.
        Route::get('lands', [LandController::class, 'index']);
        Route::post('lands', [LandController::class, 'store']);
        Route::get('lands/{land}', [LandController::class, 'show']);
        Route::patch('lands/{land}', [LandController::class, 'update']);
        Route::delete('lands/{land}', [LandController::class, 'destroy']);
        Route::get('lands/{land}/progress', [LandController::class, 'progress']);
        Route::post('lands/{land}/crop-selection', [LandController::class, 'selectCrop']);

        // Photo analysis = the monitoring pipeline: upload a period, poll it,
        // and the series of periods is what proves progress.
        Route::get('lands/{land}/analyses', [LandAnalysisController::class, 'index']);
        Route::post('lands/{land}/analyses', [LandAnalysisController::class, 'store']);
        Route::get('analyses/{analysis}', [LandAnalysisController::class, 'show']);
        Route::delete('analyses/{analysis}', [LandAnalysisController::class, 'destroy']);

        // Carbon credit: screening per land, application, portfolio.
        Route::get('lands/{land}/carbon', [CarbonController::class, 'show']);
        Route::post('lands/{land}/carbon/submit', [CarbonController::class, 'submit']);
        Route::get('carbon/portfolio', [CarbonController::class, 'portfolio']);

        // Frozen reports for compliance and ESG reporting.
        Route::get('reports', [ReportController::class, 'index']);
        Route::post('reports', [ReportController::class, 'store']);
        Route::get('reports/{report}', [ReportController::class, 'show']);
        Route::get('reports/{report}/pdf', [ReportController::class, 'pdf']);
        Route::get('reports/{report}/csv', [ReportController::class, 'csv']);
        Route::delete('reports/{report}', [ReportController::class, 'destroy']);

        // Survey workflow carried over from the previous platform: sub-plots,
        // manual field observations, evidence assets, and remote-sensing runs.
        Route::get('lands/{land}/parcels', [LandController::class, 'parcels']);
        Route::post('lands/{land}/parcels', [LandController::class, 'storeParcel']);
        Route::get('lands/{land}/observations', [LandController::class, 'observations']);
        Route::post('lands/{land}/observations', [LandController::class, 'storeObservation']);
        Route::get('lands/{land}/assets', [LandController::class, 'assets']);
        Route::post('lands/{land}/assets', [LandController::class, 'storeAsset']);
        Route::post('lands/{land}/analyses/dnbr', [AnalysisRunController::class, 'storeDnbr']);

        Route::get('analysis-runs/{analysisRun}', [AnalysisRunController::class, 'show']);
        Route::post('analysis-runs/{analysisRun}/reviews', [AnalysisRunController::class, 'storeReview']);
    });
});
