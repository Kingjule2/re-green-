<?php

namespace App\Http\Resources;

use App\Models\FieldObservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FieldObservation */
class FieldObservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'land_id' => $this->land_id,
            'parcel_id' => $this->parcel_id,
            'observed_at' => $this->observed_at?->toDateString(),
            'observer' => $this->observer,
            'source' => $this->source,
            'data_status' => $this->data_status,
            'burn_severity' => $this->burn_severity,
            'land_cover' => $this->land_cover,
            'slope_deg' => $this->slope_deg,
            'soil_ph' => $this->soil_ph,
            'soil_texture' => $this->soil_texture,
            'soil_moisture_pct' => $this->soil_moisture_pct,
            'erosion_signs' => $this->erosion_signs,
            'fire_date' => $this->fire_date?->toDateString(),
            'values' => $this->values,
            'notes' => $this->notes,
        ];
    }
}
