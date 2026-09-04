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
}
