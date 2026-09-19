<?php

namespace App\Services\Reporting;

use App\Enums\AnalysisStatus;
use App\Enums\BurnSeverity;
use App\Enums\CarbonEligibility;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Services\Carbon\CarbonCreditAssessor;
use App\Services\Land\MonitoringProgress;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Freezes what a report is allowed to claim.
 *
 * A snapshot holds only what the platform can point at: the lands, their
 * monitored periods, the model that produced each result, the datasets the
 * context came from, and the carbon screening. The numbers are copied, never
 * recomputed later, so a report a regulator already received keeps the values
 * it was issued with.
 */
final class ReportSnapshotBuilder
{
    public function __construct(
        private readonly MonitoringProgress $progress,
        private readonly CarbonCreditAssessor $carbon,
    ) {}

    /**
     * Build a snapshot over the lands the account is allowed to report on.
     *
     * @param  Collection<int, Land>|EloquentCollection<int, Land>  $lands
     * @return array<string, mixed>
     */
    public function build(Collection $lands, string $scope, ?string $preparedBy = null): array
    {
        // A single-land report is naturally assembled from one model, while the
        // aggregate report comes from a query; normalizing here keeps both
        // callers on one path.
        $lands = $lands instanceof EloquentCollection ? $lands : new EloquentCollection($lands->all());

        $lands->loadMissing(['analyses', 'carbonAssessment', 'user']);

        $landRows = $lands->map(fn (Land $land): array => $this->landRow($land))->values();

        return [
            'report' => [
                'scope' => $scope,
                'generated_at' => now()->toIso8601String(),
                'prepared_by' => $preparedBy,
                'land_count' => $lands->count(),
            ],
            'summary' => $this->summary($landRows->all()),
            'severity_mix' => $this->severityMix($landRows->all()),
            'lands' => $landRows->all(),
            'evidence' => $this->evidence($lands),
            'gaps' => $this->gaps($lands, $landRows->all()),
            'disclaimer' => 'Seluruh angka pada laporan ini berasal dari deteksi model pada foto yang diunggah pengguna dan dari dataset publik (DEM, tanah, iklim). Nilai yang bersifat estimasi ditandai pada bagiannya masing-masing dan bukan hasil verifikasi lapangan independen.',
        ];
    }

    /**
     * One land as the report shows it: identity, latest measured condition,
     * before/after progress, and the carbon screening.
     *
     * @return array<string, mixed>
     */
    private function landRow(Land $land): array
    {
        $completed = $this->completedAnalyses($land);
        $latest = $completed->last();
        $progress = $this->progress->for($land, $completed);
        $carbon = $this->carbon->assess($land);

        return [
            'id' => $land->id,
            'name' => $land->name,
            'location_name' => $land->location_name,
            'coordinates' => $land->hasCoordinates() ? ['latitude' => $land->latitude, 'longitude' => $land->longitude] : null,
            'area_ha' => $land->area_ha,
            'owner' => $land->user === null ? null : [
                'name' => $land->user->name,
                'organization' => $land->user->organization,
            ],
            'status' => $land->status->value,
            'status_label' => $land->status->label(),
            'data_status' => $land->data_status,
            'fire_event_date' => $land->fire_event_date?->toDateString(),
            'periods' => $completed->count(),
            'latest_analysis' => $latest === null ? null : [
                'id' => $latest->id,
                'captured_at' => $latest->monitoringDate()?->toIso8601String(),
                'health_score' => $latest->land_health_score,
                'vegetation_percentage' => $latest->vegetation_percentage,
                'bare_soil_percentage' => $latest->bare_soil_percentage,
                'charred_percentage' => $latest->charred_percentage,
                'burn_severity' => $latest->burn_severity?->value,
                'burn_severity_label' => $latest->burn_severity?->shortLabel(),
                'restoration_potential' => $latest->restoration_potential,
                'model' => [
                    'name' => $latest->ai_model,
                    'version' => $latest->ai_model_version,
                ],
            ],
            'progress' => [
                'direction' => $progress['comparison']['direction'] ?? null,
                'vegetation_delta' => $progress['comparison']['vegetation_delta'] ?? null,
                'health_delta' => $progress['comparison']['health_delta'] ?? null,
                'first_captured_at' => $progress['first']['captured_at'] ?? null,
                'latest_captured_at' => $progress['latest']['captured_at'] ?? null,
            ],
            'carbon' => [
                'vegetation_class' => $carbon['vegetation_class'],
                'sequestration_tco2e_per_year' => $carbon['sequestration_tco2e_per_year'],
                'sequestration_5yr_tco2e' => $carbon['sequestration_5yr_tco2e'],
                'eligibility_status' => $carbon['eligibility']['status'],
                'eligibility_label' => $carbon['eligibility']['label'],
                'failed_checks' => array_column($carbon['eligibility']['failed'], 'label'),
            ],
        ];
    }

    /**
     * Aggregate numbers over the report's lands.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summary(array $rows): array
    {
        $scored = array_filter($rows, fn (array $row): bool => $row['latest_analysis'] !== null);
        $area = array_sum(array_map(fn (array $row): float => (float) ($row['area_ha'] ?? 0), $rows));
        $analyses = array_sum(array_column($rows, 'periods'));

        $eligible = count(array_filter($rows, fn (array $row): bool => $row['carbon']['eligibility_status'] === CarbonEligibility::Eligible->value));
        $pipeline = array_sum(array_map(
            fn (array $row): float => (float) ($row['carbon']['sequestration_tco2e_per_year'] ?? 0),
            $rows,
        ));
        $pipelineFiveYears = array_sum(array_map(
            fn (array $row): float => (float) ($row['carbon']['sequestration_5yr_tco2e'] ?? 0),
            $rows,
        ));

        return [
            'lands' => count($rows),
            'lands_with_analysis' => count($scored),
            'area_ha' => round($area, 2),
            'monitoring_periods' => $analyses,
            'average_health_score' => $scored === [] ? null : (int) round(array_sum(array_map(
                fn (array $row): int => (int) $row['latest_analysis']['health_score'],
                $scored,
            )) / count($scored)),
            'average_vegetation_percentage' => $scored === [] ? null : round(array_sum(array_map(
                fn (array $row): float => (float) $row['latest_analysis']['vegetation_percentage'],
                $scored,
            )) / count($scored), 1),
            'improving_lands' => count(array_filter($rows, fn (array $row): bool => ($row['progress']['direction'] ?? null) === 'improving')),
            'carbon' => [
                'eligible_lands' => $eligible,
                'pipeline_tco2e_per_year' => round($pipeline, 2),
                'pipeline_5yr_tco2e' => round($pipelineFiveYears, 2),
            ],
        ];
    }

    /**
     * How many lands sit in each burn-severity band.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function severityMix(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $level = $row['latest_analysis']['burn_severity'] ?? null;

            if ($level === null) {
                continue;
            }

            $counts[$level] = ($counts[$level] ?? 0) + 1;
        }

        $mix = [];

        foreach (BurnSeverity::cases() as $severity) {
            $mix[] = [
                'level' => $severity->value,
                'label' => $severity->shortLabel(),
                'color' => $severity->color(),
                'count' => $counts[$severity->value] ?? 0,
            ];
        }

        return $mix;
    }

    /**
     * Where the numbers came from, so a reader can judge them.
     *
     * @param  Collection<int, Land>  $lands
     * @return array<string, mixed>
     */
    private function evidence(Collection $lands): array
    {
        $models = [];
        $datasets = [];
        $statuses = [];

        foreach ($lands as $land) {
            $statuses[$land->data_status] = ($statuses[$land->data_status] ?? 0) + 1;

            foreach ($land->analyses as $analysis) {
                if ($analysis->analysis_status !== AnalysisStatus::Completed) {
                    continue;
                }

                $label = trim(($analysis->ai_model ?? 'unknown').' v'.($analysis->ai_model_version ?? '?'));
                $models[$label] = ($models[$label] ?? 0) + 1;

                foreach (['terrain', 'soil', 'climate'] as $block) {
                    $source = $analysis->analysis_result[$block]['source']['label'] ?? null;

                    if ($source !== null) {
                        $datasets[$source] = ($datasets[$source] ?? 0) + 1;
                    }
                }
            }
        }

        $asRows = static fn (array $map): array => array_map(
            static fn (string $key, int $count): array => ['label' => $key, 'count' => $count],
            array_keys($map),
            array_values($map),
        );

        return [
            'models' => $asRows($models),
            'datasets' => $asRows($datasets),
            'data_status' => $asRows($statuses),
        ];
    }

    /**
     * What the report cannot claim yet: lands without a point on the map,
     * analyses without coordinates, demo rows, and lands without monitoring.
     *
     * @param  Collection<int, Land>  $lands
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function gaps(Collection $lands, array $rows): array
    {
        $gaps = [];

        $withoutCoordinates = $lands->filter(fn (Land $land): bool => ! $land->hasCoordinates())->count();
        $withoutAnalysis = count(array_filter($rows, fn (array $row): bool => $row['latest_analysis'] === null));
        $demo = count(array_filter($rows, fn (array $row): bool => $row['data_status'] === 'demo'));
        $singlePeriod = count(array_filter($rows, fn (array $row): bool => $row['periods'] === 1));

        if ($withoutCoordinates > 0) {
            $gaps[] = ['key' => 'coordinates', 'label' => "{$withoutCoordinates} lahan belum punya koordinat, sehingga tidak bisa dipetakan atau disampling datanya."];
        }

        if ($withoutAnalysis > 0) {
            $gaps[] = ['key' => 'analyses', 'label' => "{$withoutAnalysis} lahan belum punya analisis foto."];
        }

        if ($singlePeriod > 0) {
            $gaps[] = ['key' => 'periods', 'label' => "{$singlePeriod} lahan baru punya satu periode monitoring, sehingga progress belum terbukti."];
        }

        if ($demo > 0) {
            $gaps[] = ['key' => 'demo_data', 'label' => "{$demo} lahan berisi data demo (bukan hasil unggahan lapangan)."];
        }

        return $gaps;
    }

    /**
     * @return Collection<int, LandAnalysis>
     */
    private function completedAnalyses(Land $land): Collection
    {
        return $land->analyses
            ->filter(fn (LandAnalysis $analysis): bool => $analysis->analysis_status === AnalysisStatus::Completed)
            ->sortBy(fn (LandAnalysis $analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
            ->values();
    }
}
