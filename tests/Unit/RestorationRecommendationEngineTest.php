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
}
