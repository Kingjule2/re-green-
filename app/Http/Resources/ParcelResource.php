<?php

namespace App\Http\Resources;

use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Parcel */
class ParcelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'land_id' => $this->land_id,
            'name' => $this->name,
            'data_status' => $this->data_status,
            'crs' => $this->crs,
            'geometry' => $this->geometry,
            'area_ha' => $this->area_ha,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
