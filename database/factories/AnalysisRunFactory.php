<?php

namespace Database\Factories;

use App\Models\AnalysisRun;
use App\Models\Land;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalysisRun>
 */
class AnalysisRunFactory extends Factory
{
    protected $model = AnalysisRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'land_id' => Land::factory(),
            'asset_id' => null,
            'type' => 'dnbr',
            'status' => 'completed',
            'input_metadata' => [],
            'result' => [],
            'provenance' => ['data_status' => 'demo'],
            'error' => null,
            'model_name' => 'dnbr-baseline',
            'model_version' => '0.1.0',
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }
}
