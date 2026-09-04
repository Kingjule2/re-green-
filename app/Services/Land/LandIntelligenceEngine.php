<?php

namespace App\Services\Land;

use App\Services\Ml\LandAnalysisClient;

/**
 * Land Intelligence Engine.
 *
 * Turns a raw land-cover perception result (produced by the Computer Vision
 * model behind {@see LandAnalysisClient}) into structured land
 * intelligence: a health score, headline metrics, and detected problems.
 *
 * Every value produced here is an AI-derived estimate, never ground truth.
 * The logic is fully deterministic so the same perception always yields the
 * same intelligence, which keeps the pipeline testable and explainable.
 */
class LandIntelligenceEngine
{
    /**
     * Display metadata for each supported land-cover class.
     *
     * @var array<string, array{label: string, color: string}>
     */
    private const CLASSES = [
        'dense_vegetation' => ['label' => 'Dense Vegetation', 'color' => '#15803d'],
        'sparse_vegetation' => ['label' => 'Sparse Vegetation', 'color' => '#84cc16'],
        'bare_soil' => ['label' => 'Bare Soil', 'color' => '#b45309'],
        'water' => ['label' => 'Water', 'color' => '#0ea5e9'],
        'built_area' => ['label' => 'Built Area', 'color' => '#78716c'],
        'other' => ['label' => 'Other', 'color' => '#94a3b8'],
    ];

    /**
     * Derive the canonical analysis result from a normalized perception result.
     *
     * @param  array{land_cover?: array<string, float|int>, confidence?: array<string, float|int>, segmentation?: mixed, image?: array<string, int|null>, model?: array{name?: string, version?: string}}  $perception
     * @return array<string, mixed>
     */
    public function derive(array $perception): array
    {
        $cover = $this->normalizeCover($perception['land_cover'] ?? []);
        $confidence = $perception['confidence'] ?? [];
        $overallConfidence = (float) ($confidence['overall'] ?? 0.85);

        $vegetation = round($cover['dense_vegetation'] + $cover['sparse_vegetation'], 1);
        $bareSoil = round($cover['bare_soil'], 1);
        $water = round($cover['water'], 1);
        $degraded = round(min(100, $bareSoil + 0.5 * $cover['sparse_vegetation']), 1);

        $score = $this->healthScore($vegetation, $cover['dense_vegetation'], $water, $bareSoil, $degraded);

        return [
            'land_health' => [
                'score' => $score,
                'status' => $this->healthStatus($score),
            ],
            'land_cover' => $this->coverBreakdown($cover),
            'metrics' => [
                'vegetation_coverage' => $vegetation,
                'bare_soil' => $bareSoil,
                'water_presence' => $water,
                'degraded_area' => $degraded,
                'restoration_potential' => $this->restorationPotential($vegetation, $degraded),
                // Hectares require a reliable ground sample distance, which a
                // single image cannot provide. Never fabricate an area estimate.
                'estimated_area_hectares' => null,
            ],
            'issues' => $this->detectIssues($vegetation, $bareSoil, $degraded, $cover, $confidence),
            'confidence' => [
                'overall' => round($overallConfidence, 2),
                'vegetation_detection' => round((float) ($confidence['vegetation'] ?? $overallConfidence), 2),
                'bare_soil_detection' => round((float) ($confidence['bare_soil'] ?? $overallConfidence), 2),
                'water_detection' => round((float) ($confidence['water'] ?? $overallConfidence), 2),
            ],
            'segmentation' => $this->segmentation($perception['segmentation'] ?? null),
            'model' => [
                'name' => $perception['model']['name'] ?? 'unknown',
                'version' => $perception['model']['version'] ?? '0.0.0',
            ],
            'image' => [
                'width' => $perception['image']['width'] ?? null,
                'height' => $perception['image']['height'] ?? null,
            ],
        ];
    }

    /**
     * Coerce arbitrary cover input into the full class set and normalize the
     * percentages so they sum to 100.
     *
     * @param  array<string, float|int>  $raw
     * @return array<string, float>
     */
    private function normalizeCover(array $raw): array
    {
        $cover = [];
        foreach (array_keys(self::CLASSES) as $key) {
            $cover[$key] = max(0.0, (float) ($raw[$key] ?? 0.0));
        }

        $total = array_sum($cover);
        if ($total <= 0) {
            $cover['other'] = 100.0;

            return $cover;
        }

        foreach ($cover as $key => $value) {
            $cover[$key] = round($value / $total * 100, 1);
        }

        return $cover;
    }

    /**
     * Compute the 0-100 land health score.
     *
     * Vegetation drives the score up (canopy density adds a bonus); exposed
     * soil and degradation drive it down. The result is clamped to 0-100.
     */
    private function healthScore(float $vegetation, float $dense, float $water, float $bareSoil, float $degraded): int
    {
        $raw = $vegetation * 0.8
            + $dense * 0.2
            + $water * 0.2
            - $degraded * 0.5
            - $bareSoil * 0.2
            + 6;

        return (int) max(0, min(100, round($raw)));
    }

    /**
     * Map a score to its health classification band.
     */
    private function healthStatus(int $score): string
    {
        return match (true) {
            $score >= 80 => 'healthy',
            $score >= 60 => 'moderate',
            $score >= 40 => 'degraded',
            default => 'critical',
        };
    }

    /**
     * Estimate ecological restoration potential from detected conditions.
     */
    private function restorationPotential(float $vegetation, float $degraded): string
    {
        return match (true) {
            $vegetation < 45 || $degraded >= 25 => 'high',
            $vegetation < 65 || $degraded >= 12 => 'medium',
            default => 'low',
        };
    }

    /**
     * Build the ordered land-cover breakdown for stacked visualization.
     *
     * @param  array<string, float>  $cover
     * @return list<array{key: string, label: string, color: string, percentage: float}>
     */
    private function coverBreakdown(array $cover): array
    {
        $breakdown = [];
        foreach (self::CLASSES as $key => $meta) {
            $breakdown[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'percentage' => $cover[$key],
            ];
        }

        usort($breakdown, fn (array $a, array $b): int => $b['percentage'] <=> $a['percentage']);

        return $breakdown;
    }

    /**
     * Detect potential land issues from the derived metrics.
     *
     * @param  array<string, float>  $cover
     * @param  array<string, float|int>  $confidence
     * @return list<array<string, mixed>>
     */
    private function detectIssues(float $vegetation, float $bareSoil, float $degraded, array $cover, array $confidence): array
    {
        $issues = [];
        $vegConfidence = round((float) ($confidence['vegetation'] ?? $confidence['overall'] ?? 0.85), 2);
        $soilConfidence = round((float) ($confidence['bare_soil'] ?? $confidence['overall'] ?? 0.8), 2);

        if ($vegetation < 65) {
            $severity = $vegetation < 40 ? 'high' : ($vegetation < 55 ? 'medium' : 'low');
            $issues[] = [
                'type' => 'vegetation_loss',
                'title' => 'Vegetation Loss',
                'severity' => $severity,
                'affected_area' => round(max(0, 100 - $vegetation) * 0.5 + $cover['sparse_vegetation'] * 0.5, 1),
                'confidence' => $vegConfidence,
                'description' => 'Significant areas show sparse or absent vegetation coverage.',
            ];
        }

        if ($bareSoil >= 10) {
            $issues[] = [
                'type' => 'soil_exposure',
                'title' => 'Soil Exposure',
                'severity' => $bareSoil >= 25 ? 'high' : 'medium',
                'affected_area' => $bareSoil,
                'confidence' => $soilConfidence,
                'description' => 'Exposed soil areas may indicate vegetation degradation or potential erosion risk.',
            ];
        }

        if ($cover['sparse_vegetation'] > $cover['dense_vegetation'] && $vegetation >= 20) {
            $issues[] = [
                'type' => 'fragmented_vegetation',
                'title' => 'Fragmented Vegetation',
                'severity' => 'medium',
                'affected_area' => round($cover['sparse_vegetation'], 1),
                'confidence' => $vegConfidence,
                'description' => 'Vegetation appears fragmented across the analyzed area.',
            ];
        }

        return $issues;
    }

    /**
     * Pass a coarse segmentation grid through when the model provided one.
     *
     * @return array{available: bool, cols: int|null, rows: int|null, grid: mixed, legend: mixed}
     */
    private function segmentation(mixed $segmentation): array
    {
        if (! is_array($segmentation) || empty($segmentation['grid'])) {
            return ['available' => false, 'cols' => null, 'rows' => null, 'grid' => null, 'legend' => null];
        }

        return [
            'available' => true,
            'cols' => $segmentation['cols'] ?? null,
            'rows' => $segmentation['rows'] ?? null,
            'grid' => $segmentation['grid'],
            'legend' => $segmentation['legend'] ?? array_map(
                fn (array $meta): string => $meta['color'],
                self::CLASSES,
            ),
        ];
    }
}
