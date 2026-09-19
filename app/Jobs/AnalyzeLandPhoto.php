<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\LandStatus;
use App\Models\LandAnalysis;
use App\Services\Carbon\CarbonCreditAssessor;
use App\Services\Land\LandIntelligenceEngine;
use App\Services\Ml\LandAnalysisClient;
use App\Services\Restoration\RestorationRecommendationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs the land-analysis pipeline for a single uploaded photo.
 *
 * Pipeline: mark processing -> perceive land cover and burn severity (ML) ->
 * derive land intelligence -> generate rule-based restoration recommendations
 * -> persist -> move the land's status with the evidence -> refresh the carbon
 * estimate. Heavy work is kept off the web request; the frontend polls the
 * analysis status until it reaches a terminal state.
 */
class AnalyzeLandPhoto implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(public LandAnalysis $analysis) {}

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
        CarbonCreditAssessor $carbon,
    ): void {
        $this->analysis->forceFill([
            'analysis_status' => AnalysisStatus::Processing,
            'analysis_started_at' => now(),
            'analysis_error' => null,
        ])->save();

        $disk = Storage::disk(LandAnalysis::IMAGE_DISK);

        if (! $disk->exists($this->analysis->image_path)) {
            throw new RuntimeException("Land photo [{$this->analysis->image_path}] is missing from storage.");
        }

        $land = $this->analysis->land;

        $perception = $client->analyze(
            (string) $disk->get($this->analysis->image_path),
            $this->analysis->image_filename,
            $this->analysis->latitude,
            $this->analysis->longitude,
        );

        // The land's declared soil and rainfall travel with the analysis so the
        // recommendation engine can use them wherever a dataset was not
        // reachable for this point.
        $result = $intelligence->derive($perception, [
            'soil_texture' => $land?->soil_texture,
            'rainfall_mm' => $land?->rainfall_mm,
        ]);

        $recommendation = $recommendations->recommend($result);

        $this->analysis->recordResult($result, $recommendation);

        $this->syncLandStatus();

        // The estimate is derived from the newest evidence, so it is refreshed
        // by the same pipeline that produced it.
        $carbon->refreshFor($this->analysis->fresh(['land']));
    }

    /**
     * Move the land's status with its evidence: a land that has been analysed
     * is being monitored, and one whose health score reached the healthy band
     * counts as recovered.
     */
    private function syncLandStatus(): void
    {
        $land = $this->analysis->land;

        if ($land === null) {
            return;
        }

        $land->forceFill([
            'status' => LandStatus::fromHealthScore($this->analysis->land_health_score),
            'data_status' => 'measured',
        ])->save();
    }

    /**
     * Mark the analysis as failed once all retry attempts are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        $analysis = LandAnalysis::find($this->analysis->id);

        $analysis?->forceFill([
            'analysis_status' => AnalysisStatus::Failed,
            'analysis_completed_at' => now(),
            'analysis_error' => 'Analisis tidak bisa diselesaikan. Coba unggah foto lain atau hubungi admin.',
        ])->save();
    }
}
