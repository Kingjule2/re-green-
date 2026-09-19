<?php

namespace Database\Factories;

use App\Enums\LandStatus;
use App\Models\Land;
use App\Services\Agriculture\CropCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Land>
 */
class LandFactory extends Factory
{
    protected $model = Land::class;

    /**
     * The Indonesian regencies ("kabupaten") the demo lands are scattered
     * across — one per burnt landscape the platform was built for.
     *
     * @var list<string>
     */
    private const LOCATIONS = [
        'Kab. Ogan Ilir',
        'Kab. Muaro Jambi',
        'Kab. Siak',
        'Kab. Kotawaringin Timur',
        'Kab. Kapuas',
        'Kab. Kutai Kartanegara',
        'Kab. Barito Kuala',
        'Kab. Tanah Laut',
        'Kab. Sumbawa',
        'Kab. Sikka',
    ];

    /**
     * Common names for the demo lands, so the seeded map does not read like
     * machine output.
     *
     * @var list<string>
     */
    private const NAMES = [
        'Lahan Cempaka',
        'Lahan Meranti',
        'Lahan Harapan',
        'Lahan Sejahtera',
        'Lahan Makmur',
        'Lahan Lestari',
        'Lahan Sungai Putih',
        'Lahan Bina Tani',
    ];

    /**
     * USDA texture classes a land may declare when the soil sampler cannot
     * answer — the same set the recommendation engine scores against.
     *
     * @var list<string>
     */
    private const SOIL_TEXTURES = ['Clay Loam', 'Loam', 'Sandy Loam', 'Silt Loam', 'Clay'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cropIds = array_column(CropCatalog::all(), 'id');

        // The map marker needs both halves of the point, so latitude and
        // longitude are decided together.
        $located = fake()->boolean(80);

        // Most lands have not picked a crop yet; when they have, it is one the
        // catalog actually offers, and the pick has a timestamp.
        $selectedCropId = fake()->optional(0.6)->randomElement($cropIds);

        return [
            'user_id' => null,
            'name' => fake()->randomElement(self::NAMES),
            'objective' => 'restoration',
            'status' => fake()->randomElement(LandStatus::cases()),
            'data_status' => 'demo',
            'location_name' => fake()->randomElement(self::LOCATIONS),
            'latitude' => $located ? fake()->latitude(-11, 6) : null,
            'longitude' => $located ? fake()->longitude(95, 141) : null,
            'soil_texture' => fake()->randomElement(self::SOIL_TEXTURES),
            'rainfall_mm' => fake()->numberBetween(1200, 3200),
            'crs' => null,
            'geometry' => null,
            'area_ha' => fake()->randomFloat(2, 0.5, 45),
            'fire_event_date' => fake()->optional(0.7)->dateTimeBetween('-3 years')?->format('Y-m-d'),
            'selected_crop_id' => $selectedCropId,
            'selected_crop_at' => $selectedCropId === null ? null : fake()->dateTimeBetween('-6 months'),
            'notes' => null,
        ];
    }
}
