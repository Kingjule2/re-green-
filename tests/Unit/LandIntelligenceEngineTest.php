<?php

namespace Tests\Unit;

use App\Services\Land\LandIntelligenceEngine;
use PHPUnit\Framework\TestCase;

class LandIntelligenceEngineTest extends TestCase
{
    private function engine(): LandIntelligenceEngine
    {
        return new LandIntelligenceEngine;
    }

    /**
     * @param  array<string, float>  $cover
     * @return array<string, mixed>
     */
    private function derive(array $cover, float $confidence = 0.9): array
    {
        return $this->engine()->derive([
            'land_cover' => $cover,
            'confidence' => ['overall' => $confidence, 'vegetation' => $confidence],
            'image' => ['width' => 4000, 'height' => 3000],
            'model' => ['name' => 'color-histogram', 'version' => '0.1.0'],
        ]);
    }

    /**
     * A terrain context in the shape the ML service reports it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function terrain(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'source' => [
                'id' => 'demnas',
                'label' => 'DEMNAS (BIG)',
                'dataset' => 'DEMNAS - DEM Nasional Indonesia, 0.27 arc-second (~8 m)',
                'provider' => 'Badan Informasi Geospasial (BIG)',
                'service_url' => 'https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer',
                'portal_url' => 'https://tanahair.indonesia.go.id/portal-web/unduh/demnas',
            ],
            'sampled_at' => ['latitude' => -7.53, 'longitude' => 110.45],
            'profile' => [
                'elevation_m' => 1200.0,
                'slope_deg' => 24.0,
                'aspect_deg' => 210.0,
                'hillshade' => 0.42,
                'ruggedness_m' => 5.1,
                'resolution_m' => 8.3,
                'window' => ['rows' => 3, 'cols' => 3, 'spacing_x_m' => 8.3, 'spacing_y_m' => 8.4],
            ],
        ], $overrides);
    }

    /**
     * A soil context carrying the real SoilGrids values for Mount Merapi.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function soil(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'source' => ['id' => 'soilgrids', 'label' => 'ISRIC SoilGrids'],
            'sampled_at' => ['latitude' => -7.53, 'longitude' => 110.45],
            'depth_cm' => 5,
            'resolution_m' => 250.0,
            'profile' => [
                'texture' => ['class_name' => 'Clay Loam', 'sand_pct' => 33.8, 'silt_pct' => 31.8, 'clay_pct' => 34.3],
                'subsoil_texture' => ['class_name' => 'Clay Loam', 'sand_pct' => 33.7, 'silt_pct' => 31.7, 'clay_pct' => 34.6],
                'ph' => 5.3,
                'organic_carbon_g_kg' => 103.6,
                'nitrogen_g_kg' => 5.77,
            ],
        ], $overrides);
    }

    /**
     * A climate context carrying the real NASA POWER normals for Mount Merapi.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function climate(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'source' => ['id' => 'nasa-power', 'label' => 'NASA POWER'],
            'sampled_at' => ['latitude' => -7.53, 'longitude' => 110.45],
            'profile' => [
                'annual_rainfall_mm' => 2093.2,
                'monthly_rainfall_mm' => ['JAN' => 345.7, 'AUG' => 31.9],
                'dry_months' => 2,
                'dry_month_names' => ['JUL', 'AUG'],
                'wet_months' => 8,
                'driest_month' => ['month' => 'AUG', 'rainfall_mm' => 31.9],
                'wettest_month' => ['month' => 'JAN', 'rainfall_mm' => 345.7],
                'mean_temperature_c' => 25.0,
                'mean_daily_max_c' => 38.7,
                'mean_humidity_pct' => 82.5,
                'topsoil_wetness_pct' => 77.0,
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, float>  $cover
     * @param  array<string, mixed>  $terrain
     * @param  array<string, mixed>  $soil
     * @param  array<string, mixed>  $climate
     * @return array<string, mixed>
     */
    private function deriveWithContext(array $cover, array $terrain = [], array $soil = [], array $climate = []): array
    {
        return $this->engine()->derive([
            'land_cover' => $cover,
            'confidence' => ['overall' => 0.9, 'vegetation' => 0.9, 'bare_soil' => 0.85, 'water' => 0.8],
            'terrain' => $terrain,
            'soil' => $soil,
            'climate' => $climate,
            'image' => ['width' => 4000, 'height' => 3000],
            'model' => ['name' => 'color-histogram', 'version' => '0.1.0'],
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function cover(): array
    {
        return ['dense_vegetation' => 50, 'sparse_vegetation' => 20, 'bare_soil' => 20, 'water' => 10];
    }

    public function test_vegetation_coverage_is_the_sum_of_dense_and_sparse(): void
    {
        $result = $this->derive([
            'dense_vegetation' => 40,
            'sparse_vegetation' => 20,
            'bare_soil' => 25,
            'water' => 10,
            'built_area' => 0,
            'other' => 5,
        ]);

        $this->assertSame(60.0, $result['metrics']['vegetation_coverage']);
    }

    public function test_score_is_clamped_and_status_matches_band(): void
    {
        $healthy = $this->derive(['dense_vegetation' => 85, 'sparse_vegetation' => 10, 'bare_soil' => 2, 'water' => 3]);
        $this->assertGreaterThanOrEqual(0, $healthy['land_health']['score']);
        $this->assertLessThanOrEqual(100, $healthy['land_health']['score']);
        $this->assertSame('healthy', $healthy['land_health']['status']);

        $critical = $this->derive(['dense_vegetation' => 2, 'sparse_vegetation' => 5, 'bare_soil' => 80, 'water' => 0, 'other' => 13]);
        $this->assertLessThanOrEqual(100, $critical['land_health']['score']);
        $this->assertContains($critical['land_health']['status'], ['degraded', 'critical']);
    }

    public function test_more_vegetation_never_lowers_the_score(): void
    {
        $low = $this->derive(['dense_vegetation' => 20, 'sparse_vegetation' => 10, 'bare_soil' => 60, 'water' => 10]);
        $high = $this->derive(['dense_vegetation' => 70, 'sparse_vegetation' => 10, 'bare_soil' => 10, 'water' => 10]);

        $this->assertGreaterThanOrEqual($low['land_health']['score'], $high['land_health']['score']);
    }

    public function test_land_cover_is_normalized_to_100_and_sorted_descending(): void
    {
        // Deliberately does not sum to 100.
        $result = $this->derive(['dense_vegetation' => 30, 'sparse_vegetation' => 30, 'bare_soil' => 30, 'water' => 30]);

        $sum = array_sum(array_column($result['land_cover'], 'percentage'));
        $this->assertEqualsWithDelta(100.0, $sum, 1.5);

        $percentages = array_column($result['land_cover'], 'percentage');
        $sorted = $percentages;
        rsort($sorted);
        $this->assertSame($sorted, $percentages);
    }

    public function test_hectares_are_never_fabricated(): void
    {
        $result = $this->derive(['dense_vegetation' => 50, 'sparse_vegetation' => 20, 'bare_soil' => 20, 'water' => 10]);

        $this->assertNull($result['metrics']['estimated_area_hectares']);
    }

    public function test_low_vegetation_triggers_vegetation_loss_issue(): void
    {
        $result = $this->derive(['dense_vegetation' => 15, 'sparse_vegetation' => 15, 'bare_soil' => 60, 'water' => 10]);

        $types = array_column($result['issues'], 'type');
        $this->assertContains('vegetation_loss', $types);
        $this->assertContains('soil_exposure', $types);
    }

    public function test_every_context_is_reported_unavailable_when_nothing_was_measured(): void
    {
        // A perception from an older ML service carries no context keys at all.
        $result = $this->derive($this->cover());

        foreach (['terrain', 'soil', 'climate'] as $block) {
            $this->assertFalse($result[$block]['available'], $block);
            $this->assertNull($result[$block]['profile'], $block);
            $this->assertStringContainsString('No', $result[$block]['reason'], $block);
        }

        $this->assertFalse($result['agriculture']['assessable']);
        $this->assertFalse($result['agriculture']['ranking_available']);
        $this->assertSame([], $result['agriculture']['crops']);
        $this->assertSame([], $result['agriculture']['limiting_factors']);
        $this->assertFalse($result['agriculture']['rainfall']['available']);
    }

    public function test_declared_soil_and_rainfall_make_agriculture_assessable_without_sampled_context(): void
    {
        $result = $this->engine()->derive([
            'land_cover' => $this->cover(),
            'confidence' => ['overall' => 0.9, 'vegetation' => 0.9],
            'image' => ['width' => 4000, 'height' => 3000],
            'model' => ['name' => 'color-histogram', 'version' => '0.1.0'],
        ], [
            'soil_texture' => 'Loam',
            'rainfall_mm' => 1800,
        ]);
        $agriculture = $result['agriculture'];

        $this->assertTrue($agriculture['assessable']);
        $this->assertTrue($agriculture['ranking_available']);
        $this->assertNotEmpty($agriculture['crops']);
        $this->assertSame('declared', $agriculture['input_sources']['soil_texture']);
        $this->assertSame('declared', $agriculture['input_sources']['rainfall']);
        $this->assertSame(1800, $agriculture['inputs']['rainfall']);
    }

    public function test_reason_from_the_ml_service_is_passed_through(): void
    {
        $result = $this->deriveWithContext($this->cover(), [
            'available' => false,
            'reason' => 'DEMNAS (BIG): DEMNAS could not be reached: ConnectTimeout',
        ]);

        $this->assertFalse($result['terrain']['available']);
        $this->assertSame(
            'DEMNAS (BIG): DEMNAS could not be reached: ConnectTimeout',
            $result['terrain']['reason'],
        );
    }

    public function test_terrain_exposes_band_slope_class_and_aspect(): void
    {
        $terrain = $this->deriveWithContext($this->cover(), $this->terrain())['terrain'];

        $this->assertTrue($terrain['available']);
        $this->assertSame(1200.0, $terrain['profile']['elevation_m']);
        $this->assertSame(24.0, $terrain['profile']['slope_deg']);
        $this->assertSame(210.0, $terrain['profile']['aspect_deg']);
        $this->assertSame(8.3, $terrain['profile']['resolution_m']);
        $this->assertSame('upper_montane', $terrain['elevation_band']['key']);
        $this->assertSame(700.0, $terrain['elevation_band']['min_m']);
        $this->assertSame(1500.0, $terrain['elevation_band']['max_m']);
        $this->assertSame('steep', $terrain['slope_class']);
        $this->assertSame('SW', $terrain['aspect_label']);
        $this->assertSame('demnas', $terrain['source']['id']);
        $this->assertSame(
            'https://tanahair.indonesia.go.id/portal-web/unduh/demnas',
            $terrain['source']['portal_url'],
        );
    }

    public function test_flat_ground_keeps_a_null_aspect(): void
    {
        $result = $this->deriveWithContext($this->cover(), $this->terrain([
            'profile' => ['elevation_m' => 12.0, 'slope_deg' => 0.2, 'aspect_deg' => null],
        ]));

        $this->assertNull($result['terrain']['profile']['aspect_deg']);
        $this->assertSame('flat', $result['terrain']['aspect_label']);
        $this->assertSame('flat', $result['terrain']['slope_class']);
        $this->assertSame('lowland', $result['terrain']['elevation_band']['key']);
    }

    public function test_soil_context_is_exposed_with_texture_and_chemistry(): void
    {
        $soil = $this->deriveWithContext($this->cover(), $this->terrain(), $this->soil())['soil'];

        $this->assertTrue($soil['available']);
        $this->assertSame('soilgrids', $soil['source']['id']);
        $this->assertSame(5, $soil['depth_cm']);
        $this->assertSame(250.0, $soil['resolution_m']);
        $this->assertSame('Clay Loam', $soil['profile']['texture']['class_name']);
        $this->assertSame(34.3, $soil['profile']['texture']['clay_pct']);
        $this->assertSame(5.3, $soil['profile']['ph']);
        $this->assertSame(103.6, $soil['profile']['organic_carbon_g_kg']);
        $this->assertSame(5.77, $soil['profile']['nitrogen_g_kg']);
    }

    public function test_climate_context_is_exposed_with_rainfall_distribution(): void
    {
        $climate = $this->deriveWithContext(
            $this->cover(),
            $this->terrain(),
            $this->soil(),
            $this->climate(),
        )['climate'];

        $this->assertTrue($climate['available']);
        $this->assertSame('nasa-power', $climate['source']['id']);
        $this->assertSame(2093.2, $climate['profile']['annual_rainfall_mm']);
        $this->assertSame(2, $climate['profile']['dry_months']);
        $this->assertSame(['JUL', 'AUG'], $climate['profile']['dry_month_names']);
        $this->assertSame(8, $climate['profile']['wet_months']);
        $this->assertSame(['month' => 'AUG', 'rainfall_mm' => 31.9], $climate['profile']['driest_month']);
        $this->assertSame(25.0, $climate['profile']['mean_temperature_c']);
        $this->assertSame(77.0, $climate['profile']['topsoil_wetness_pct']);
    }

    public function test_soil_and_climate_stay_null_when_the_service_did_not_answer(): void
    {
        $result = $this->deriveWithContext(
            $this->cover(),
            $this->terrain(),
            $this->soil(['available' => false, 'reason' => 'SoilGrids could not be reached: ConnectTimeout']),
            $this->climate(['available' => false, 'reason' => 'NASA POWER could not be reached: ConnectTimeout']),
        );

        $this->assertFalse($result['soil']['available']);
        $this->assertSame('SoilGrids could not be reached: ConnectTimeout', $result['soil']['reason']);
        $this->assertNull($result['soil']['profile']);

        $this->assertFalse($result['climate']['available']);
        $this->assertSame('NASA POWER could not be reached: ConnectTimeout', $result['climate']['reason']);

        // Crop ranking needs terrain and climate; without climate it stands down
        // instead of ranking on half a picture.
        $this->assertFalse($result['agriculture']['ranking_available']);
        $this->assertSame([], $result['agriculture']['crops']);
        $this->assertContains('temperature', $result['agriculture']['missing_inputs']);
        $this->assertContains('rainfall', $result['agriculture']['missing_inputs']);
        $this->assertNotContains('elevation', $result['agriculture']['missing_inputs']);
    }

    public function test_slope_and_elevation_bound_the_land_capacity(): void
    {
        $flatLowland = $this->deriveWithContext($this->cover(), $this->terrain([
            'profile' => ['elevation_m' => 90.0, 'slope_deg' => 1.5],
        ]));
        $this->assertSame('high', $flatLowland['agriculture']['suitability']);
        $this->assertSame([], $flatLowland['agriculture']['limiting_factors']);

        // A gentle slope at 1800 m is limited by its climate band, not its gradient.
        $highland = $this->deriveWithContext($this->cover(), $this->terrain([
            'profile' => ['elevation_m' => 1800.0, 'slope_deg' => 4.0],
        ]));
        $this->assertSame('moderate', $highland['agriculture']['suitability']);
        $this->assertSame('highland', $highland['terrain']['elevation_band']['key']);
        $this->assertSame('elevation', $highland['agriculture']['limiting_factors'][0]['key']);

        // 24 degrees caps it at "low", and exposed soil adds erosion to the limits.
        $steep = $this->deriveWithContext($this->cover(), $this->terrain());
        $this->assertSame('low', $steep['agriculture']['suitability']);
        $this->assertSame(['slope', 'erosion'], array_column($steep['agriculture']['limiting_factors'], 'key'));

        // Above 30 degrees the land is off the table for crops entirely.
        $verySteep = $this->deriveWithContext($this->cover(), $this->terrain([
            'profile' => ['elevation_m' => 1200.0, 'slope_deg' => 35.0],
        ]));
        $this->assertSame('unsuitable', $verySteep['agriculture']['suitability']);
    }

    public function test_rainfall_impact_is_derived_from_the_climate_normals(): void
    {
        $rainfall = $this->deriveWithContext(
            $this->cover(),
            $this->terrain(),
            $this->soil(),
            $this->climate(),
        )['agriculture']['rainfall'];

        $this->assertTrue($rainfall['available']);
        $this->assertSame(2093.2, $rainfall['annual_rainfall_mm']);
        $this->assertSame(2, $rainfall['dry_months']);
        $this->assertSame('JUL, AUG', $rainfall['dry_season']);
        // 2093 mm is a "high" rainfall class and a 24 degree slope a "steep" one;
        // the weaker of the two caps erosivity.
        $this->assertSame('high', $rainfall['erosivity']);
        // A real gradient sheds water long before it can pond.
        $this->assertSame('low', $rainfall['drainage_risk']);
        $this->assertNotEmpty($rainfall['impacts']);
    }

    public function test_flat_heavy_soil_in_a_wet_climate_carries_a_drainage_risk(): void
    {
        $agriculture = $this->deriveWithContext(
            $this->cover(),
            $this->terrain(['profile' => ['elevation_m' => 60.0, 'slope_deg' => 1.2]]),
            $this->soil(),
            $this->climate(),
        )['agriculture'];

        $this->assertSame('high', $agriculture['rainfall']['drainage_risk']);
        $this->assertContains('drainage', array_column($agriculture['limiting_factors'], 'key'));
    }

    public function test_crops_are_ranked_and_the_best_fit_leads(): void
    {
        $agriculture = $this->deriveWithContext(
            $this->cover(),
            $this->terrain(),
            $this->soil(),
            $this->climate(),
        )['agriculture'];

        $this->assertTrue($agriculture['ranking_available']);
        $this->assertSame(10, $agriculture['crop_count']);
        $this->assertCount(5, $agriculture['crops']);

        $scores = array_column($agriculture['crops'], 'score');
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores);

        // 1200 m, 24 degrees, 2093 mm, 25 C, pH 5.3, clay loam: a coffee/tea
        // profile, and certainly not rice on that gradient.
        $best = $agriculture['crops'][0];
        $this->assertContains($best['id'], ['coffee-arabica', 'tea', 'coffee-robusta']);
        $this->assertGreaterThanOrEqual(50, $best['score']);
        $this->assertNotEmpty($best['classification']);

        $ids = array_column($agriculture['crops'], 'id');
        $this->assertNotContains('rice', $ids);
    }

    public function test_exposed_soil_on_a_steep_slope_raises_an_erosion_issue(): void
    {
        $result = $this->deriveWithContext(
            ['dense_vegetation' => 30, 'sparse_vegetation' => 20, 'bare_soil' => 40, 'water' => 10],
            $this->terrain(['profile' => ['elevation_m' => 780.0, 'slope_deg' => 31.6]]),
        );

        $erosion = $this->issueOfType($result['issues'], 'slope_erosion_risk');

        $this->assertNotNull($erosion);
        $this->assertSame('high', $erosion['severity']);
        // The degraded area (bare soil plus half the sparse vegetation) is the
        // larger of the two, so that is what the issue reports.
        $this->assertSame(50.0, $erosion['affected_area']);
    }

    public function test_steep_sparse_ground_raises_an_instability_issue(): void
    {
        $result = $this->deriveWithContext(
            ['dense_vegetation' => 10, 'sparse_vegetation' => 15, 'bare_soil' => 40, 'water' => 5, 'other' => 30],
            $this->terrain(['profile' => ['elevation_m' => 1200.0, 'slope_deg' => 27.0]]),
        );

        $instability = $this->issueOfType($result['issues'], 'slope_instability');

        $this->assertNotNull($instability);
        $this->assertSame('medium', $instability['severity']);
    }

    public function test_gentle_vegetated_ground_raises_no_terrain_issue(): void
    {
        $result = $this->deriveWithContext(
            ['dense_vegetation' => 80, 'sparse_vegetation' => 10, 'bare_soil' => 5, 'water' => 5],
            $this->terrain(['profile' => ['elevation_m' => 150.0, 'slope_deg' => 6.0]]),
        );

        $this->assertSame([], array_column($result['issues'], 'type'));
    }

    public function test_the_analysis_stays_json_encodable_with_full_context(): void
    {
        // The result is stored in a JSON column, and json_encode() refuses
        // infinite or NaN values, so no band bound may ever be infinite.
        $result = $this->deriveWithContext(
            $this->cover(),
            $this->terrain(['profile' => ['elevation_m' => 3400.0, 'slope_deg' => 89.0, 'aspect_deg' => 359.9]]),
            $this->soil(),
            $this->climate(),
        );

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertIsString($encoded);
        $this->assertSame('alpine', $result['terrain']['elevation_band']['key']);
        $this->assertNull($result['terrain']['elevation_band']['max_m']);
        $this->assertSame('N', $result['terrain']['aspect_label']);
        $this->assertSame('very_steep', $result['terrain']['slope_class']);
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>|null
     */
    private function issueOfType(array $issues, string $type): ?array
    {
        foreach ($issues as $issue) {
            if (($issue['type'] ?? null) === $type) {
                return $issue;
            }
        }

        return null;
    }
}
