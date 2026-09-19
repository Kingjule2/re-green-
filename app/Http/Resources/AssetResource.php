<?php

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'land_id' => $this->land_id,
            'parcel_id' => $this->parcel_id,
            'type' => $this->type,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'data_status' => $this->data_status,
            'status' => $this->status,
            'provenance' => $this->provenance,
            'vertical_metadata' => $this->vertical_metadata,
            'url' => $this->url(),
        ];
    }
}
