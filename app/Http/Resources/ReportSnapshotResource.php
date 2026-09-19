<?php

namespace App\Http\Resources;

use App\Models\ReportSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A frozen report.
 *
 * The list view needs the headline numbers and the links to the downloads; the
 * per-land rows are only sent when a single report is opened, because a report
 * over hundreds of lands would otherwise dominate every list response.
 *
 * @mixin ReportSnapshot
 */
class ReportSnapshotResource extends JsonResource
{
    private bool $detailed = false;

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
        $snapshot = $this->snapshot ?? [];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'scope' => $this->scope,
            'is_global' => $this->isGlobal(),
            'land_id' => $this->land_id,
            'land' => $this->whenLoaded('land', fn (): ?array => $this->land === null ? null : [
                'id' => $this->land->id,
                'name' => $this->land->name,
            ]),
            'version' => $this->version,
            'format' => $this->format,
            'generated_at' => $this->created_at?->toIso8601String(),
            'prepared_by' => $this->whenLoaded('user', fn (): ?string => $this->user?->name, null),

            'summary' => $snapshot['summary'] ?? null,
            'severity_mix' => $snapshot['severity_mix'] ?? [],
            'evidence' => $snapshot['evidence'] ?? null,
            'gaps' => $snapshot['gaps'] ?? [],
            'disclaimer' => $snapshot['disclaimer'] ?? null,

            'downloads' => [
                'pdf' => "/api/v1/reports/{$this->id}/pdf",
                'csv' => "/api/v1/reports/{$this->id}/csv",
            ],

            'lands' => $this->when($this->detailed, $snapshot['lands'] ?? []),
        ];
    }
}
