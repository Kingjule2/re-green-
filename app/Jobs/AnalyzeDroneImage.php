<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\DroneAnalysis;
use App\Services\Land\LandIntelligenceEngine;
use App\Services\Ml\LandAnalysisClient;
use App\Services\Restoration\RestorationRecommendationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs the drone land-analysis pipeline for a single {@see DroneAnalysis}.
 *
 * Pipeline: mark processing -> perceive land cover (ML) -> derive land
 * intelligence -> generate restoration recommendations -> persist -> complete.
 * Heavy work is intentionally kept off the web request; the frontend polls the
 * analysis status until it reaches a terminal state.
 */
class AnalyzeDroneImage implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public DroneAnalysis $analysis) {}

    /**
     * Backoff (seconds) between retries when the ML service is unavailable.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * Execute the job.
     */
    public function handle(
        LandAnalysisClient $client,
        LandIntelligenceEngine $intelligence,
        RestorationRecommendationEngine $recommendations,
    ): void {
        $this->analysis->forceFill([
            'analysis_status' => AnalysisStatus::Processing,
            'analysis_started_at' => now(),
            'analysis_error' => null,
        ])->save();

        $disk = Storage::disk(DroneAnalysis::IMAGE_DISK);

        if (! $disk->exists($this->analysis->image_path)) {
            throw new RuntimeException("Drone image [{$this->analysis->image_path}] is missing from storage.");
        }

        $perception = $client->analyze(
            (string) $disk->get($this->analysis->image_path),
            $this->analysis->image_filename,
        );

        $result = $intelligence->derive($perception);
        $recommendation = $recommendations->recommend($result);

        $this->analysis->forceFill([
            'analysis_status' => AnalysisStatus::Completed,
            'analysis_completed_at' => now(),
            'analysis_error' => null,
            'land_health_score' => $result['land_health']['score'],
            'vegetation_percentage' => $result['metrics']['vegetation_coverage'],
            'bare_soil_percentage' => $result['metrics']['bare_soil'],
            'water_percentage' => $result['metrics']['water_presence'],
            'degraded_percentage' => $result['metrics']['degraded_area'],
            'restoration_potential' => $result['metrics']['restoration_potential'],
            'ai_model' => $result['model']['name'],
            'ai_model_version' => $result['model']['version'],
            'analysis_result' => $result,
            'recommendation_result' => $recommendation,
        ])->save();
    }

    /**
     * Mark the analysis as failed once all retry attempts are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        $analysis = DroneAnalysis::find($this->analysis->id);

        $analysis?->forceFill([
            'analysis_status' => AnalysisStatus::Failed,
            'analysis_completed_at' => now(),
            'analysis_error' => 'Analysis could not be completed. Please try again.',
        ])->save();
    }
}
