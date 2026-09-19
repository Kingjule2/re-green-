<?php

namespace App\Http\Resources;

use App\Models\AnalysisReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnalysisReview */
class AnalysisReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'analysis_run_id' => $this->analysis_run_id,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'corrections' => $this->corrections,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
