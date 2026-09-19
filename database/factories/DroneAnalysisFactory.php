<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Models\DroneAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DroneAnalysis>
 */
class DroneAnalysisFactory extends Factory
{
    /**
     * Define the model's default state: a completed analysis with coherent,
     * denormalized headline metrics backed by a representative result payload.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $width = fake()->numberBetween(3000, 6000);
        $height = (int) round($width * 2 / 3);

        $vegetation = fake()->numberBetween(35, 80);
        $bareSoil = fake()->numberBetween(5, 30);
        $water = fake()->numberBetween(0, 12);
        $degraded = fake()->numberBetween(5, 30);
        $score = fake()->numberBetween(45, 90);

        return [
            'user_id' => null,
            'project_id' => null,
            'image_path' => 'drone-analyses/'.fake()->uuid().'.jpg',
            'image_filename' => 'drone_land_'.fake()->numberBetween(1, 999).'.jpg',
            'image_width' => $width,
            'image_height' => $height,
            'file_size' => fake()->numberBetween(1_000_000, 40_000_000),
            'latitude' => fake()->optional()->latitude(-11, 6),
            'longitude' => fake()->optional()->longitude(95, 141),
            'area_name' => fake()->optional()->randomElement(['Sector Alpha', 'Peatland Zone 1', 'Coastal Sector', 'Area A']),
            'survey_date' => fake()->optional()->dateTimeBetween('-1 year')?->format('Y-m-d'),
            'drone_model' => fake()->optional()->randomElement(['DJI Mavic 3 Multispectral', 'DJI Phantom 4', 'senseFly eBee X']),
            'flight_altitude' => fake()->optional()->randomElement(['80 m', '120 m', '150 m']),
            'image_type' => fake()->optional()->randomElement(['RGB', 'Nadir', 'Multispectral']),
            'analysis_status' => AnalysisStatus::Completed,
            'analysis_started_at' => now()->subMinutes(2),
            'analysis_completed_at' => now()->subMinute(),
            'analysis_error' => null,
            'land_health_score' => $score,
            'vegetation_percentage' => $vegetation,
            'bare_soil_percentage' => $bareSoil,
            'water_percentage' => $water,
            'degraded_percentage' => $degraded,
            'restoration_potential' => fake()->randomElement(['low', 'medium', 'high']),
            'ai_model' => 'color-histogram',
            'ai_model_version' => '0.1.0',
            'analysis_result' => $this->sampleAnalysisResult($score, $vegetation, $bareSoil, $water, $degraded, $width, $height),
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
            'vegetation_percentage' => null,
            'bare_soil_percentage' => null,
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
     * Build a representative analysis result payload.
     *
     * @return array<string, mixed>
     */
    private function sampleAnalysisResult(
        int $score,
        int $vegetation,
        int $bareSoil,
        int $water,
        int $degraded,
        int $width,
        int $height,
    ): array {
        $status = match (true) {
            $score >= 80 => 'healthy',
            $score >= 60 => 'moderate',
            $score >= 40 => 'degraded',
            default => 'critical',
        };

        return [
            'land_health' => ['score' => $score, 'status' => $status],
            'land_cover' => [
                ['key' => 'dense_vegetation', 'label' => 'Dense Vegetation', 'color' => '#15803d', 'percentage' => (float) round($vegetation * 0.65, 1)],
                ['key' => 'sparse_vegetation', 'label' => 'Sparse Vegetation', 'color' => '#84cc16', 'percentage' => (float) round($vegetation * 0.35, 1)],
                ['key' => 'bare_soil', 'label' => 'Bare Soil', 'color' => '#b45309', 'percentage' => (float) $bareSoil],
                ['key' => 'water', 'label' => 'Water', 'color' => '#0ea5e9', 'percentage' => (float) $water],
                ['key' => 'other', 'label' => 'Other', 'color' => '#94a3b8', 'percentage' => (float) max(0, 100 - $vegetation - $bareSoil - $water)],
            ],
            'metrics' => [
                'vegetation_coverage' => (float) $vegetation,
                'bare_soil' => (float) $bareSoil,
                'water_presence' => (float) $water,
                'degraded_area' => (float) $degraded,
                'restoration_potential' => 'high',
                'estimated_area_hectares' => null,
            ],
            'issues' => [
                ['type' => 'vegetation_loss', 'title' => 'Vegetation Loss', 'severity' => 'high', 'affected_area' => (float) $degraded, 'confidence' => 0.92, 'description' => 'Significant areas show sparse or absent vegetation coverage.'],
            ],
            'confidence' => ['overall' => 0.9, 'vegetation_detection' => 0.92],
            'model' => ['name' => 'color-histogram', 'version' => '0.1.0'],
            'image' => ['width' => $width, 'height' => $height],
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
