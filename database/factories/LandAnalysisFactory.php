<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Enums\BurnSeverity;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Services\Land\LandIntelligenceEngine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LandAnalysis>
 */
class LandAnalysisFactory extends Factory
{
    protected $model = LandAnalysis::class;

    /**
     * The USDA texture classes the demo soil samples carry, with their
     * approximate sand/silt/clay composition so the texture block the crop
     * engine reads is internally consistent.
     *
     * @var array<string, array{sand: float, silt: float, clay: float}>
     */
    private const SOIL_TEXTURES = [
        'Clay Loam' => ['sand' => 32.0, 'silt' => 34.0, 'clay' => 34.0],
        'Loam' => ['sand' => 42.0, 'silt' => 40.0, 'clay' => 18.0],
        'Sandy Loam' => ['sand' => 65.0, 'silt' => 25.0, 'clay' => 10.0],
        'Silt Loam' => ['sand' => 20.0, 'silt' => 65.0, 'clay' => 15.0],
        'Clay' => ['sand' => 22.0, 'silt' => 22.0, 'clay' => 56.0],
    ];

    /**
     * @var list<string>
     */
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /**
     * Define the model's default state: a completed analysis with coherent,
     * denormalized headline metrics backed by the canonical intelligence
     * payload.
     *
     * The payload is not hand-assembled here — it is what
     * {@see LandIntelligenceEngine::derive()} produces from a representative
     * perception, so the fixture can never drift from the engine's land-cover
     * classes, severity bands or agriculture block.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $width = fake()->numberBetween(3000, 6000);
        $height = (int) round($width * 2 / 3);
        $latitude = fake()->latitude(-11, 6);
        $longitude = fake()->longitude(95, 141);

        $result = (new LandIntelligenceEngine)->derive($this->perception($width, $height, $latitude, $longitude));

        return [
            'user_id' => null,
            'land_id' => Land::factory(),
            'image_path' => 'land-analyses/'.fake()->uuid().'.jpg',
            'image_filename' => 'lahan_'.fake()->numberBetween(1, 999).'.jpg',
            'image_width' => $width,
            'image_height' => $height,
            'file_size' => fake()->numberBetween(1_000_000, 40_000_000),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'captured_at' => fake()->dateTimeBetween('-1 year'),
            'capture_source' => fake()->randomElement(['field-survey', 'drone-mission', 'satellite-imagery']),
            'notes' => fake()->optional(0.3)->sentence(),
            'vertical_status' => 'incomplete',
            'vertical_metadata' => null,
            'analysis_status' => AnalysisStatus::Completed,
            'analysis_started_at' => now()->subMinutes(2),
            'analysis_completed_at' => now()->subMinute(),
            'analysis_error' => null,
            'land_health_score' => $result['land_health']['score'],
            'burn_severity' => BurnSeverity::from($result['burn_severity']['level']),
            'burn_severity_score' => $result['burn_severity']['score'],
            'burn_severity_confidence' => $result['burn_severity']['confidence'],
            'vegetation_percentage' => $result['metrics']['vegetation_coverage'],
            'bare_soil_percentage' => $result['metrics']['bare_soil'],
            'charred_percentage' => $result['metrics']['charred_soil'],
            'water_percentage' => $result['metrics']['water_presence'],
            'degraded_percentage' => $result['metrics']['degraded_area'],
            'restoration_potential' => $result['metrics']['restoration_potential'],
            'ai_model' => $result['model']['name'],
            'ai_model_version' => $result['model']['version'],
            'analysis_result' => $result,
            'recommendation_result' => $this->sampleRecommendationResult(),
        ];
    }

    /**
     * A freshly created analysis awaiting processing.
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'analysis_status' => AnalysisStatus::Pending,
            'analysis_started_at' => null,
            'analysis_completed_at' => null,
            'analysis_error' => null,
            'land_health_score' => null,
            'burn_severity' => null,
            'burn_severity_score' => null,
            'burn_severity_confidence' => null,
            'vegetation_percentage' => null,
            'bare_soil_percentage' => null,
            'charred_percentage' => null,
            'water_percentage' => null,
            'degraded_percentage' => null,
            'restoration_potential' => null,
            'ai_model' => null,
            'ai_model_version' => null,
            'analysis_result' => null,
            'recommendation_result' => null,
        ]);
    }

    /**
     * An analysis currently being processed by the ML pipeline.
     */
    public function processing(): static
    {
        return $this->pending()->state(fn (): array => [
            'analysis_status' => AnalysisStatus::Processing,
            'analysis_started_at' => now(),
        ]);
    }

    /**
     * An analysis that failed during processing.
     */
    public function failed(): static
    {
        return $this->pending()->state(fn (): array => [
            'analysis_status' => AnalysisStatus::Failed,
            'analysis_started_at' => now()->subMinute(),
            'analysis_completed_at' => now(),
            'analysis_error' => 'Analysis could not be completed. Please try again.',
        ]);
    }

    /**
     * A representative perception result, in the shape the ML service returns:
     * the classifier's land cover, the model provenance, and the terrain, soil
     * and climate the service sampled around the photo's coordinates.
     *
     * @return array<string, mixed>
     */
    private function perception(int $width, int $height, float $latitude, float $longitude): array
    {
        return [
            'land_cover' => [
                'dense_vegetation' => fake()->numberBetween(20, 55),
                'sparse_vegetation' => fake()->numberBetween(10, 35),
                'bare_soil' => fake()->numberBetween(8, 28),
                'charred_soil' => fake()->numberBetween(2, 25),
                'water' => fake()->numberBetween(0, 8),
                'built_area' => fake()->numberBetween(0, 6),
                'other' => fake()->numberBetween(0, 5),
            ],
            'confidence' => [
                'overall' => 0.88,
                'vegetation' => 0.92,
                'bare_soil' => 0.85,
                'water' => 0.87,
            ],
            // Left for the engine to derive from the cover mix, with the
            // provenance the colour-heuristic fallback reports.
            'fire_severity' => [
                'confidence' => 0.81,
                'method' => 'colour-heuristic',
            ],
            'model' => ['name' => 'color-histogram', 'version' => '0.1.0'],
            'image' => ['width' => $width, 'height' => $height],
            'terrain' => $this->terrainContext($latitude, $longitude),
            'soil' => $this->soilContext($latitude, $longitude),
            'climate' => $this->climateContext($latitude, $longitude),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function terrainContext(float $latitude, float $longitude): array
    {
        return [
            'available' => true,
            'source' => [
                'id' => 'demnas',
                'label' => 'DEMNAS',
                'dataset' => 'DEMNAS 8 m',
                'provider' => 'Badan Informasi Geospasial',
                'service_url' => 'https://tanahair.indonesia.go.id',
                'portal_url' => 'https://tanahair.indonesia.go.id/portal-web',
            ],
            'sampled_at' => ['latitude' => $latitude, 'longitude' => $longitude],
            'profile' => [
                'elevation_m' => (float) fake()->numberBetween(10, 1200),
                'slope_deg' => fake()->randomFloat(1, 0.5, 22),
                'aspect_deg' => fake()->randomFloat(1, 0, 359),
                'hillshade' => fake()->randomFloat(3, 0.2, 0.9),
                'ruggedness_m' => fake()->randomFloat(2, 0.5, 12),
                'resolution_m' => 8.0,
                'window' => ['rows' => 5, 'cols' => 5, 'spacing_x_m' => 8.0, 'spacing_y_m' => 8.0],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function soilContext(float $latitude, float $longitude): array
    {
        $className = fake()->randomElement(array_keys(self::SOIL_TEXTURES));
        $texture = self::SOIL_TEXTURES[$className];
        $composition = [
            'class_name' => $className,
            'sand_pct' => $texture['sand'],
            'silt_pct' => $texture['silt'],
            'clay_pct' => $texture['clay'],
        ];

        return [
            'available' => true,
            'source' => [
                'id' => 'soilgrids',
                'label' => 'ISRIC SoilGrids',
                'dataset' => 'SoilGrids 250 m',
                'provider' => 'ISRIC World Soil Information',
                'service_url' => 'https://rest.isric.org',
                'portal_url' => 'https://soilgrids.org',
            ],
            'sampled_at' => ['latitude' => $latitude, 'longitude' => $longitude],
            'depth_cm' => 30,
            'resolution_m' => 250.0,
            'profile' => [
                'texture' => $composition,
                'subsoil_texture' => $composition,
                'ph' => fake()->randomFloat(2, 4.8, 7.2),
                'organic_carbon_g_kg' => fake()->randomFloat(1, 8, 35),
                'nitrogen_g_kg' => fake()->randomFloat(2, 0.8, 2.6),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function climateContext(float $latitude, float $longitude): array
    {
        // A monsoonal year with a short dry tail, jittered per analysis but
        // kept internally consistent: the annual total is the sum of the
        // months, and the dry/wet counts are read off the same array.
        $base = [270, 250, 290, 240, 150, 90, 55, 40, 70, 160, 240, 285];
        $monthly = array_map(fn (int $mm): float => (float) max(20, $mm + fake()->numberBetween(-15, 15)), $base);

        $dryMonths = [];
        foreach ($monthly as $index => $mm) {
            if ($mm < 60.0) {
                $dryMonths[] = self::MONTHS[$index];
            }
        }

        $driest = array_search(min($monthly), $monthly, true);
        $wettest = array_search(max($monthly), $monthly, true);
        $meanTemperature = fake()->randomFloat(1, 22, 30);

        return [
            'available' => true,
            'source' => [
                'id' => 'nasa-power',
                'label' => 'NASA POWER',
                'dataset' => 'NASA POWER climatology',
                'provider' => 'NASA Langley Research Center',
                'service_url' => 'https://power.larc.nasa.gov/api',
                'portal_url' => 'https://power.larc.nasa.gov',
            ],
            'sampled_at' => ['latitude' => $latitude, 'longitude' => $longitude],
            'profile' => [
                'annual_rainfall_mm' => round(array_sum($monthly), 1),
                'monthly_rainfall_mm' => array_values($monthly),
                'dry_months' => count($dryMonths),
                'dry_month_names' => $dryMonths,
                'wet_months' => count(array_filter($monthly, fn (float $mm): bool => $mm >= 200.0)),
                'driest_month' => ['month' => self::MONTHS[$driest], 'rainfall_mm' => $monthly[$driest]],
                'wettest_month' => ['month' => self::MONTHS[$wettest], 'rainfall_mm' => $monthly[$wettest]],
                'mean_temperature_c' => $meanTemperature,
                'mean_daily_max_c' => round($meanTemperature + 4.2, 1),
                'mean_humidity_pct' => fake()->randomFloat(1, 65, 90),
                'topsoil_wetness_pct' => fake()->randomFloat(1, 30, 80),
            ],
        ];
    }

    /**
     * Build a representative recommendation result payload.
     *
     * @return array<string, mixed>
     */
    private function sampleRecommendationResult(): array
    {
        return [
            'priority' => 'high',
            'recommendations' => [
                [
                    'action' => 'native_reforestation',
                    'title' => 'Reforestation',
                    'priority' => 'high',
                    'reason' => 'Large areas of low vegetation density were detected.',
                    'recommended_actions' => ['Native tree planting', 'Assisted natural regeneration', 'Vegetation corridor development'],
                    'objective' => 'Restore canopy cover and reconnect fragmented vegetation.',
                ],
            ],
        ];
    }
}
