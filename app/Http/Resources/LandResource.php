<?php

namespace App\Http\Resources;

use App\Enums\HealthStatus;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Services\Agriculture\CropCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A land as the API shows it: identity, where it is, what the land declared
 * about its location, the headline of its latest analysis, the crop the farmer
 * picked, and the carbon screening.
 *
 * The heavy payloads (full analysis results, the monitoring series) live on the
 * land detail endpoint and on the analyses endpoints, so a list of lands stays
 * small enough for a dashboard.
 *
 * @mixin Land
 */
class LandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latest = $this->whenLoaded('latestAnalysis', fn (): ?LandAnalysis => $this->latestAnalysis, null);
        $carbon = $this->whenLoaded('carbonAssessment', fn () => $this->carbonAssessment, null);
        $health = HealthStatus::fromScore($latest?->land_health_score);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'objective' => $this->objective,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'data_status' => $this->data_status,
            'location_name' => $this->location_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'has_coordinates' => $this->hasCoordinates(),
            'area_ha' => $this->area_ha,
            'fire_event_date' => $this->fire_event_date?->toDateString(),
            'soil_texture' => $this->soil_texture,
            'rainfall_mm' => $this->rainfall_mm,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),

            'owner' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'organization' => $this->user->organization,
            ]),

            'analyses_count' => $this->whenCounted('analyses'),

            'latest_analysis' => $latest === null ? null : [
                'id' => $latest->id,
                'captured_at' => $latest->monitoringDate()?->toIso8601String(),
                'health_score' => $latest->land_health_score,
                'vegetation_percentage' => $latest->vegetation_percentage,
                'burn_severity' => $latest->burn_severity?->value,
                'burn_severity_label' => $latest->burn_severity?->shortLabel(),
                'model' => $latest->ai_model,
            ],

            'health' => $latest === null ? null : [
                'score' => $latest->land_health_score,
                'status' => $health?->value,
                'label' => $health?->label(),
                'color' => $health?->color(),
            ],

            'burn_severity' => $latest?->burn_severity === null ? null : [
                'level' => $latest->burn_severity->value,
                'label' => $latest->burn_severity->shortLabel(),
                'score' => $latest->burn_severity_score,
                'color' => $latest->burn_severity->color(),
            ],

            'vegetation_percentage' => $latest?->vegetation_percentage,

            'selected_crop' => $this->selectedCrop(),

            'carbon' => $carbon === null ? null : [
                'eligibility_status' => $carbon->eligibility_status->value,
                'eligibility_label' => $carbon->eligibility_status->label(),
                'eligibility_color' => $carbon->eligibility_status->color(),
                'submission_status' => $carbon->submission_status->value,
                'sequestration_tco2e_per_year' => $carbon->sequestration_tco2e_per_year,
                'calculated_at' => $carbon->calculated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * The crop the farmer picked, with the catalog metadata the UI shows next
     * to it.
     *
     * @return array<string, mixed>|null
     */
    private function selectedCrop(): ?array
    {
        if (blank($this->selected_crop_id)) {
            return null;
        }

        $crop = CropCatalog::find((string) $this->selected_crop_id);

        return [
            'id' => $this->selected_crop_id,
            'name' => $crop['name'] ?? $this->selected_crop_id,
            'icon' => $crop['icon'] ?? null,
            'category' => $crop['category'] ?? null,
            'color' => $crop['color'] ?? null,
            'growing_period' => $crop['growing_period'] ?? null,
            'selected_at' => $this->selected_crop_at?->toIso8601String(),
        ];
    }
}
