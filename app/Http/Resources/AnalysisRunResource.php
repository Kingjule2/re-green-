<?php

namespace App\Http\Resources;

use App\Models\AnalysisRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnalysisRun */
class AnalysisRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'land_id' => $this->land_id,
            'asset_id' => $this->asset_id,
            'type' => $this->type,
            'status' => $this->status,
            'model' => [
                'name' => $this->model_name,
                'version' => $this->model_version,
            ],
            'provenance' => $this->provenance,
            'input_metadata' => $this->input_metadata,
            'result' => $this->result,
            'error' => $this->error,
            'reviews' => AnalysisReviewResource::collection($this->whenLoaded('reviews')),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
