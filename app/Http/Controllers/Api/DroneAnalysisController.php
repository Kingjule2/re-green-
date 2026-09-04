<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDroneAnalysisRequest;
use App\Http\Resources\DroneAnalysisResource;
use App\Jobs\AnalyzeDroneImage;
use App\Models\DroneAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Drone land analysis API.
 *
 * SECURITY: these endpoints are currently unauthenticated because the app has
 * no auth layer installed yet. Before production, protect them with auth
 * middleware and scope every query to the authenticated user.
 */
class DroneAnalysisController extends Controller
{
    /**
     * List previous analyses (most recent first) for the analysis history view.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $analyses = DroneAnalysis::query()
            ->when($request->user(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->limit(50)
            ->get();

        return DroneAnalysisResource::collection($analyses);
    }

    /**
     * Accept a drone image, create the analysis record, and queue processing.
     */
    public function store(StoreDroneAnalysisRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $path = $file->store('drone-analyses', DroneAnalysis::IMAGE_DISK);

        $analysis = DroneAnalysis::create([
            'user_id' => $request->user()?->id,
            'project_id' => $request->integer('project_id') ?: null,
            'image_path' => $path,
            'image_filename' => $file->getClientOriginalName(),
            'image_width' => $dimensions[0] ?? null,
            'image_height' => $dimensions[1] ?? null,
            'file_size' => $file->getSize(),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'area_name' => $request->input('area_name'),
            'survey_date' => $request->input('survey_date'),
            'drone_model' => $request->input('drone_model'),
            'flight_altitude' => $request->input('flight_altitude'),
            'image_type' => $request->input('image_type'),
            'analysis_status' => AnalysisStatus::Pending,
        ]);

        AnalyzeDroneImage::dispatch($analysis);

        return DroneAnalysisResource::make($analysis)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Return the current state and results of a single analysis (polled by the UI).
     */
    public function show(DroneAnalysis $analysis): DroneAnalysisResource
    {
        return DroneAnalysisResource::make($analysis);
    }

    /**
     * Delete an analysis and its stored image.
     */
    public function destroy(DroneAnalysis $analysis): Response
    {
        if (filled($analysis->image_path)) {
            Storage::disk(DroneAnalysis::IMAGE_DISK)->delete($analysis->image_path);
        }

        $analysis->delete();

        return response()->noContent();
    }
}
