<?php

namespace App\Http\Controllers\Api;

use App\Enums\CarbonEligibility;
use App\Enums\CarbonSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCarbonSubmissionRequest;
use App\Models\Land;
use App\Services\Carbon\CarbonCreditAssessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Carbon-credit module: the estimate and eligibility of one land, the
 * application a farmer files, and the portfolio an institution or corporate
 * account can see across every land.
 *
 * The module stops at the partner's door on purpose — the platform produces the
 * evidence package, the certification itself is somebody else's process.
 */
class CarbonController extends Controller
{
    public function __construct(private readonly CarbonCreditAssessor $carbon) {}

    public function show(Land $land): JsonResponse
    {
        $this->authorize('view', $land);

        return response()->json(['data' => $this->payload($land)]);
    }

    /**
     * File an application for a land. The estimate is refreshed first, so the
     * application is filed against the numbers that exist at submission time.
     */
    public function submit(StoreCarbonSubmissionRequest $request, Land $land): JsonResponse
    {
        $this->authorize('manage', $land);

        $assessment = $this->carbon->refresh($land);

        $assessment->forceFill([
            'partner' => $request->validated('partner'),
            'contact_name' => $request->validated('contact_name'),
            'contact_email' => $request->validated('contact_email'),
            'offered_area_ha' => $request->validated('offered_area_ha') ?? $land->area_ha,
            'notes' => $request->validated('notes'),
            'submission_status' => CarbonSubmissionStatus::Submitted,
            'submitted_at' => now(),
        ])->save();

        return response()->json(['data' => $this->payload($land)])
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * How much carbon sits in the pipeline across every land this account may
     * read, and where those lands stand in the screening.
     */
    public function portfolio(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->viewsAllLands(),
            403,
            'Portofolio karbon hanya tersedia untuk akun pemerintah/NGO dan korporasi.',
        );

        $lands = Land::query()
            ->with(['user:id,name,organization', 'latestAnalysis', 'carbonAssessment', 'analyses'])
            ->get();

        $rows = $lands->map(function (Land $land): array {
            $assessment = $this->carbon->assess($land);

            return [
                'land_id' => $land->id,
                'name' => $land->name,
                'location_name' => $land->location_name,
                'area_ha' => $land->area_ha,
                'owner' => $land->user?->name,
                'organization' => $land->user?->organization,
                'eligibility_status' => $assessment['eligibility']['status'],
                'eligibility_label' => $assessment['eligibility']['label'],
                'submission_status' => $land->carbonAssessment?->submission_status->value,
                'submission_label' => $land->carbonAssessment?->submission_status->label(),
                'vegetation_cover_pct' => $assessment['vegetation_cover_pct'],
                'vegetation_class' => $assessment['vegetation_class'],
                'sequestration_tco2e_per_year' => $assessment['sequestration_tco2e_per_year'],
                'sequestration_5yr_tco2e' => $assessment['sequestration_5yr_tco2e'],
                'failed_checks' => array_column($assessment['eligibility']['failed'], 'label'),
            ];
        })->values();

        return response()->json([
            'data' => [
                'lands_total' => $rows->count(),
                'eligible' => $rows->where('eligibility_status', CarbonEligibility::Eligible->value)->count(),
                'not_yet' => $rows->where('eligibility_status', CarbonEligibility::NotYet->value)->count(),
                'ineligible' => $rows->where('eligibility_status', CarbonEligibility::Ineligible->value)->count(),
                'submitted' => $lands->filter(fn (Land $land): bool => $land->carbonAssessment?->isSubmitted() ?? false)->count(),
                'forwarded' => $lands->filter(fn (Land $land): bool => $land->carbonAssessment?->submission_status === CarbonSubmissionStatus::Forwarded)->count(),
                'pipeline_tco2e_per_year' => round((float) $rows->sum('sequestration_tco2e_per_year'), 2),
                'pipeline_5yr_tco2e' => round((float) $rows->sum('sequestration_5yr_tco2e'), 2),
                'by_region' => $this->byRegion($rows),
                'lands' => $rows->all(),
            ],
        ]);
    }

    /**
     * The estimate, the checklist, and the application as one payload.
     *
     * @return array<string, mixed>
     */
    private function payload(Land $land): array
    {
        $land->loadMissing(['analyses', 'carbonAssessment']);

        $assessment = $this->carbon->assess($land);
        $stored = $land->carbonAssessment;

        return [
            ...$assessment,
            'submission' => $stored === null ? null : [
                'status' => $stored->submission_status->value,
                'status_label' => $stored->submission_status->label(),
                'color' => $stored->submission_status->color(),
                'partner' => $stored->partner,
                'contact_name' => $stored->contact_name,
                'contact_email' => $stored->contact_email,
                'offered_area_ha' => $stored->offered_area_ha,
                'notes' => $stored->notes,
                'submitted_at' => $stored->submitted_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Carbon potential grouped by the region each land declared.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byRegion(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row): string => $row['location_name'] ?? 'Tanpa lokasi')
            ->map(fn (Collection $group, string $region): array => [
                'region' => $region,
                'lands' => $group->count(),
                'eligible' => $group->where('eligibility_status', CarbonEligibility::Eligible->value)->count(),
                'area_ha' => round((float) $group->sum('area_ha'), 2),
                'pipeline_tco2e_per_year' => round((float) $group->sum('sequestration_tco2e_per_year'), 2),
            ])
            ->sortByDesc('pipeline_tco2e_per_year')
            ->values()
            ->all();
    }
}
