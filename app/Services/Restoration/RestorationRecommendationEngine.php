<?php

namespace App\Services\Restoration;

use App\Services\Land\LandIntelligenceEngine;

/**
 * Restoration Recommendation Engine.
 *
 * Consumes structured land intelligence (produced by
 * {@see LandIntelligenceEngine}) and returns prioritized,
 * actionable restoration recommendations.
 *
 * Recommendations are always derived from the detected land condition, never
 * generated at random, so the same intelligence yields the same guidance.
 */
class RestorationRecommendationEngine
{
    /**
     * Ordering weight for priority levels (higher is more urgent).
     *
     * @var array<string, int>
     */
    private const PRIORITY_RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

    /**
     * Build recommendations from a canonical analysis result.
     *
     * @param  array<string, mixed>  $analysis
     * @return array{priority: string, recommendations: list<array<string, mixed>>}
     */
    public function recommend(array $analysis): array
    {
        $metrics = $analysis['metrics'] ?? [];
        $issues = $analysis['issues'] ?? [];

        $vegetation = (float) ($metrics['vegetation_coverage'] ?? 0);
        $bareSoil = (float) ($metrics['bare_soil'] ?? 0);
        $degraded = (float) ($metrics['degraded_area'] ?? 0);

        $recommendations = [];

        if ($vegetation < 55 || $this->hasIssue($issues, 'vegetation_loss')) {
            $recommendations[] = [
                'action' => 'native_reforestation',
                'title' => 'Reforestation',
                'priority' => ($vegetation < 45 || $degraded >= 25) ? 'high' : 'medium',
                'reason' => 'Large areas of low vegetation density were detected.',
                'recommended_actions' => [
                    'Native tree planting',
                    'Assisted natural regeneration',
                    'Vegetation corridor development',
                ],
                'objective' => 'Increase canopy cover and reconnect fragmented vegetation over time.',
            ];
        }

        if ($bareSoil >= 12 || $this->hasIssue($issues, 'soil_exposure')) {
            $recommendations[] = [
                'action' => 'soil_restoration',
                'title' => 'Soil Restoration',
                'priority' => $bareSoil >= 25 ? 'high' : 'medium',
                'reason' => 'Significant exposed soil areas were detected.',
                'recommended_actions' => [
                    'Mulching',
                    'Ground cover planting',
                    'Erosion control',
                    'Soil stabilization',
                ],
                'objective' => 'Reduce exposed soil and limit erosion risk.',
            ];
        }

        // Monitoring is always recommended so recovery can be measured over time.
        $recommendations[] = [
            'action' => 'vegetation_monitoring',
            'title' => 'Vegetation Monitoring',
            'priority' => $vegetation >= 75 && $bareSoil < 12 ? 'low' : 'medium',
            'reason' => 'Ongoing monitoring confirms whether restoration is improving land condition.',
            'recommended_actions' => [
                'Repeat drone survey',
                'Monitor vegetation recovery',
                'Compare imagery over time',
            ],
            'objective' => 'Track change and validate restoration progress.',
        ];

        return [
            'priority' => $this->overallPriority($recommendations),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Whether the issue list contains an issue of the given type.
     *
     * @param  list<array<string, mixed>>  $issues
     */
    private function hasIssue(array $issues, string $type): bool
    {
        foreach ($issues as $issue) {
            if (($issue['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * The highest priority across all recommendations.
     *
     * @param  list<array<string, mixed>>  $recommendations
     */
    private function overallPriority(array $recommendations): string
    {
        $highest = 'low';
        foreach ($recommendations as $recommendation) {
            $priority = (string) ($recommendation['priority'] ?? 'low');
            if ((self::PRIORITY_RANK[$priority] ?? 0) > (self::PRIORITY_RANK[$highest] ?? 0)) {
                $highest = $priority;
            }
        }

        return $highest;
    }
}
