<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Enums\BurnSeverity;
use App\Enums\HealthStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LandAnalysisResource;
use App\Http\Resources\LandResource;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Services\Carbon\CarbonCreditAssessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The dashboard, and the one place where the two audiences split.
 *
 * A farmer gets their own lands, what needs a new photo, and how the vegetation
 * is moving. A pemda/NGO or corporate account gets the same numbers across
 * every monitored land, plus the regional breakdown and the lands in the worst
 * condition — the aggregate view they cannot get anywhere else.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CarbonCreditAssessor $carbon): JsonResponse
    {
        $user = $request->user();
        $lands = $this->visibleLands($request)
            ->with(['user:id,name,organization,role', 'latestAnalysis', 'carbonAssessment', 'analyses'])
            ->withCount('analyses')
            ->get();

        $payload = [
            'scope' => $user->viewsAllLands() ? 'all' : 'own',
            'lands' => $lands->count(),
            'area_ha' => round((float) $lands->sum('area_ha'), 2),
            'analyses' => (int) $lands->sum('analyses_count'),
            'average_health_score' => $this->averageHealth($lands),
            'severity_mix' => $this->severityMix($lands),
            'vegetation_trend' => $this->vegetationTrend($lands),
            'carbon' => $this->carbonSummary($lands, $carbon),
        ];

        if ($user->viewsAllLands()) {
            $payload['by_region'] = $this->byRegion($lands);
            $payload['top_risk_lands'] = $this->topRiskLands($lands)
                ->map(fn (Land $land) => LandResource::make($land->loadCount('analyses'))->resolve())
                ->values()
                ->all();
            $payload['farmers'] = $lands->pluck('user')->filter()->unique('id')->count();
            $payload['organizations'] = $lands->pluck('user.organization')->filter()->unique()->values()->all();
        } else {
            $payload['latest_analyses'] = $lands
                ->flatMap(fn (Land $land) => $land->analyses
                    ->sortByDesc(fn (LandAnalysis $analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
                    ->take(1))
                ->sortByDesc(fn (LandAnalysis $analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
                ->take(5)
                ->map(fn (LandAnalysis $analysis) => LandAnalysisResource::make($analysis)->resolve())
                ->values()
                ->all();
            $payload['needs_monitoring'] = $this->needsMonitoring($lands);
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * @return Builder<Land>
     */
    private function visibleLands(Request $request): Builder
    {
        $query = Land::query();

        if (! $request->user()->viewsAllLands()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Land>  $lands
     */
    private function averageHealth(Collection $lands): ?int
    {
        $scores = $lands
            ->map(fn (Land $land): ?int => $land->latestAnalysis?->land_health_score)
            ->filter(fn (?int $score): bool => $score !== null);

        return $scores->isEmpty() ? null : (int) round($scores->average());
    }

    /**
     * How many lands sit in each burn-severity band.
     *
     * @param  Collection<int, Land>  $lands
     * @return list<array<string, mixed>>
     */
    private function severityMix(Collection $lands): array
    {
        $mix = [];

        foreach (BurnSeverity::cases() as $severity) {
            $mix[] = [
                'level' => $severity->value,
                'label' => $severity->shortLabel(),
                'color' => $severity->color(),
                'count' => $lands->filter(fn (Land $land): bool => $land->latestAnalysis?->burn_severity === $severity)->count(),
            ];
        }

        return $mix;
    }

    /**
     * Average measured condition per monitoring month, oldest first: the
     * dashboard's progress line.
     *
     * @param  Collection<int, Land>  $lands
     * @return list<array<string, mixed>>
     */
    private function vegetationTrend(Collection $lands): array
    {
        $analyses = $lands
            ->flatMap(fn (Land $land) => $land->analyses)
            ->filter(fn (LandAnalysis $analysis): bool => $analysis->analysis_status === AnalysisStatus::Completed
                && $analysis->vegetation_percentage !== null);

        return $analyses
            ->groupBy(fn (LandAnalysis $analysis): string => $analysis->monitoringDate()?->format('Y-m') ?? 'unknown')
            ->sortKeys()
            ->map(fn (Collection $group, string $period): array => [
                'period' => $period,
                'analyses' => $group->count(),
                'vegetation_percentage' => round((float) $group->avg('vegetation_percentage'), 1),
                'health_score' => (int) round((float) $group->avg('land_health_score')),
                'lands' => $group->pluck('land_id')->unique()->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Land>  $lands
     * @return array<string, mixed>
     */
    private function carbonSummary(Collection $lands, CarbonCreditAssessor $carbon): array
    {
        $assessments = $lands->map(fn (Land $land): array => $carbon->assess($land));

        return [
            'eligible' => $assessments->where('eligibility.status', 'eligible')->count(),
            'submitted' => $lands->filter(fn (Land $land): bool => $land->carbonAssessment?->isSubmitted() ?? false)->count(),
            'pipeline_tco2e_per_year' => round((float) $assessments->sum('sequestration_tco2e_per_year'), 2),
            'pipeline_5yr_tco2e' => round((float) $assessments->sum('sequestration_5yr_tco2e'), 2),
        ];
    }

    /**
     * Regional breakdown for the B2G/B2B view.
     *
     * @param  Collection<int, Land>  $lands
     * @return list<array<string, mixed>>
     */
    private function byRegion(Collection $lands): array
    {
        return $lands
            ->groupBy(fn (Land $land): string => $land->location_name ?? 'Tanpa lokasi')
            ->map(function (Collection $group, string $region): array {
                $scores = $group
                    ->map(fn (Land $land): ?int => $land->latestAnalysis?->land_health_score)
                    ->filter(fn (?int $score): bool => $score !== null);
                $vegetation = $group
                    ->map(fn (Land $land): ?float => $land->latestAnalysis?->vegetation_percentage)
                    ->filter(fn (?float $value): bool => $value !== null);

                return [
                    'region' => $region,
                    'lands' => $group->count(),
                    'area_ha' => round((float) $group->sum('area_ha'), 2),
                    'average_health_score' => $scores->isEmpty() ? null : (int) round($scores->average()),
                    'vegetation_percentage' => $vegetation->isEmpty() ? null : round((float) $vegetation->average(), 1),
                ];
            })
            ->sortByDesc('lands')
            ->values()
            ->all();
    }

    /**
     * The lands whose latest measurement is worst, where attention pays off most.
     *
     * @param  Collection<int, Land>  $lands
     * @return Collection<int, Land>
     */
    private function topRiskLands(Collection $lands): Collection
    {
        return $lands
            ->filter(fn (Land $land): bool => $land->latestAnalysis?->land_health_score !== null
                && HealthStatus::fromScore($land->latestAnalysis->land_health_score)?->value !== HealthStatus::Healthy->value)
            ->sortBy(fn (Land $land): int => $land->latestAnalysis->land_health_score)
            ->take(5)
            ->values();
    }

    /**
     * Lands whose newest photo is old enough that the progress claim behind it
     * is going stale — the nudge that keeps the monitoring series alive.
     *
     * @param  Collection<int, Land>  $lands
     * @return list<array<string, mixed>>
     */
    private function needsMonitoring(Collection $lands): array
    {
        return $lands
            ->map(function (Land $land): array {
                $latest = $land->latestAnalysis;
                $date = $latest?->monitoringDate();

                return [
                    'id' => $land->id,
                    'name' => $land->name,
                    'location_name' => $land->location_name,
                    'days_since_last_analysis' => $date === null ? null : (int) round($date->diffInDays(now())),
                    'last_analysis_at' => $date?->toIso8601String(),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['days_since_last_analysis'] ?? PHP_INT_MAX)
            ->take(5)
            ->values()
            ->all();
    }
}
