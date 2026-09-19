<?php

namespace App\Http\Controllers\Api;

use App\Enums\HealthStatus;
use App\Http\Controllers\Controller;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The geospatial layer of the B2G/B2B dashboard: every monitored land as a
 * point with the status the map colours it by.
 *
 * Lands without coordinates are not invented onto the map; they are counted in
 * `meta.without_coordinates` so the dashboard can be honest about how much of
 * the portfolio is actually locatable.
 */
class MapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $lands = Land::query()
            ->when(! $user->viewsAllLands(), fn ($query) => $query->where('user_id', $user->id))
            ->with(['user:id,name,organization', 'latestAnalysis'])
            ->withCount('analyses')
            ->get();

        $located = $lands->filter(fn (Land $land): bool => $land->hasCoordinates());

        return response()->json([
            'data' => $located
                ->map(fn (Land $land): array => [
                    'id' => $land->id,
                    'name' => $land->name,
                    'location_name' => $land->location_name,
                    'latitude' => $land->latitude,
                    'longitude' => $land->longitude,
                    'area_ha' => $land->area_ha,
                    'status' => $land->status->value,
                    'status_label' => $land->status->label(),
                    'status_color' => $land->status->color(),
                    'health_score' => $land->latestAnalysis?->land_health_score,
                    'health_status' => HealthStatus::fromScore($land->latestAnalysis?->land_health_score)?->value,
                    'burn_severity' => $land->latestAnalysis?->burn_severity?->value,
                    'burn_severity_label' => $land->latestAnalysis?->burn_severity?->shortLabel(),
                    'vegetation_percentage' => $land->latestAnalysis?->vegetation_percentage,
                    'owner_name' => $land->user?->name,
                    'organization' => $land->user?->organization,
                    'latest_analysis_at' => $land->latestAnalysis?->monitoringDate()?->toIso8601String(),
                    'analyses_count' => $land->analyses_count,
                ])
                ->values()
                ->all(),
            'meta' => [
                'count' => $located->count(),
                'without_coordinates' => $lands->count() - $located->count(),
                'area_ha' => round((float) $located->sum('area_ha'), 2),
            ],
        ]);
    }
}
