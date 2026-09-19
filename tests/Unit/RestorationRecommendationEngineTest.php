<?php

namespace Tests\Unit;

use App\Services\Restoration\RestorationRecommendationEngine;
use PHPUnit\Framework\TestCase;

class RestorationRecommendationEngineTest extends TestCase
{
    private function engine(): RestorationRecommendationEngine
    {
        return new RestorationRecommendationEngine;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  list<array<string, mixed>>  $issues
     * @return array{priority: string, recommendations: list<array<string, mixed>>}
     */
    private function recommend(array $metrics, array $issues = []): array
    {
        return $this->engine()->recommend(['metrics' => $metrics, 'issues' => $issues]);
    }

    private function actions(array $result): array
    {
        return array_map(fn (array $r): string => $r['action'], $result['recommendations']);
    }

    public function test_degraded_land_yields_high_priority_reforestation_and_soil_restoration(): void
    {
        $result = $this->recommend([
            'vegetation_coverage' => 42,
            'bare_soil' => 31,
            'degraded_area' => 27,
            'restoration_potential' => 'high',
        ]);

        $this->assertSame('high', $result['priority']);
        $this->assertEqualsCanonicalizing(
            ['native_reforestation', 'soil_restoration', 'vegetation_monitoring'],
            $this->actions($result),
        );

        $reforestation = $result['recommendations'][0];
        $this->assertSame('native_reforestation', $reforestation['action']);
        $this->assertSame('high', $reforestation['priority']);
        $this->assertNotEmpty($reforestation['recommended_actions']);
    }

    public function test_healthy_land_only_recommends_monitoring_at_low_priority(): void
    {
        $result = $this->recommend([
            'vegetation_coverage' => 85,
            'bare_soil' => 5,
            'degraded_area' => 6,
            'restoration_potential' => 'low',
        ]);

        $this->assertSame('low', $result['priority']);
        $this->assertSame(['vegetation_monitoring'], $this->actions($result));
    }

    public function test_soil_issue_forces_soil_restoration_even_with_moderate_bare_soil(): void
    {
        $result = $this->recommend(
            ['vegetation_coverage' => 70, 'bare_soil' => 8, 'degraded_area' => 10],
            [['type' => 'soil_exposure', 'severity' => 'medium']],
        );

        $this->assertContains('soil_restoration', $this->actions($result));
    }

    public function test_recommendations_are_deterministic(): void
    {
        $metrics = ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22];

        $this->assertSame($this->recommend($metrics), $this->recommend($metrics));
    }

    /**
     * A terrain block in the shape the intelligence engine produces.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function terrain(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'profile' => ['elevation_m' => 1200.0, 'slope_deg' => 24.0, 'aspect_deg' => 210.0],
            'elevation_band' => ['key' => 'upper_montane', 'label' => 'Upper montane', 'min_m' => 700.0, 'max_m' => 1500.0],
            'slope_class' => 'steep',
            'aspect_label' => 'SW',
        ], $overrides);
    }

    /**
     * An agriculture assessment in the shape the intelligence engine produces.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function agriculture(array $overrides = []): array
    {
        return array_replace([
            'assessable' => true,
            'suitability' => 'low',
            'limiting_factors' => [],
            'elevation_band' => ['key' => 'upper_montane', 'label' => 'Upper montane', 'min_m' => 700.0, 'max_m' => 1500.0],
            'slope_class' => 'steep',
            'ranking_available' => true,
            'crops' => [$this->crop('tea', 'Tea', 74, 'suitable', 0.5)],
            'rainfall' => $this->rainfall(),
        ], $overrides);
    }

    /**
     * A ranked crop entry.
     *
     * @return array<string, mixed>
     */
    private function crop(
        string $id,
        string $name,
        int $score,
        string $classification,
        float $agroforestry = 0.7,
        array $limiting = [],
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'score' => $score,
            'classification' => ucwords(str_replace('_', ' ', $classification)),
            'classification_key' => $classification,
            'limiting_parameters' => $limiting,
            'agroforestry_potential' => $agroforestry,
        ];
    }

    /**
     * A rainfall impact block.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rainfall(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'annual_rainfall_mm' => 2093.2,
            'dry_months' => 2,
            'dry_season' => 'JUL, AUG',
            'wet_months' => 8,
            'wettest_month' => ['month' => 'JAN', 'rainfall_mm' => 345.7],
            'erosivity' => 'high',
            'drainage_risk' => 'low',
            'impacts' => ['2 dry months (JUL, AUG) below 60 mm.'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  list<array<string, mixed>>  $issues
     * @return array{priority: string, recommendations: list<array<string, mixed>>}
     */
    private function recommendTerrain(array $metrics, array $terrain, array $agriculture, array $issues = []): array
    {
        return $this->engine()->recommend([
            'metrics' => $metrics,
            'issues' => $issues,
            'terrain' => $terrain,
            'agriculture' => $agriculture,
        ]);
    }

    /**
     * @param  array{priority: string, recommendations: list<array<string, mixed>>}  $result
     * @return array<string, mixed>|null
     */
    private function recommendation(array $result, string $action): ?array
    {
        foreach ($result['recommendations'] as $recommendation) {
            if ($recommendation['action'] === $action) {
                return $recommendation;
            }
        }

        return null;
    }

    public function test_steep_slope_with_exposed_soil_recommends_slope_conservation(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain(),
            $this->agriculture(),
        );

        $conservation = $this->recommendation($result, 'slope_soil_conservation');

        $this->assertNotNull($conservation);
        $this->assertSame('medium', $conservation['priority']);
        $this->assertContains('Contour terracing', $conservation['recommended_actions']);
    }

    public function test_ranked_crops_drive_the_crop_recommendation(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain(),
            $this->agriculture([
                'crops' => [
                    $this->crop('tea', 'Tea', 78, 'suitable', 0.5, ['soil_ph']),
                    $this->crop('coffee-arabica', 'Coffee Arabica', 71, 'suitable', 0.9),
                ],
            ]),
        );

        $crop = $this->recommendation($result, 'crop_recommendation');

        $this->assertNotNull($crop);
        $this->assertSame('medium', $crop['priority']);
        $this->assertStringContainsString('Tea scores 78/100', $crop['reason']);
        $this->assertStringContainsString('Coffee Arabica', $crop['reason']);
        $this->assertContains('Plant Tea', $crop['recommended_actions']);
        $this->assertContains('Address the limiting factor: soil ph', $crop['recommended_actions']);
        // 24 degrees means it goes on terrace or agroforestry rows.
        $this->assertContains('Use terrace or agroforestry rows instead of open-field cropping', $crop['recommended_actions']);
    }

    public function test_land_that_cannot_carry_a_crop_gets_no_crop_recommendation(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain(['profile' => ['elevation_m' => 1200.0, 'slope_deg' => 33.0], 'slope_class' => 'very_steep']),
            $this->agriculture([
                'suitability' => 'unsuitable',
                // Nothing on this site reaches the "moderately suitable" band.
                'crops' => [$this->crop('rice', 'Rice (Padi)', 34, 'not_suitable')],
            ]),
        );

        $this->assertNull($this->recommendation($result, 'crop_recommendation'));
    }

    public function test_steeper_ground_escalates_slope_conservation_to_high(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain(['profile' => ['elevation_m' => 1200.0, 'slope_deg' => 33.0], 'slope_class' => 'very_steep']),
            $this->agriculture(['suitability' => 'unsuitable']),
        );

        $this->assertSame('high', $this->recommendation($result, 'slope_soil_conservation')['priority']);
    }

    public function test_slope_instability_recommends_bioengineering(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 25, 'bare_soil' => 40, 'degraded_area' => 48],
            $this->terrain(['profile' => ['elevation_m' => 1200.0, 'slope_deg' => 28.0]]),
            $this->agriculture(),
            [['type' => 'slope_instability', 'severity' => 'high']],
        );

        $bioengineering = $this->recommendation($result, 'slope_bioengineering');

        $this->assertNotNull($bioengineering);
        $this->assertSame('high', $bioengineering['priority']);
        $this->assertContains('Vetiver grass hedgerows', $bioengineering['recommended_actions']);
        $this->assertSame('high', $result['priority']);
    }

    public function test_dry_season_recommends_rainfall_management(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain(),
            $this->agriculture(),
        );

        $rainfall = $this->recommendation($result, 'rainfall_management');

        $this->assertNotNull($rainfall);
        $this->assertSame('medium', $rainfall['priority']);
        $this->assertStringContainsString('2 dry months (JUL, AUG)', $rainfall['reason']);
        $this->assertContains('Store wet-season water for the dry months', $rainfall['recommended_actions']);
        $this->assertContains('Keep the soil covered between harvests', $rainfall['recommended_actions']);
    }

    public function test_flat_heavy_soil_escalates_rainfall_management_to_high(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain(['profile' => ['elevation_m' => 40.0, 'slope_deg' => 1.0], 'slope_class' => 'flat']),
            $this->agriculture([
                'rainfall' => $this->rainfall([
                    'dry_months' => 4,
                    'dry_season' => 'JUN, JUL, AUG, SEP',
                    'drainage_risk' => 'high',
                ]),
            ]),
        );

        $rainfall = $this->recommendation($result, 'rainfall_management');

        $this->assertSame('high', $rainfall['priority']);
        $this->assertContains('Cut drainage channels before planting', $rainfall['recommended_actions']);
        $this->assertContains('Plant on raised beds', $rainfall['recommended_actions']);
    }

    public function test_gentle_ground_gets_no_slope_recommendations(): void
    {
        $result = $this->recommendTerrain(
            ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            $this->terrain([
                'profile' => ['elevation_m' => 1800.0, 'slope_deg' => 4.0],
                'slope_class' => 'gentle',
                'elevation_band' => ['key' => 'highland', 'label' => 'Highland', 'min_m' => 1500.0, 'max_m' => 2500.0],
            ]),
            $this->agriculture([
                'suitability' => 'moderate',
                'slope_class' => 'gentle',
                'elevation_band' => ['key' => 'highland', 'label' => 'Highland', 'min_m' => 1500.0, 'max_m' => 2500.0],
                'crops' => [$this->crop('tea', 'Tea', 82, 'suitable', 0.4)],
            ]),
        );

        $this->assertNull($this->recommendation($result, 'slope_soil_conservation'));
        $this->assertNull($this->recommendation($result, 'slope_bioengineering'));

        // Nothing to hold back on a gentle slope, but the crop still stands.
        $crop = $this->recommendation($result, 'crop_recommendation');
        $this->assertNotNull($crop);
        $this->assertSame(['Plant Tea'], $crop['recommended_actions']);
    }

    public function test_terrain_recommendations_are_absent_when_no_dem_was_sampled(): void
    {
        $result = $this->engine()->recommend([
            'metrics' => ['vegetation_coverage' => 50, 'bare_soil' => 20, 'degraded_area' => 22],
            'issues' => [],
            'terrain' => ['available' => false, 'reason' => 'DEMNAS (BIG): DEMNAS could not be reached: ConnectTimeout'],
            'agriculture' => [
                'assessable' => false,
                'suitability' => null,
                'limiting_factors' => [],
                'elevation_band' => null,
                'slope_class' => null,
                'ranking_available' => false,
                'crops' => [],
                'rainfall' => ['available' => false, 'reason' => 'no climate context was provided for this survey'],
            ],
        ]);

        $this->assertNotContains('slope_soil_conservation', $this->actions($result));
        $this->assertNotContains('slope_bioengineering', $this->actions($result));
        $this->assertNotContains('crop_recommendation', $this->actions($result));
        $this->assertNotContains('rainfall_management', $this->actions($result));
    }
}
