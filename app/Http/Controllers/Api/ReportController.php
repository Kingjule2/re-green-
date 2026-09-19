<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportSnapshotResource;
use App\Models\Land;
use App\Models\ReportSnapshot;
use App\Services\Reporting\ReportPdfRenderer;
use App\Services\Reporting\ReportSnapshotBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports for the B2G/B2B side (and for a farmer who wants to document their
 * own land).
 *
 * A report is generated once and frozen: the snapshot is what gets downloaded,
 * so a number a regulator already read never changes under them. Downloads come
 * in PDF (the document that gets filed) and CSV (the rows a spreadsheet can
 * continue from).
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportSnapshotBuilder $builder,
        private readonly ReportPdfRenderer $pdf,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = $request->user()->reports()
            ->with('land:id,name')
            ->latest()
            ->limit(50)
            ->get();

        return ReportSnapshotResource::collection($reports);
    }

    /**
     * Freeze the current evidence into a new report version.
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $land = $this->scopeLand($request, $validated);

        $scope = $validated['scope'] === ReportSnapshot::SCOPE_LAND ? ReportSnapshot::SCOPE_LAND : ReportSnapshot::SCOPE_ALL;
        $lands = $scope === ReportSnapshot::SCOPE_LAND
            ? collect([$land])
            : $this->visibleLands($request)->with(['analyses', 'carbonAssessment', 'user'])->get();

        $version = $this->nextVersion($request, $scope, $land);

        $report = ReportSnapshot::create([
            'land_id' => $scope === ReportSnapshot::SCOPE_LAND ? $land->id : null,
            'user_id' => $request->user()->id,
            'scope' => $scope,
            'version' => $version,
            'title' => $validated['title'] ?? $this->defaultTitle($scope, $land, $version),
            'format' => 'pdf',
            'snapshot' => $this->builder->build($lands, $scope, $request->user()->name),
        ]);

        return ReportSnapshotResource::make($report)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(ReportSnapshot $report): ReportSnapshotResource
    {
        $this->authorizeReport(request()->user(), $report);

        return ReportSnapshotResource::make($report->load('land:id,name'))->detailed();
    }

    /**
     * The report as a PDF: what actually gets filed with a regulator or an ESG
     * auditor.
     */
    public function pdf(ReportSnapshot $report): Response
    {
        $this->authorizeReport(request()->user(), $report);

        $contents = $this->pdf->render($report);

        return response($contents, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->pdf->filename($report).'"',
            'Content-Length' => (string) strlen($contents),
        ]);
    }

    /**
     * The same frozen rows as a flat CSV, for whoever continues the analysis in
     * a spreadsheet.
     */
    public function csv(ReportSnapshot $report): StreamedResponse
    {
        $this->authorizeReport(request()->user(), $report);

        $snapshot = $report->snapshot;
        $filename = 'regreen-report-'.$report->id.'-v'.$report->version.'.csv';

        return response()->streamDownload(function () use ($snapshot): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['section', 'key', 'value']);
            $this->writeRows($stream, '', $snapshot);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(ReportSnapshot $report): Response
    {
        $this->authorizeReport(request()->user(), $report);

        $report->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function scopeLand(Request $request, array $validated): ?Land
    {
        if (($validated['scope'] ?? null) !== ReportSnapshot::SCOPE_LAND) {
            return null;
        }

        $land = Land::find($validated['land_id'] ?? null);

        abort_if($land === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Laporan satu lahan butuh land_id yang valid.');
        abort_unless($request->user()->can('view', $land), Response::HTTP_FORBIDDEN, 'Kamu tidak punya akses ke lahan ini.');

        return $land;
    }

    /**
     * @return Builder<Land>
     */
    private function visibleLands(Request $request): Builder
    {
        $query = Land::query();

        if (! $request->user()->viewsAllLands()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }

    /**
     * Versions are per account and per scope: an account's reports over one
     * land, and over all its lands, each count up independently.
     */
    private function nextVersion(Request $request, string $scope, ?Land $land): int
    {
        return ((int) $request->user()->reports()
            ->where('scope', $scope)
            ->when($scope === ReportSnapshot::SCOPE_LAND, fn ($query) => $query->where('land_id', $land?->id))
            ->max('version')) + 1;
    }

    private function defaultTitle(string $scope, ?Land $land, int $version): string
    {
        $subject = $scope === ReportSnapshot::SCOPE_LAND
            ? ($land?->name ?? 'Lahan')
            : 'Seluruh Lahan Terpantau';

        return sprintf('%s — laporan monitoring v%d', $subject, $version);
    }

    private function authorizeReport(?object $user, ReportSnapshot $report): void
    {
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);
        abort_unless($report->user_id === $user->id, Response::HTTP_FORBIDDEN, 'Laporan ini bukan milik akunmu.');
    }

    /** @param resource $stream */
    private function writeRows($stream, string $prefix, mixed $value): void
    {
        if (! is_array($value)) {
            fputcsv($stream, [$prefix, '', is_scalar($value) || $value === null ? $value : json_encode($value)]);

            return;
        }

        foreach ($value as $key => $child) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $this->writeRows($stream, $path, $child);
        }
    }
}
