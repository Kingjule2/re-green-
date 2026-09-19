<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnalysisReviewRequest;
use App\Http\Requests\StoreDnbrAnalysisRequest;
use App\Http\Resources\AnalysisReviewResource;
use App\Http\Resources\AnalysisRunResource;
use App\Models\AnalysisRun;
use App\Models\Land;
use App\Services\Analysis\DnbrAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Remote-sensing (dNBR) analysis runs: the survey-grade burn-severity workflow
 * that predates the photo pipeline and is kept for teams that bring before/after
 * satellite imagery, plus the human review that accepts or corrects a run.
 */
class AnalysisRunController extends Controller
{
    public function __construct(private readonly DnbrAnalyzer $dnbr) {}

    public function storeDnbr(StoreDnbrAnalysisRequest $request, Land $land): JsonResponse
    {
        $this->authorize('update', $land);

        $input = $request->validated();
        $result = $this->dnbr->analyze($input);

        if ($result['status'] === 'rejected') {
            return response()->json([
                'message' => 'The dNBR input could not be aligned or validated.',
                'result' => $result,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = now();
        $run = $land->analysisRuns()->create([
            'type' => 'dnbr',
            'status' => $result['status'] === 'valid' ? 'completed' : 'needs_review',
            'input_metadata' => [
                'before_acquired_at' => $input['before']['acquired_at'] ?? null,
                'after_acquired_at' => $input['after']['acquired_at'] ?? null,
                'crs' => $input['crs'] ?? null,
                'pixel_size_m' => $input['pixel_size_m'] ?? null,
            ],
            'result' => $result,
            'provenance' => $result['provenance'],
            'model_name' => 'dnbr-baseline',
            'model_version' => '0.1.0',
            'started_at' => $now,
            'completed_at' => $now,
        ]);

        return AnalysisRunResource::make($run)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(AnalysisRun $analysisRun): AnalysisRunResource
    {
        $this->authorize('view', $analysisRun->land);

        $analysisRun->load('reviews');

        return AnalysisRunResource::make($analysisRun);
    }

    public function storeReview(StoreAnalysisReviewRequest $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->authorize('view', $analysisRun->land);

        $review = $analysisRun->reviews()->create([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
        ]);

        if ($review->decision === 'approved') {
            $analysisRun->update(['status' => 'approved']);
        } elseif (in_array($review->decision, ['needs_review', 'corrected'], true)) {
            $analysisRun->update(['status' => 'needs_review']);
        } elseif ($review->decision === 'rejected') {
            $analysisRun->update(['status' => 'rejected']);
        }

        return AnalysisReviewResource::make($review)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
