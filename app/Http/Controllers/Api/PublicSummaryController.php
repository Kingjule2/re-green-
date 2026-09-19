<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Models\Land;
use App\Models\LandAnalysis;
use Illuminate\Http\JsonResponse;

/**
 * The numbers the landing page is allowed to show.
 *
 * Aggregate only: how many lands are monitored, how much area, how many photos
 * have been analysed, and where. No names, no coordinates, no per-land rows —
 * the public page argues why restoration matters, it does not publish anybody's
 * data.
 */
class PublicSummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $lands = Land::query()->get(['id', 'area_ha', 'location_name']);

        return response()->json([
            'data' => [
                'lands_monitored' => $lands->count(),
                'hectares' => round((float) $lands->sum('area_ha'), 2),
                'monitoring_periods' => LandAnalysis::query()
                    ->where('analysis_status', AnalysisStatus::Completed->value)
                    ->count(),
                'regions' => $lands->pluck('location_name')->filter()->unique()->count(),
            ],
        ]);
    }
}
