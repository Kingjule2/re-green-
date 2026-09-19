<?php

namespace Tests\Unit;

use App\Services\Geospatial\VerticalDatumNormalizer;
use PHPUnit\Framework\TestCase;

class VerticalDatumNormalizerTest extends TestCase
{
    public function test_it_converts_units_and_computes_orthometric_and_ground_height(): void
    {
        $result = (new VerticalDatumNormalizer)->normalize([
            'h' => 299.96,
            'h_unit' => 'm',
            'N' => 60.96,
            'N_unit' => 'm',
            'd_vertikal_ke_tanah' => 200,
            'd_unit' => 'ft',
            'vertical_reference' => 'ellipsoidal',
            'geoid_model' => 'EGM2008',
            'distance_reference' => 'takeoff_to_ground_vertical',
            'surface' => 'ground',
            'epoch' => '2026-01-15',
            'source' => 'survey',
            'uncertainty_h' => 3,
            'uncertainty_N' => 2,
            'uncertainty_d' => 1,
            'uncertainty_unit' => 'm',
        ]);

        $this->assertSame('valid', $result['status']);
        $this->assertEqualsWithDelta(239.0, $result['normalized']['H_orthometric_m'], 0.01);
        $this->assertEqualsWithDelta(178.04, $result['normalized']['H_ground_m'], 0.01);
        $this->assertSame('h - N', $result['calculation_trace']['H_orthometric']['formula']);
        $this->assertSame('H - d_vertikal_ke_tanah', $result['calculation_trace']['H_ground']['formula']);
        $this->assertEqualsWithDelta(sqrt(13), $result['uncertainty_m']['H_orthometric'], 0.001);
        $this->assertEqualsWithDelta(sqrt(14), $result['uncertainty_m']['H_ground'], 0.001);
    }

    public function test_it_reports_incomplete_vertical_reference_without_inventing_zero_values(): void
    {
        $result = (new VerticalDatumNormalizer)->normalize([
            'h' => 299.96,
            'h_unit' => 'm',
            'd_vertikal_ke_tanah' => 80,
            'd_unit' => 'm',
            'surface' => 'canopy',
        ]);

        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['normalized']['H_orthometric_m']);
        $this->assertNull($result['normalized']['H_ground_m']);
        $this->assertContains('geoid_model', $result['missing']);
        $this->assertContains('vertical_reference', $result['missing']);
        $this->assertContains('surface_ground_reference', $result['problems']);
    }

    public function test_it_rejects_a_non_vertical_or_incompatible_measurement(): void
    {
        $result = (new VerticalDatumNormalizer)->normalize([
            'h' => 100,
            'h_unit' => 'm',
            'N' => 10,
            'N_unit' => 'm',
            'vertical_reference' => 'ellipsoidal',
            'geoid_model' => 'EGM2008',
            'd_vertikal_ke_tanah' => 25,
            'd_unit' => 'm',
            'distance_reference' => 'sloped_distance',
            'surface' => 'ground',
        ]);

        $this->assertSame('rejected', $result['status']);
        $this->assertNull($result['normalized']['H_ground_m']);
        $this->assertContains('distance_must_be_vertical', $result['problems']);
    }
}
