<?php

namespace App\Http\Resources;

use App\Models\DroneAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DroneAnalysis
 */
class DroneAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->analysis_status;

        return [
            'id' => $this->id,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_message' => $status->message(),
            'is_finished' => $status->isFinished(),

            'image' => [
                'url' => $this->image_url,
                'filename' => $this->image_filename,
                'width' => $this->image_width,
                'height' => $this->image_height,
                'file_size' => $this->file_size,
            ],

            'metadata' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'area_name' => $this->area_name,
                'survey_date' => $this->survey_date?->toDateString(),
                'drone_model' => $this->drone_model,
                'flight_altitude' => $this->flight_altitude,
                'image_type' => $this->image_type,
                'has_reliable_geolocation' => $this->hasReliableGeolocation(),
            ],

            // Denormalized headline metrics for quick list rendering.
            'land_health_score' => $this->land_health_score,
            'vegetation_percentage' => $this->vegetation_percentage,
            'bare_soil_percentage' => $this->bare_soil_percentage,
            'water_percentage' => $this->water_percentage,
            'degraded_percentage' => $this->degraded_percentage,
            'restoration_potential' => $this->restoration_potential,

            'ai_model' => $this->ai_model,
            'ai_model_version' => $this->ai_model_version,

            // Full AI output (null until the analysis completes).
            'analysis' => $this->analysis_result,
            'recommendation' => $this->recommendation_result,
            'error' => $this->analysis_error,

            'started_at' => $this->analysis_started_at?->toIso8601String(),
            'completed_at' => $this->analysis_completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
