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
 * Land cover alone cannot say what a slope needs. When the analysis carries a
 * terrain context (slope, aspect, elevation band from BIG DEMNAS or NASA SRTM)
 * the engine also answers the terrain questions: how to keep soil on a steep
 * incline, how to keep a steep, bare slope from failing in the next rainy
 * season, and which crops the elevation band actually supports.
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
        $terrain = $analysis['terrain'] ?? [];
        $agriculture = $analysis['agriculture'] ?? [];

        $vegetation = (float) ($metrics['vegetation_coverage'] ?? 0);
        $bareSoil = (float) ($metrics['bare_soil'] ?? 0);
        $degraded = (float) ($metrics['degraded_area'] ?? 0);

        $slope = ($terrain['available'] ?? false) === true ? (float) $terrain['profile']['slope_deg'] : null;

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

        // Terrain-driven advice, only when a DEM was actually sampled.
        if ($slope !== null && $slope >= 15.0 && ($bareSoil >= 10.0 || $degraded >= 15.0 || $this->hasIssue($issues, 'slope_erosion_risk'))) {
            $recommendations[] = [
                'action' => 'slope_soil_conservation',
                'title' => 'Slope Soil Conservation',
                'priority' => $slope >= 30.0 ? 'high' : 'medium',
                'reason' => "A {$slope}° slope with exposed soil sheds runoff and topsoil downhill.",
                'recommended_actions' => [
                    'Contour terracing',
                    'Contour strip cropping',
                    'Cover crops between rows',
                    'Silt pits and check dams',
                ],
                'objective' => 'Hold topsoil in place on the slope before vegetation can establish cover.',
            ];
        }

        if ($this->hasIssue($issues, 'slope_instability')) {
            $recommendations[] = [
                'action' => 'slope_bioengineering',
                'title' => 'Slope Bioengineering',
                'priority' => 'high',
                'reason' => 'Steep, sparsely vegetated ground is where heavy rain after a disaster turns into landslides and debris flows.',
                'recommended_actions' => [
                    'Vetiver grass hedgerows',
                    'Bamboo and Gliricidia planting',
                    'Live poles and brush layering',
                    'Gabion or woven-bamboo check dams',
                ],
                'objective' => 'Reinforce the slope with living root systems so it stabilises before replanting begins.',
            ];
        }

        if ($this->hasRankedCrops($agriculture)) {
            $best = $agriculture['crops'][0];
            $alternatives = array_slice(array_column(array_slice($agriculture['crops'], 1, 3), 'name'), 0, 3);

            $recommendations[] = [
                'action' => 'crop_recommendation',
                'title' => 'Crop Recommendation',
                'priority' => $agriculture['crops'][0]['classification_key'] === 'very_suitable' ? 'low' : 'medium',
                'reason' => sprintf(
                    '%s scores %d/100 (%s) on this site\'s measured elevation, slope, rainfall, temperature, soil texture and pH%s.',
                    $best['name'],
                    $best['score'],
                    $best['classification'],
                    $alternatives === [] ? '' : '; next best are '.implode(', ', $alternatives),
                ),
                'recommended_actions' => $this->plantingActions($slope, $best, $agriculture),
                'objective' => 'Plant what this specific site can carry, instead of the lowland default.',
            ];
        }

        if ($this->hasRainfallAdvice($agriculture)) {
            $rainfall = $agriculture['rainfall'];
            $recommendations[] = [
                'action' => 'rainfall_management',
                'title' => 'Rainfall Management',
                'priority' => $rainfall['dry_months'] >= 3 || $rainfall['drainage_risk'] === 'high' ? 'high' : 'medium',
                'reason' => $this->rainfallReason($rainfall),
                'recommended_actions' => $this->rainfallActions($rainfall),
                'objective' => 'Bridge the dry season and keep the topsoil in place through the wet one.',
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
     * Whether the crop engine managed to rank crops for this site.
     *
     * Ranking needs the terrain and climate contexts; when either is missing
     * the analysis says so through `missing_inputs` and no crop is recommended,
     * because advice off a partial picture is worse than none.
     *
     * @param  array<string, mixed>  $agriculture
     */
    private function hasRankedCrops(array $agriculture): bool
    {
        return ($agriculture['ranking_available'] ?? false) === true
            && ! empty($agriculture['crops'])
            && ($agriculture['crops'][0]['score'] ?? 0) >= 50;
    }

    /**
     * Whether the rainfall pattern needs managing on this site.
     *
     * @param  array<string, mixed>  $agriculture
     */
    private function hasRainfallAdvice(array $agriculture): bool
    {
        $rainfall = $agriculture['rainfall'] ?? [];

        if (($rainfall['available'] ?? false) !== true) {
            return false;
        }

        return ($rainfall['dry_months'] ?? 0) >= 1
            || in_array($rainfall['drainage_risk'] ?? null, ['moderate', 'high'], true)
            || in_array($rainfall['erosivity'] ?? null, ['high', 'very_high'], true);
    }

    /**
     * Why the rainfall pattern matters here.
     *
     * @param  array<string, mixed>  $rainfall
     */
    private function rainfallReason(array $rainfall): string
    {
        $reasons = [];

        if (($rainfall['dry_months'] ?? 0) >= 1) {
            $reasons[] = "{$rainfall['dry_months']} dry months ({$rainfall['dry_season']}) fall below 60 mm";
        }

        if ($rainfall['drainage_risk'] === 'high') {
            $reasons[] = 'flat heavy soil holds water through the wet season';
        }

        if (in_array($rainfall['erosivity'], ['high', 'very_high'], true)) {
            $reasons[] = "rainfall erosivity on this slope is {$rainfall['erosivity']}";
        }

        return sprintf(
            'This site receives %s mm of rain a year, and %s.',
            $rainfall['annual_rainfall_mm'],
            implode('; ', $reasons),
        );
    }

    /**
     * Concrete water actions for this site's rainfall pattern.
     *
     * @param  array<string, mixed>  $rainfall
     * @return list<string>
     */
    private function rainfallActions(array $rainfall): array
    {
        $actions = [];

        if (($rainfall['dry_months'] ?? 0) >= 1) {
            $actions[] = 'Store wet-season water for the dry months';
            $actions[] = 'Plan planting so the growing period clears the dry season';
        }

        if (in_array($rainfall['erosivity'] ?? null, ['high', 'very_high'], true)) {
            $actions[] = 'Keep the soil covered between harvests';
            $actions[] = 'Direct runoff with contour drains';
        }

        if ($rainfall['drainage_risk'] === 'high') {
            $actions[] = 'Cut drainage channels before planting';
            $actions[] = 'Plant on raised beds';
        }

        return $actions;
    }

    /**
     * Concrete planting actions, shaped by the slope the crop goes on.
     *
     * @param  array<string, mixed>  $crop
     * @param  array<string, mixed>  $agriculture
     * @return list<string>
     */
    private function plantingActions(?float $slope, array $crop, array $agriculture): array
    {
        $actions = ["Plant {$crop['name']}"];

        foreach (array_slice($crop['limiting_parameters'] ?? [], 0, 2) as $parameter) {
            $actions[] = 'Address the limiting factor: '.str_replace('_', ' ', (string) $parameter);
        }

        if ($slope !== null && $slope >= 15.0) {
            $actions[] = 'Use terrace or agroforestry rows instead of open-field cropping';
        }

        if (($crop['agroforestry_potential'] ?? 0) >= 0.7) {
            $actions[] = 'Grow it as an agroforestry canopy with a ground crop';
        }

        return $actions;
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
