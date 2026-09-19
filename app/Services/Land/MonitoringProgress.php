<?php

namespace App\Services\Land;

use App\Enums\AnalysisStatus;
use App\Models\Land;
use App\Models\LandAnalysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the analyses of a land into the monitoring story: the period series,
 * the before/after pair, and the change between uploads.
 *
 * Only completed analyses take part. A failed upload is an operational event,
 * not evidence about the land, so it never appears in the progress chart and
 * never moves the comparison.
 */
class MonitoringProgress
{
    /**
     * Vegetation change, in percentage points, inside which a land reads as
     * stable rather than improving or declining.
     */
    private const STABLE_BAND = 3.0;

    /**
     * The monitoring series for one land, oldest period first.
     *
     * @param  Collection<int, LandAnalysis>|null  $analyses
     * @return array<string, mixed>
     */
    public function for(Land $land, ?Collection $analyses = null): array
    {
        $series = $this->series($analyses ?? $land->analyses()->get());
        $first = $series->first();
        $latest = $series->last();

        return [
            'points' => $series->map(fn (LandAnalysis $analysis): array => $this->point($analysis))->values()->all(),
            'first' => $first === null ? null : $this->point($first),
            'latest' => $latest === null ? null : $this->point($latest),
            'comparison' => $first === null || $latest === null || $first->is($latest)
                ? null
                : $this->comparison($first, $latest),
            'periods' => $series->count(),
            'span_days' => $this->spanDays($first, $latest),
        ];
    }

    /**
     * Progress of every analysis against the one before it, keyed by analysis
     * id, so a timeline can be rendered in a single pass.
     *
     * @param  Collection<int, LandAnalysis>  $analyses
     * @return array<int, array<string, mixed>|null>
     */
    public function timeline(Collection $analyses): array
    {
        $timeline = [];
        $previous = null;

        foreach ($this->series($analyses) as $analysis) {
            $timeline[$analysis->id] = $this->compare($previous, $analysis);
            $previous = $analysis;
        }

        return $timeline;
    }

    /**
     * The change between two analyses, or null for the first analysis of a land
     * (there is nothing to compare it with yet).
     *
     * @return array<string, mixed>|null
     */
    public function compare(?LandAnalysis $previous, LandAnalysis $current): ?array
    {
        if ($previous === null) {
            return null;
        }

        $vegetationDelta = $this->delta($previous->vegetation_percentage, $current->vegetation_percentage);

        return [
            'compared_to' => [
                'id' => $previous->id,
                'captured_at' => $previous->monitoringDate()?->toIso8601String(),
            ],
            'health_delta' => (int) ($current->land_health_score ?? 0) - (int) ($previous->land_health_score ?? 0),
            'vegetation_delta' => $vegetationDelta,
            'bare_soil_delta' => $this->delta($previous->bare_soil_percentage, $current->bare_soil_percentage),
            'charred_delta' => $this->delta($previous->charred_percentage, $current->charred_percentage),
            'direction' => $this->direction($vegetationDelta),
        ];
    }

    /**
     * Which way the vegetation moved between two measurements.
     */
    public function direction(float $vegetationDelta): string
    {
        return match (true) {
            $vegetationDelta >= self::STABLE_BAND => 'improving',
            $vegetationDelta <= -self::STABLE_BAND => 'declining',
            default => 'stable',
        };
    }

    /**
     * The completed analyses of a land, ordered by the period they describe.
     *
     * @param  Collection<int, LandAnalysis>  $analyses
     * @return Collection<int, LandAnalysis>
     */
    private function series(Collection $analyses): Collection
    {
        return $analyses
            ->filter(fn (LandAnalysis $analysis): bool => $analysis->analysis_status === AnalysisStatus::Completed
                && $analysis->vegetation_percentage !== null)
            ->sortBy(fn (LandAnalysis $analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * One chart point: the measured headline numbers of a period.
     *
     * @return array<string, mixed>
     */
    private function point(LandAnalysis $analysis): array
    {
        return [
            'analysis_id' => $analysis->id,
            'captured_at' => $analysis->monitoringDate()?->toIso8601String(),
            'land_health_score' => $analysis->land_health_score,
            'vegetation_percentage' => $analysis->vegetation_percentage,
            'bare_soil_percentage' => $analysis->bare_soil_percentage,
            'charred_percentage' => $analysis->charred_percentage,
            'burn_severity' => [
                'level' => $analysis->burn_severity?->value,
                'label' => $analysis->burn_severity?->shortLabel(),
                'color' => $analysis->burn_severity?->color(),
            ],
            'image_url' => $analysis->image_url,
            'model' => $analysis->ai_model,
        ];
    }

    /**
     * The before/after comparison a land page shows first.
     *
     * @return array<string, mixed>
     */
    private function comparison(LandAnalysis $first, LandAnalysis $latest): array
    {
        $vegetationDelta = $this->delta($first->vegetation_percentage, $latest->vegetation_percentage);

        return [
            'health_delta' => (int) ($latest->land_health_score ?? 0) - (int) ($first->land_health_score ?? 0),
            'vegetation_delta' => $vegetationDelta,
            'bare_soil_delta' => $this->delta($first->bare_soil_percentage, $latest->bare_soil_percentage),
            'charred_delta' => $this->delta($first->charred_percentage, $latest->charred_percentage),
            'direction' => $this->direction($vegetationDelta),
            'from' => [
                'id' => $first->id,
                'captured_at' => $first->monitoringDate()?->toIso8601String(),
                'land_health_score' => $first->land_health_score,
                'vegetation_percentage' => $first->vegetation_percentage,
                'image_url' => $first->image_url,
            ],
            'to' => [
                'id' => $latest->id,
                'captured_at' => $latest->monitoringDate()?->toIso8601String(),
                'land_health_score' => $latest->land_health_score,
                'vegetation_percentage' => $latest->vegetation_percentage,
                'image_url' => $latest->image_url,
            ],
        ];
    }

    private function delta(?float $from, ?float $to): float
    {
        return round((float) $to - (float) $from, 1);
    }

    private function spanDays(?LandAnalysis $first, ?LandAnalysis $latest): ?int
    {
        $start = $first?->monitoringDate();
        $end = $latest?->monitoringDate();

        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return null;
        }

        return (int) round($start->diffInDays($end));
    }
}
