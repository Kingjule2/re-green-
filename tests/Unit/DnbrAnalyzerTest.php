<?php

namespace Tests\Unit;

use App\Services\Analysis\DnbrAnalyzer;
use PHPUnit\Framework\TestCase;

class DnbrAnalyzerTest extends TestCase
{
    public function test_it_calculates_dnbr_and_excludes_cloudy_pixels_from_area(): void
    {
        $result = (new DnbrAnalyzer)->analyze([
            'before' => [
                'nir' => [[8000, 8000], [8000, 8000]],
                'swir' => [[2000, 2000], [2000, 2000]],
                'reflectance_scale' => 10000,
                'acquired_at' => '2025-01-01',
            ],
            'after' => [
                'nir' => [[3000, 8000], [3000, 3000]],
                'swir' => [[5000, 2000], [5000, 5000]],
                'reflectance_scale' => 10000,
                'acquired_at' => '2025-02-01',
            ],
            'clear_mask' => [[true, false], [true, true]],
            'crs' => 'EPSG:4326',
            'pixel_size_m' => 10,
            'data_status' => 'demo',
            'source' => 'fixture',
        ]);

        $this->assertSame('valid', $result['status']);
        $this->assertSame(3, $result['valid_pixel_count']);
        $this->assertSame(1, $result['nodata_pixel_count']);
        $this->assertEqualsWithDelta(0.85, $result['statistics']['mean_dnbr'], 0.001);
        $this->assertEqualsWithDelta(0.03, $result['area_ha']['total_valid'], 0.0001);
        $this->assertSame('demo', $result['provenance']['data_status']);
        $this->assertSame(null, $result['dnbr'][0][1]);
    }

    public function test_it_requires_both_images_and_compatible_band_grids(): void
    {
        $result = (new DnbrAnalyzer)->analyze([
            'before' => [
                'nir' => [[0.8, 0.8]],
                'swir' => [[0.2, 0.2]],
                'reflectance_scale' => 1,
            ],
            'after' => [
                'nir' => [[0.3]],
                'swir' => [[0.5]],
                'reflectance_scale' => 1,
            ],
        ]);

        $this->assertSame('rejected', $result['status']);
        $this->assertContains('before_after_grid_mismatch', $result['problems']);
        $this->assertNull($result['statistics']['mean_dnbr']);
    }

    public function test_it_does_not_claim_area_without_pixel_size_and_crs(): void
    {
        $result = (new DnbrAnalyzer)->analyze([
            'before' => [
                'nir' => [[0.8]],
                'swir' => [[0.2]],
                'reflectance_scale' => 1,
            ],
            'after' => [
                'nir' => [[0.3]],
                'swir' => [[0.5]],
                'reflectance_scale' => 1,
            ],
        ]);

        $this->assertSame('valid', $result['status']);
        $this->assertNull($result['area_ha']['total_valid']);
        $this->assertContains('pixel_size_m', $result['area_missing']);
        $this->assertContains('crs', $result['area_missing']);
    }
}
