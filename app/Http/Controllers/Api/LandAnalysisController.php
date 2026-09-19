<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLandAnalysisRequest;
use App\Http\Resources\LandAnalysisResource;
use App\Jobs\AnalyzeLandPhoto;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Services\Geospatial\VerticalDatumNormalizer;
use App\Services\Land\MonitoringProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Photo analysis endpoints.
 *
 * A farmer uploads a photo of a land; the heavy work happens in
 * {@see AnalyzeLandPhoto} and the UI polls the analysis until it reaches a
 * terminal state. Repeated uploads for the same land are what builds the
 * monitoring series, so uploads are always addressed through the land.
 */
class LandAnalysisController extends Controller
{
    public function __construct(
        private readonly MonitoringProgress $progress,
        private readonly VerticalDatumNormalizer $vertical,
    ) {}

    /**
     * The monitoring history of one land, newest period first, each row
     * carrying its change against the previous period.
     */
    public function index(Land $land): JsonResponse
    {
        $this->authorize('view', $land);

        $land->load('analyses');
        $timeline = $this->progress->timeline($land->analyses);

        $analyses = $land->analyses
            ->sortByDesc(fn (LandAnalysis $analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
            ->values();

        return response()->json([
            'data' => $analyses
                ->map(fn (LandAnalysis $analysis) => LandAnalysisResource::make($analysis)
                    ->withProgress($timeline[$analysis->id] ?? null))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Accept a land photo, create the analysis record, and queue processing.
     */
    public function store(StoreLandAnalysisRequest $request, Land $land): JsonResponse
    {
        $this->authorize('create', [LandAnalysis::class, $land]);

        $file = $request->file('image');
        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $verticalInput = $request->validated('vertical');
        $vertical = $verticalInput === null
            ? ['status' => 'incomplete', 'missing' => ['h', 'N', 'd_vertikal_ke_tanah', 'vertical_reference', 'geoid_model'], 'problems' => []]
            : $this->vertical->normalize($verticalInput);

        if ($vertical['status'] === 'rejected') {
            return response()->json([
                'message' => 'Vertical metadata is incompatible with a ground-height calculation.',
                'vertical' => $vertical,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $path = $file->store('land-analyses/'.$land->id, LandAnalysis::IMAGE_DISK);

        $analysis = $land->analyses()->create([
            'user_id' => $request->user()->id,
            'image_path' => $path,
            'image_filename' => $file->getClientOriginalName(),
            'image_width' => $dimensions[0] ?? null,
            'image_height' => $dimensions[1] ?? null,
            'file_size' => $file->getSize(),
            // The photo's own point wins; otherwise the land's registered point
            // is what the ML service samples terrain, soil and climate for.
            'latitude' => $request->input('latitude') ?? $land->latitude,
            'longitude' => $request->input('longitude') ?? $land->longitude,
            'captured_at' => $request->date('captured_at') ?? now(),
            'capture_source' => $request->input('capture_source', 'phone'),
            'notes' => $request->input('notes'),
            'vertical_status' => $vertical['status'],
            'vertical_metadata' => $vertical,
            'analysis_status' => AnalysisStatus::Pending,
        ]);

        try {
            AnalyzeLandPhoto::dispatch($analysis);
        } catch (\Throwable $exception) {
            // A synchronous local queue can surface a model outage directly in
            // the upload request. Keep the polling contract intact: the photo
            // exists and its terminal state is failed, rather than returning a
            // generic 500 that hides the analysis row from the farmer.
            report($exception);

            $analysis->forceFill([
                'analysis_status' => AnalysisStatus::Failed,
                'analysis_completed_at' => now(),
                'analysis_error' => 'Analisis tidak bisa diselesaikan. Coba unggah foto lain atau hubungi admin.',
            ])->save();
        }

        $analysis->refresh();

        return LandAnalysisResource::make($analysis)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The current state and results of one analysis (polled by the UI).
     */
    public function show(LandAnalysis $analysis): LandAnalysisResource
    {
        $this->authorize('view', $analysis);

        $analysis->load('land');

        return LandAnalysisResource::make($analysis)
            ->withProgress($this->previousPeriodProgress($analysis))
            ->detailed();
    }

    /**
     * Delete an analysis and its stored photo. Deleting the newest period
     * simply returns the land to the evidence it still has; nothing is
     * recomputed here, so the next upload re-derives it.
     */
    public function destroy(LandAnalysis $analysis): Response
    {
        $this->authorize('delete', $analysis);

        if (filled($analysis->image_path)) {
            Storage::disk(LandAnalysis::IMAGE_DISK)->delete($analysis->image_path);
        }

        $analysis->delete();

        return response()->noContent();
    }

    /**
     * How this period compares with the one before it, for the same land.
     *
     * @return array<string, mixed>|null
     */
    private function previousPeriodProgress(LandAnalysis $analysis): ?array
    {
        $previous = $analysis->land?->analyses()
            ->whereKeyNot($analysis->id)
            ->where('analysis_status', AnalysisStatus::Completed->value)
            ->whereNotNull('vegetation_percentage')
            ->whereRaw('COALESCE(captured_at, created_at) < ?', [$analysis->monitoringDate()])
            ->orderByRaw('COALESCE(captured_at, created_at) DESC')
            ->first();

        return $this->progress->compare($previous, $analysis);
    }
}
