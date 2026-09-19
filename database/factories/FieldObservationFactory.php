<?php

namespace Database\Factories;

use App\Models\FieldObservation;
use App\Models\Land;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldObservation>
 */
class FieldObservationFactory extends Factory
{
    protected $model = FieldObservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'land_id' => Land::factory(),
            'observed_at' => fake()->date(),
            'observer' => fake()->name(),
            'source' => 'field-form',
            'data_status' => 'measured',
            'burn_severity' => 'moderate',
            'land_cover' => null,
            'slope_deg' => null,
            'soil_ph' => null,
            'soil_texture' => null,
            'soil_moisture_pct' => null,
            'erosion_signs' => null,
            'fire_date' => null,
            'values' => null,
            'notes' => null,
        ];
    }
}
