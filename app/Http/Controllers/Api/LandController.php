<?php

namespace App\Http\Controllers\Api;

use App\Enums\LandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SelectCropRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\StoreFieldObservationRequest;
use App\Http\Requests\StoreLandRequest;
use App\Http\Requests\StoreParcelRequest;
use App\Http\Requests\UpdateLandRequest;
use App\Http\Resources\AssetResource;
use App\Http\Resources\FieldObservationResource;
use App\Http\Resources\LandAnalysisResource;
use App\Http\Resources\LandResource;
use App\Http\Resources\ParcelResource;
use App\Models\Asset;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Models\Parcel;
use App\Services\Agriculture\CropCatalog;
use App\Services\Carbon\CarbonCreditAssessor;
use App\Services\Geospatial\GeoJsonGeometry;
use App\Services\Geospatial\VerticalDatumNormalizer;
use App\Services\Land\MonitoringProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Land endpoints: the farmer's own restoration sites and the aggregate the
 * pemda/NGO and corporate accounts read.
 *
 * The list and detail responses are deliberately different sizes — a dashboard
 * over many lands only needs the headline, while a land page needs its whole
 * monitoring history — so the detail endpoint composes the timeline and the
 * carbon screening on top of the same resource.
 */
class LandController extends Controller
{
    public function __construct(
        private readonly GeoJsonGeometry $geometry,
        private readonly VerticalDatumNormalizer $vertical,
        private readonly MonitoringProgress $progress,
        private readonly CarbonCreditAssessor $carbon,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $lands = $this->visibleLands($request)
            ->with(['user:id,name,organization', 'latestAnalysis', 'carbonAssessment'])
            ->withCount('analyses')
            ->latest()
            ->limit(200)
            ->get();

        return LandResource::collection($lands)->additional([
            'meta' => [
                'count' => $lands->count(),
                'area_ha' => round((float) $lands->sum('area_ha'), 2),
                'analyses' => (int) $lands->sum('analyses_count'),
                'average_health_score' => $this->averageHealth($lands),
                'scope' => $request->user()->viewsAllLands() ? 'all' : 'own',
            ],
        ]);
    }

    public function store(StoreLandRequest $request): JsonResponse
    {
        $this->authorize('create', Land::class);

        $validated = $request->validated();

        try {
            $geo = $this->geometry->validate($validated['geometry'] ?? null, $validated['crs'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['geometry' => [$exception->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $land = Land::create([
            ...$validated,
            'user_id' => $request->user()->id,
            // A land starts as registered; the first analysed photo moves it to
            // "monitoring" and a recovered land health score moves it to
            // "restored" (see AnalyzeLandPhoto).
            'status' => LandStatus::Planned,
            'data_status' => 'demo',
            'objective' => $validated['objective'] ?? 'restoration',
            'geometry' => $geo['geometry'],
            'area_ha' => $validated['area_ha'] ?? $geo['area_ha'],
        ]);

        return LandResource::make($land)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * A land page: identity plus its monitoring history, before/after
     * comparison, and the carbon screening for that land.
     */
    public function show(Land $land): JsonResponse
    {
        $this->authorize('view', $land);

        $land->load([
            'user:id,name,organization',
            'latestAnalysis',
            'carbonAssessment',
            'parcels',
            'observations',
            'assets',
            'analysisRuns.reviews',
            'analyses',
        ])->loadCount('analyses');

        $analyses = $land->analyses
            ->sortByDesc(fn ($analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
            ->values();

        $timeline = $this->progress->timeline($land->analyses);

        return response()->json([
            'data' => [
                ...LandResource::make($land)->resolve(),
                'analyses' => $analyses
                    ->map(fn ($analysis) => LandAnalysisResource::make($analysis)
                        ->withProgress($timeline[$analysis->id] ?? null))
                    ->values()
                    ->all(),
                'progress' => $this->progress->for($land),
                'carbon' => $this->carbon->assess($land),
                'planting_plan' => $this->plantingPlan($land),
            ],
        ]);
    }

    public function update(UpdateLandRequest $request, Land $land): LandResource
    {
        $this->authorize('update', $land);

        $validated = $request->validated();

        if (array_key_exists('geometry', $validated) || array_key_exists('crs', $validated)) {
            $geo = $this->geometry->validate($validated['geometry'] ?? $land->geometry, $validated['crs'] ?? $land->crs);
            $validated['geometry'] = $geo['geometry'];
            $validated['area_ha'] ??= $geo['area_ha'];
        }

        $land->update($validated);

        return LandResource::make($land->load(['latestAnalysis', 'carbonAssessment'])->loadCount('analyses'));
    }

    /**
     * Remove a land, its monitoring rows (cascade) and the images those rows
     * point at — an orphaned photo in storage is still the farmer's data.
     */
    public function destroy(Land $land): Response
    {
        $this->authorize('delete', $land);

        $disk = Storage::disk(LandAnalysis::IMAGE_DISK);

        foreach ($land->analyses()->pluck('image_path') as $path) {
            if (filled($path)) {
                $disk->delete($path);
            }
        }

        $land->delete();

        return response()->noContent();
    }

    /**
     * The farmer picks one of the recommended crops for this land; the planting
     * guide that comes back is the catalog's guide for that crop.
     */
    public function selectCrop(SelectCropRequest $request, Land $land): JsonResponse
    {
        $this->authorize('manage', $land);

        $crop = CropCatalog::find($request->validated('crop_id'));

        $land->forceFill([
            'selected_crop_id' => $crop['id'],
            'selected_crop_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'crop' => $this->cropSummary($crop),
                'planting_guide' => $crop['planting_guide'] ?? null,
            ],
        ]);
    }

    /**
     * The monitoring series behind the progress chart.
     */
    public function progress(Land $land): JsonResponse
    {
        $this->authorize('view', $land);

        $land->loadMissing('analyses');

        return response()->json(['data' => $this->progress->for($land)]);
    }

    public function parcels(Land $land): AnonymousResourceCollection
    {
        $this->authorize('view', $land);

        return ParcelResource::collection($land->parcels()->latest()->get());
    }

    public function storeParcel(StoreParcelRequest $request, Land $land): JsonResponse
    {
        $this->authorize('update', $land);

        $validated = $request->validated();

        try {
            $geo = $this->geometry->validate($validated['geometry'] ?? null, $validated['crs'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['geometry' => [$exception->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $parcel = $land->parcels()->create([
            ...$validated,
            'data_status' => $validated['data_status'] ?? $land->data_status,
            'geometry' => $geo['geometry'],
            'area_ha' => $validated['area_ha'] ?? $geo['area_ha'],
        ]);

        return ParcelResource::make($parcel)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function observations(Land $land): AnonymousResourceCollection
    {
        $this->authorize('view', $land);

        return FieldObservationResource::collection($land->observations()->latest('observed_at')->get());
    }

    public function storeObservation(StoreFieldObservationRequest $request, Land $land): JsonResponse
    {
        $this->authorize('update', $land);

        $validated = $request->validated();
        $parcel = $this->parcelForLand($validated['parcel_id'] ?? null, $land);

        if (($validated['parcel_id'] ?? null) !== null && $parcel === null) {
            return response()->json(['message' => 'The selected parcel does not belong to this land.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $observation = $land->observations()->create([
            ...$validated,
            'parcel_id' => $parcel?->id,
        ]);

        return FieldObservationResource::make($observation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function assets(Land $land): AnonymousResourceCollection
    {
        $this->authorize('view', $land);

        return AssetResource::collection($land->assets()->latest()->get());
    }

    public function storeAsset(StoreAssetRequest $request, Land $land): JsonResponse
    {
        $this->authorize('update', $land);

        $validated = $request->validated();
        $parcel = $this->parcelForLand($validated['parcel_id'] ?? null, $land);

        if (($validated['parcel_id'] ?? null) !== null && $parcel === null) {
            return response()->json(['message' => 'The selected parcel does not belong to this land.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->file('asset');
        $path = $file->store('lands/'.$land->id.'/assets', Asset::DISK);
        $verticalInput = $validated['vertical'] ?? null;
        $vertical = $verticalInput === null
            ? ['status' => 'incomplete', 'missing' => ['h', 'N', 'd_vertikal_ke_tanah', 'vertical_reference', 'geoid_model'], 'problems' => []]
            : $this->vertical->normalize($verticalInput);

        if ($vertical['status'] === 'rejected') {
            Storage::disk(Asset::DISK)->delete($path);

            return response()->json([
                'message' => 'Vertical metadata is incompatible with a ground-height calculation.',
                'vertical' => $vertical,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $asset = $land->assets()->create([
            'parcel_id' => $parcel?->id,
            'type' => $validated['type'],
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'captured_at' => $validated['captured_at'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'data_status' => $validated['data_status'],
            'status' => 'uploaded',
            'provenance' => array_filter([
                'source' => $validated['source'] ?? null,
                'dataset' => $validated['dataset'] ?? null,
                'provider' => $validated['provider'] ?? null,
                'service_url' => $validated['service_url'] ?? null,
            ]),
            'vertical_metadata' => $vertical,
        ]);

        return AssetResource::make($asset)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The lands an account may read.
     *
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
     * @param  Collection<int, Land>  $lands
     */
    private function averageHealth(Collection $lands): ?int
    {
        $scores = $lands
            ->map(fn (Land $land): ?int => $land->latestAnalysis?->land_health_score)
            ->filter(fn (?int $score): bool => $score !== null);

        return $scores->isEmpty() ? null : (int) round($scores->average());
    }

    /**
     * The planting guide for the crop this land's farmer selected.
     *
     * @return array<string, mixed>|null
     */
    private function plantingPlan(Land $land): ?array
    {
        if (blank($land->selected_crop_id)) {
            return null;
        }

        $crop = CropCatalog::find((string) $land->selected_crop_id);

        if ($crop === null) {
            return null;
        }

        return [
            'crop' => $this->cropSummary($crop),
            'selected_at' => $land->selected_crop_at?->toIso8601String(),
            'planting_guide' => $crop['planting_guide'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $crop
     * @return array<string, mixed>
     */
    private function cropSummary(array $crop): array
    {
        return [
            'id' => $crop['id'],
            'name' => $crop['name'],
            'icon' => $crop['icon'],
            'category' => $crop['category'],
            'color' => $crop['color'],
            'description' => $crop['description'],
            'growing_period' => $crop['growing_period'],
            'market_value' => $crop['market_value'],
        ];
    }

    private function parcelForLand(?int $parcelId, Land $land): ?Parcel
    {
        if ($parcelId === null) {
            return null;
        }

        return $land->parcels()->whereKey($parcelId)->first();
    }
}
