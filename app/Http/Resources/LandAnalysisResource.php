<?php

namespace App\Http\Resources;

use App\Enums\HealthStatus;
use App\Models\LandAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One analysis of a land photo.
 *
 * The same resource serves the polling endpoint, the monitoring timeline, and
 * the analysis detail view; `detailed()` adds the full land intelligence and
 * recommendation payload, and `withProgress()` injects the comparison against
 * the previous period, which only the caller knows (it needs the neighbouring
 * analyses to work it out).
 *
 * @mixin LandAnalysis
 */
class LandAnalysisResource extends JsonResource
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $progress = null;

    private bool $detailed = false;

    /**
     * @param  array<string, mixed>|null  $progress
     */
    public function withProgress(?array $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function detailed(bool $detailed = true): static
    {
        $this->detailed = $detailed;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->analysis_status;
        $severity = $this->burn_severity;
        $intelligence = $this->analysis_result ?? [];
        $health = HealthStatus::fromEngineStatus($intelligence['land_health']['status'] ?? null)
            ?? HealthStatus::fromScore($this->land_health_score);

        $payload = [
            'id' => $this->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_message' => $status->message(),
            'is_finished' => $status->isFinished(),

            'captured_at' => $this->monitoringDate()?->toIso8601String(),
            'notes' => $this->notes,
            'capture_source' => $this->capture_source,

            'image' => [
                'url' => $this->image_url,
                'filename' => $this->image_filename,
                'width' => $this->image_width,
                'height' => $this->image_height,
                'file_size' => $this->file_size,
            ],

            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'has_reliable_geolocation' => $this->hasReliableGeolocation(),
            ],

            'burn_severity' => $severity === null ? null : [
                'level' => $severity->value,
                'label' => $severity->shortLabel(),
                'color' => $severity->color(),
                'score' => $this->burn_severity_score,
                'confidence' => $this->burn_severity_confidence,
                'method' => $intelligence['burn_severity']['method'] ?? null,
                'reason' => $intelligence['burn_severity']['reason'] ?? null,
                'evidence' => $intelligence['burn_severity']['evidence'] ?? null,
            ],

            'metrics' => [
                'health_score' => $this->land_health_score,
                'health_status' => $health?->value,
                'health_label' => $health?->label(),
                'health_color' => $health?->color(),
                'vegetation_percentage' => $this->vegetation_percentage,
                'bare_soil_percentage' => $this->bare_soil_percentage,
                'charred_percentage' => $this->charred_percentage,
                'water_percentage' => $this->water_percentage,
                'degraded_percentage' => $this->degraded_percentage,
                'restoration_potential' => $this->restoration_potential,
            ],

            'progress' => $this->progress,

            'model' => [
                'name' => $this->ai_model,
                'version' => $this->ai_model_version,
                'task' => $intelligence['model']['task'] ?? null,
                'weights' => $intelligence['model']['weights'] ?? null,
                'device' => $intelligence['model']['device'] ?? null,
            ],

            'error' => $this->analysis_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $payload;
        }

        $payload['land'] = [
            'id' => $this->land_id,
            'name' => $this->whenLoaded('land', fn (): ?string => $this->land?->name, null),
        ];
        $payload['land_intelligence'] = $intelligence;
        $payload['recommendation'] = $this->recommendation_result;
        $payload['detections'] = $intelligence['detections'] ?? [];

        return $payload;
    }
}
