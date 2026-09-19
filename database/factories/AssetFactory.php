<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Land;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'land_id' => Land::factory(),
            'parcel_id' => null,
            'type' => 'satellite_before',
            'path' => 'assets/demo.tif',
            'original_filename' => 'demo.tif',
            'mime_type' => 'image/tiff',
            'file_size' => 1024,
            'captured_at' => now(),
            'latitude' => null,
            'longitude' => null,
            'data_status' => 'demo',
            'status' => 'uploaded',
            'provenance' => ['source' => 'fixture'],
            'vertical_metadata' => null,
        ];
    }
}
