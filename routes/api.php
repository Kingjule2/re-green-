<?php

use App\Http\Controllers\Api\DroneAnalysisController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned JSON API. These endpoints are currently unauthenticated for the
| MVP; add auth middleware (e.g. Sanctum) before exposing them publicly.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('drone-analyses', [DroneAnalysisController::class, 'index']);
    Route::post('drone-analyses', [DroneAnalysisController::class, 'store']);
    Route::get('drone-analyses/{analysis}', [DroneAnalysisController::class, 'show']);
    Route::delete('drone-analyses/{analysis}', [DroneAnalysisController::class, 'destroy']);
});
