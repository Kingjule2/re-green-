<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Enums\BurnSeverity;
use App\Enums\UserRole;
use App\Jobs\AnalyzeLandPhoto;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The photo-analysis API: a farmer uploads a monitoring period for one of their
 * lands, polls the analysis while it runs, reads the land's monitoring history
 * and deletes a period.
 *
 * The ML service is faked at the HTTP boundary; everything downstream of it (the
 * intelligence engine, the recommendations and the persistence) is real code.
 */
class LandAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The analysis routes carry the session, so a mutating call needs the CSRF
     * token the Blade layout hands `api.js` — exactly what the SPA sends.
     */
    private const CSRF_TOKEN = 'regreen-test-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-CSRF-TOKEN', self::CSRF_TOKEN);
        $this->withSession(['_token' => self::CSRF_TOKEN]);
    }

    /**
     * A representative ML land-analysis response.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fakePerception(array $overrides = []): array
    {
        return array_replace([
            'land_cover' => [
                'dense_vegetation' => 40,
                'sparse_vegetation' => 20,
                'bare_soil' => 25,
                'charred_soil' => 5,
                'water' => 5,
                'built_area' => 3,
                'other' => 2,
            ],
            'confidence' => ['overall' => 0.9, 'vegetation' => 0.92, 'bare_soil' => 0.84, 'water' => 0.8],
            'detections' => [
                ['label' => 'charred_soil', 'confidence' => 0.87, 'box' => [0.12, 0.34, 0.22, 0.18]],
            ],
            'image' => ['width' => 5472, 'height' => 3648],
            'model' => [
                'name' => 'color-histogram',
                'version' => '0.2.0',
                'task' => 'detect',
                'weights' => 'models/regreen-burn-yolov8n.pt',
                'device' => 'cpu',
            ],
        ], $overrides);
    }

    /**
     * A terrain context as the ML service reports it, sampled from BIG's DEMNAS.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fakeTerrain(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'source' => [
                'id' => 'demnas',
                'label' => 'DEMNAS (BIG)',
                'dataset' => 'DEMNAS - DEM Nasional Indonesia, 0.27 arc-second (~8 m)',
                'provider' => 'Badan Informasi Geospasial (BIG)',
                'service_url' => 'https://geoservices.big.go.id/raster/rest/services/DEMNAS/DEM_Indonesia/ImageServer',
                'portal_url' => 'https://tanahair.indonesia.go.id/portal-web/unduh/demnas',
            ],
            'sampled_at' => ['latitude' => -7.53, 'longitude' => 110.45],
            'profile' => [
                'elevation_m' => 1840.5,
                'slope_deg' => 28.4,
                'aspect_deg' => 148.0,
                'hillshade' => 0.31,
                'ruggedness_m' => 12.6,
                'resolution_m' => 8.3,
            ],
        ], $overrides);
    }

    /**
     * A soil context carrying the real SoilGrids values for Mount Merapi.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fakeSoil(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'source' => [
                'id' => 'soilgrids',
                'label' => 'ISRIC SoilGrids',
                'dataset' => 'ISRIC SoilGrids v2.0 (250 m)',
                'provider' => 'ISRIC - World Soil Information',
                'service_url' => 'https://rest.isric.org/soilgrids/v2.0/properties/query',
                'portal_url' => 'https://soilgrids.org',
            ],
            'sampled_at' => ['latitude' => -7.53, 'longitude' => 110.45],
            'depth_cm' => 5,
            'resolution_m' => 250.0,
            'profile' => [
                'texture' => ['class_name' => 'Clay Loam', 'sand_pct' => 33.8, 'silt_pct' => 31.8, 'clay_pct' => 34.3],
                'subsoil_texture' => ['class_name' => 'Clay Loam', 'sand_pct' => 33.7, 'silt_pct' => 31.7, 'clay_pct' => 34.6],
                'ph' => 5.3,
                'organic_carbon_g_kg' => 103.6,
                'nitrogen_g_kg' => 5.77,
            ],
        ], $overrides);
    }

    /**
     * A climate context carrying the real NASA POWER normals for Mount Merapi.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fakeClimate(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'reason' => null,
            'source' => [
                'id' => 'nasa-power',
                'label' => 'NASA POWER',
                'dataset' => 'NASA POWER agroclimatology climatology',
                'provider' => 'NASA Langley Research Center (POWER Project)',
                'service_url' => 'https://power.larc.nasa.gov/api/temporal/climatology/point',
                'portal_url' => 'https://power.larc.nasa.gov/data-access-viewer/',
            ],
            'sampled_at' => ['latitude' => -7.53, 'longitude' => 110.45],
            'profile' => [
                'annual_rainfall_mm' => 2093.2,
                'monthly_rainfall_mm' => ['JAN' => 345.7, 'JUL' => 53.6, 'AUG' => 31.9],
                'dry_months' => 2,
                'dry_month_names' => ['JUL', 'AUG'],
                'wet_months' => 8,
                'driest_month' => ['month' => 'AUG', 'rainfall_mm' => 31.9],
                'wettest_month' => ['month' => 'JAN', 'rainfall_mm' => 345.7],
                'mean_temperature_c' => 25.0,
                'mean_daily_max_c' => 38.7,
                'mean_humidity_pct' => 82.5,
                'topsoil_wetness_pct' => 77.0,
            ],
        ], $overrides);
    }

    private function fakeImage(): UploadedFile
    {
        // create() avoids the GD dependency that image() requires.
        return UploadedFile::fake()->create('lahan_utara_001.jpg', 8000, 'image/jpeg');
    }

    private function farmer(): User
    {
        return User::factory()->create(['role' => UserRole::Farmer]);
    }

    /**
     * A land owned by the given account.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function landOwnedBy(User $owner, array $attributes = []): Land
    {
        return Land::factory()->for($owner)->create($attributes);
    }

    public function test_upload_stores_image_and_queues_analysis(): void
    {
        Queue::fake();
        Storage::fake('public');

        $farmer = $this->farmer();
        // The land's own point is somewhere else, so the photo's point winning
        // is observable.
        $land = $this->landOwnedBy($farmer, ['latitude' => -7.0, 'longitude' => 110.0]);
        $capturedAt = now()->subDays(12)->toDateString();

        $response = $this->actingAs($farmer)->post("/api/v1/lands/{$land->id}/analyses", [
            'image' => $this->fakeImage(),
            'captured_at' => $capturedAt,
            'capture_source' => 'drone',
            'notes' => 'Sisi utara lereng.',
            'latitude' => -6.4025,
            'longitude' => 106.7942,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AnalysisStatus::Pending->value)
            ->assertJsonPath('data.status_label', 'Pending')
            ->assertJsonPath('data.is_finished', false)
            ->assertJsonPath('data.notes', 'Sisi utara lereng.')
            ->assertJsonPath('data.capture_source', 'drone')
            ->assertJsonPath('data.location.has_reliable_geolocation', true)
            ->assertJsonPath('data.image.filename', 'lahan_utara_001.jpg');

        $analysis = LandAnalysis::sole();
        $this->assertSame(AnalysisStatus::Pending, $analysis->analysis_status);
        $this->assertSame($land->id, $analysis->land_id);
        $this->assertSame($farmer->id, $analysis->user_id);
        $this->assertSame($capturedAt, $analysis->monitoringDate()->toDateString());
        $this->assertSame(-6.4025, $analysis->latitude);
        $this->assertSame(106.7942, $analysis->longitude);
        Storage::disk('public')->assertExists($analysis->image_path);
        $this->assertStringContainsString($analysis->image_path, (string) $response->json('data.image.url'));

        Queue::assertPushed(AnalyzeLandPhoto::class);
    }

    public function test_land_lists_the_last_completed_evidence_while_a_new_upload_is_pending(): void
    {
        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $completed = LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subDays(2),
        ]);
        LandAnalysis::factory()->for($land)->pending()->create([
            'captured_at' => now()->subDay(),
        ]);

        $this->actingAs($farmer)
            ->getJson('/api/v1/lands')
            ->assertOk()
            ->assertJsonPath('data.0.latest_analysis.id', $completed->id);

        $this->actingAs($farmer)
            ->getJson("/api/v1/lands/{$land->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_analysis.id', $completed->id);
    }

    public function test_upload_runs_pipeline_to_completion(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->fakePerception())]);

        $farmer = $this->farmer();
        // Nothing was declared about this land either, so the analysis has to
        // report the missing context instead of inventing it.
        $land = $this->landOwnedBy($farmer, [
            'latitude' => null,
            'longitude' => null,
            'soil_texture' => null,
            'rainfall_mm' => null,
        ]);

        // The queue runs inline in tests, so the upload response already holds
        // the finished analysis.
        $this->actingAs($farmer)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertCreated()
            ->assertJsonPath('data.status', AnalysisStatus::Completed->value)
            ->assertJsonPath('data.is_finished', true);

        $analysis = LandAnalysis::sole();

        $this->assertSame(AnalysisStatus::Completed, $analysis->analysis_status);
        $this->assertNotNull($analysis->land_health_score);
        $this->assertNotNull($analysis->analysis_completed_at);
        $this->assertArrayHasKey('land_health', $analysis->analysis_result);
        $this->assertArrayHasKey('recommendations', $analysis->recommendation_result);
        $this->assertSame('color-histogram', $analysis->ai_model);

        // The headline metrics are denormalized onto the row, not only kept in
        // the JSON result.
        $this->assertSame($analysis->analysis_result['land_health']['score'], $analysis->land_health_score);
        $this->assertSame($analysis->analysis_result['burn_severity']['level'], $analysis->burn_severity->value);
        $this->assertNotNull($analysis->vegetation_percentage);
        $this->assertNotNull($analysis->restoration_potential);

        // No coordinates were supplied, so no DEM was sampled and the analysis
        // must say so rather than imply flat or unknown terrain.
        $this->assertFalse($analysis->analysis_result['terrain']['available']);
        $this->assertFalse($analysis->analysis_result['agriculture']['assessable']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/land-analysis'));

        // The polled analysis reports the completed state and the results.
        $state = $this->actingAs($farmer)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.status', AnalysisStatus::Completed->value)
            ->assertJsonPath('data.metrics.health_score', $analysis->land_health_score)
            ->assertJsonPath('data.burn_severity.level', $analysis->burn_severity->value)
            ->assertJsonPath('data.model.name', 'color-histogram')
            ->assertJsonPath('data.model.version', '0.2.0')
            ->assertJsonPath('data.model.task', 'detect')
            ->assertJsonPath('data.model.weights', 'models/regreen-burn-yolov8n.pt')
            ->assertJsonPath('data.model.device', 'cpu')
            ->assertJsonPath('data.error', null);

        // JSON drops the fraction of a whole number, which no consumer can
        // observe, so the percentages are compared by value.
        $this->assertEquals($analysis->vegetation_percentage, $state->json('data.metrics.vegetation_percentage'));
    }

    public function test_pipeline_samples_the_environment_context_for_the_lands_registered_point(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->fakePerception([
            'terrain' => $this->fakeTerrain(),
            'soil' => $this->fakeSoil(),
            'climate' => $this->fakeClimate(),
        ]))]);

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer, ['latitude' => -7.53, 'longitude' => 110.45]);

        // The upload carries no coordinates of its own, so the point the farmer
        // registered for the land is what the ML service must sample.
        $this->actingAs($farmer)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertCreated()
            ->assertJsonPath('data.location.latitude', -7.53)
            ->assertJsonPath('data.location.longitude', 110.45)
            ->assertJsonPath('data.location.has_reliable_geolocation', true);

        $analysis = LandAnalysis::sole();

        // The survey point has to reach the DEM sampler, or the context it
        // returns would describe somewhere else entirely.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v1/land-analysis')) {
                return false;
            }

            // A multipart body is recorded as a list of name/contents parts.
            $fields = [];
            foreach ($request->data() as $part) {
                if (is_array($part) && isset($part['name'], $part['contents'])) {
                    $fields[$part['name']] = $part['contents'];
                }
            }

            return (float) ($fields['latitude'] ?? 0) === -7.53
                && (float) ($fields['longitude'] ?? 0) === 110.45;
        });

        $result = $analysis->analysis_result;

        $this->assertTrue($result['terrain']['available']);
        $this->assertSame('demnas', $result['terrain']['source']['id']);
        $this->assertSame(1840.5, $result['terrain']['profile']['elevation_m']);
        $this->assertSame(28.4, $result['terrain']['profile']['slope_deg']);
        $this->assertSame('highland', $result['terrain']['elevation_band']['key']);
        $this->assertSame('steep', $result['terrain']['slope_class']);
        $this->assertSame('SE', $result['terrain']['aspect_label']);

        $this->assertTrue($result['soil']['available']);
        $this->assertSame('Clay Loam', $result['soil']['profile']['texture']['class_name']);
        $this->assertSame(5.3, $result['soil']['profile']['ph']);

        $this->assertTrue($result['climate']['available']);
        $this->assertSame(2093.2, $result['climate']['profile']['annual_rainfall_mm']);
        $this->assertSame(['JUL', 'AUG'], $result['climate']['profile']['dry_month_names']);

        // The three contexts together drive the agriculture assessment.
        $agriculture = $result['agriculture'];

        $this->assertTrue($agriculture['assessable']);
        $this->assertSame('low', $agriculture['suitability']);
        $this->assertSame('Clay Loam', $agriculture['soil_texture']);
        $this->assertTrue($agriculture['ranking_available']);
        $this->assertSame(10, $agriculture['crop_count']);
        $this->assertNotEmpty($agriculture['crops']);
        $this->assertSame([], $agriculture['missing_inputs']);

        // Every published input was measured, so the ranking saw all of them.
        // Compared by value: whole numbers come back as ints after the JSON
        // column round trip, which no consumer can observe.
        $this->assertEquals([
            'elevation' => 1840.5,
            'slope' => 28.4,
            'temperature' => 25.0,
            'rainfall' => 2093.2,
            'soil_ph' => 5.3,
            'soil_texture' => 'Clay Loam',
            'soil_moisture' => 77.0,
            'land_health' => $result['land_health']['score'],
        ], $agriculture['inputs']);

        $this->assertSame(2, $agriculture['rainfall']['dry_months']);
        $this->assertSame('high', $agriculture['rainfall']['erosivity']);

        $this->assertContains('slope_instability', array_column($result['issues'], 'type'));

        $actions = array_column($analysis->recommendation_result['recommendations'], 'action');
        $this->assertContains('slope_soil_conservation', $actions);
        $this->assertContains('slope_bioengineering', $actions);
        $this->assertContains('crop_recommendation', $actions);
        $this->assertContains('rainfall_management', $actions);

        // The JSON column round trip must survive, and the detail endpoint must
        // expose it together with the land it belongs to and the detections.
        $this->actingAs($farmer)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.land.id', $land->id)
            ->assertJsonPath('data.land.name', $land->name)
            ->assertJsonPath('data.land_intelligence.terrain.slope_class', 'steep')
            ->assertJsonPath('data.land_intelligence.soil.profile.texture.class_name', 'Clay Loam')
            ->assertJsonPath('data.land_intelligence.climate.profile.dry_months', 2)
            ->assertJsonPath(
                'data.land_intelligence.terrain.source.portal_url',
                'https://tanahair.indonesia.go.id/portal-web/unduh/demnas',
            )
            ->assertJsonPath('data.detections.0.label', 'charred_soil')
            ->assertJsonPath('data.detections.0.box', [0.12, 0.34, 0.22, 0.18]);
    }

    public function test_pipeline_survives_an_unavailable_dem(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->fakePerception(['terrain' => [
            'available' => false,
            'reason' => 'DEMNAS (BIG): DEMNAS could not be reached: ConnectTimeout',
            'source' => null,
            'sampled_at' => null,
            'profile' => null,
        ]]))]);

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer, ['latitude' => -7.53, 'longitude' => 110.45]);

        $this->actingAs($farmer)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertCreated();

        $analysis = LandAnalysis::sole();

        $this->assertSame(AnalysisStatus::Completed, $analysis->analysis_status);
        $this->assertFalse($analysis->analysis_result['terrain']['available']);
        $this->assertStringContainsString('could not be reached', $analysis->analysis_result['terrain']['reason']);
        $this->assertContains('vegetation_loss', array_column($analysis->analysis_result['issues'], 'type'));
    }

    public function test_upload_requires_an_image(): void
    {
        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $this->actingAs($farmer)
            ->postJson("/api/v1/lands/{$land->id}/analyses", ['notes' => 'Tanpa foto.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('land_analyses', 0);
    }

    public function test_upload_rejects_unsupported_formats(): void
    {
        Storage::fake('public');

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $this->actingAs($farmer)->post("/api/v1/lands/{$land->id}/analyses", [
            'image' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('land_analyses', 0);
    }

    public function test_upload_rejects_a_photo_larger_than_the_limit(): void
    {
        Storage::fake('public');

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $this->actingAs($farmer)->post("/api/v1/lands/{$land->id}/analyses", [
            'image' => UploadedFile::fake()->create('huge.jpg', 51 * 1024, 'image/jpeg'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_upload_rejects_partial_photo_coordinates(): void
    {
        Storage::fake('public');

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $this->actingAs($farmer)
            ->post("/api/v1/lands/{$land->id}/analyses", [
                'image' => $this->fakeImage(),
                'latitude' => -7.0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->assertDatabaseCount('land_analyses', 0);
    }

    public function test_show_returns_analysis_state_and_results(): void
    {
        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);
        $analysis = LandAnalysis::factory()->for($land)->create();

        $this->actingAs($farmer)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $analysis->id)
            ->assertJsonPath('data.status', AnalysisStatus::Completed->value)
            ->assertJsonPath('data.is_finished', true)
            ->assertJsonPath('data.land.id', $land->id)
            ->assertJsonPath('data.land_intelligence.land_health.score', $analysis->analysis_result['land_health']['score'])
            ->assertJsonPath('data.metrics.health_score', $analysis->analysis_result['land_health']['score'])
            ->assertJsonPath('data.burn_severity.level', $analysis->burn_severity->value)
            ->assertJsonPath('data.burn_severity.score', $analysis->burn_severity_score)
            ->assertJsonPath('data.image.url', Storage::disk('public')->url($analysis->image_path))
            ->assertJsonPath('data.image.filename', $analysis->image_filename)
            ->assertJsonPath('data.error', null)
            // The first period of a land has nothing to be compared against.
            ->assertJsonPath('data.progress', null);
    }

    public function test_index_lists_a_lands_monitoring_periods_newest_first(): void
    {
        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $oldest = LandAnalysis::factory()->for($land)->create(['captured_at' => now()->subDays(30)]);
        $middle = LandAnalysis::factory()->for($land)->create(['captured_at' => now()->subDays(20)]);
        $newest = LandAnalysis::factory()->for($land)->create(['captured_at' => now()->subDays(10)]);

        // A period of a different land must never leak into this history.
        LandAnalysis::factory()->create(['captured_at' => now()->subDay()]);

        $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/analyses")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id)
            // Each period carries its change against the one before it.
            ->assertJsonPath('data.0.progress.compared_to.id', $middle->id)
            ->assertJsonPath('data.1.progress.compared_to.id', $oldest->id)
            ->assertJsonPath('data.2.progress', null);
    }

    public function test_destroy_removes_analysis_and_image(): void
    {
        Storage::fake('public');

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);
        $analysis = LandAnalysis::factory()->for($land)->create(['image_path' => 'land-analyses/sample.jpg']);
        Storage::disk('public')->put('land-analyses/sample.jpg', 'bytes');

        $this->actingAs($farmer)->deleteJson("/api/v1/analyses/{$analysis->id}")->assertNoContent();

        $this->assertDatabaseMissing('land_analyses', ['id' => $analysis->id]);
        Storage::disk('public')->assertMissing('land-analyses/sample.jpg');
    }

    public function test_pipeline_failure_marks_analysis_failed(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(['message' => 'model unavailable'], 500)]);

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);
        $analysis = LandAnalysis::factory()->for($land)->pending()->create(['image_path' => 'land-analyses/sample.jpg']);
        Storage::disk('public')->put('land-analyses/sample.jpg', 'bytes');

        try {
            AnalyzeLandPhoto::dispatchSync($analysis);
        } catch (\Throwable) {
            // The job rethrows after marking the record failed; that is expected.
        }

        $failed = $analysis->fresh();

        $this->assertSame(AnalysisStatus::Failed, $failed->analysis_status);
        $this->assertNotNull($failed->analysis_error);

        // Polling must land on the failure with a plain-language message rather
        // than hanging on "pending" forever.
        $this->actingAs($farmer)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.status', AnalysisStatus::Failed->value)
            ->assertJsonPath('data.is_finished', true)
            ->assertJsonPath('data.error', $failed->analysis_error);
    }

    public function test_sync_upload_returns_a_finished_failure_when_the_model_is_unavailable(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(['message' => 'model unavailable'], 500)]);

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $response = $this->actingAs($farmer)->post("/api/v1/lands/{$land->id}/analyses", [
            'image' => $this->fakeImage(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AnalysisStatus::Failed->value)
            ->assertJsonPath('data.is_finished', true)
            ->assertJsonPath('data.error', 'Analisis tidak bisa diselesaikan. Coba unggah foto lain atau hubungi admin.');
    }

    public function test_fire_severity_reported_by_the_model_is_stored_and_exposed(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->fakePerception([
            'fire_severity' => [
                'available' => true,
                'reason' => null,
                'level' => 'moderate',
                'label' => 'Moderate',
                'score' => 52,
                'confidence' => 0.81,
                'method' => 'yolov8',
                'evidence' => [
                    'charred_soil_pct' => 18.0,
                    'bare_soil_pct' => 26.0,
                    'vegetation_pct' => 44.0,
                    'detections' => ['charred_soil' => 3, 'vegetation_regrowth' => 1],
                ],
            ],
        ]))]);

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $this->actingAs($farmer)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertCreated();

        $analysis = LandAnalysis::sole();

        $this->assertSame(BurnSeverity::Moderate, $analysis->burn_severity);
        $this->assertSame(52, $analysis->burn_severity_score);
        $this->assertSame(0.81, $analysis->burn_severity_confidence);

        $severity = $this->actingAs($farmer)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.burn_severity.level', 'moderate')
            ->assertJsonPath('data.burn_severity.label', 'Sedang')
            ->assertJsonPath('data.burn_severity.score', 52)
            ->assertJsonPath('data.burn_severity.confidence', 0.81)
            ->assertJsonPath('data.burn_severity.method', 'yolov8')
            ->assertJsonPath('data.burn_severity.evidence.detections.charred_soil', 3);

        // JSON drops the fraction of a whole number, which no consumer can
        // observe, so the percentage is compared by value.
        $this->assertEquals($analysis->charred_percentage, $severity->json('data.metrics.charred_percentage'));
    }

    public function test_fire_severity_missing_from_the_model_is_derived_from_the_land_cover(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->fakePerception([
            'land_cover' => [
                'dense_vegetation' => 5,
                'sparse_vegetation' => 10,
                'bare_soil' => 15,
                'charred_soil' => 60,
                'water' => 5,
                'built_area' => 3,
                'other' => 2,
            ],
        ]))]);

        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $this->actingAs($farmer)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertCreated();

        $analysis = LandAnalysis::sole();

        // The same area-weighted formula the detector uses: charred 100, bare
        // soil 60, regrowth 20, unburned canopy 0.
        $this->assertSame(BurnSeverity::High, $analysis->burn_severity);
        $this->assertSame(79, $analysis->burn_severity_score);

        $severity = $this->actingAs($farmer)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.burn_severity.level', 'high')
            ->assertJsonPath('data.burn_severity.method', 'cover-derived');

        // JSON drops the fraction of a whole number, which no consumer can
        // observe, so the percentages are compared by value.
        $this->assertEquals(60.0, $severity->json('data.burn_severity.evidence.charred_soil_pct'));
        $this->assertEquals($analysis->charred_percentage, $severity->json('data.metrics.charred_percentage'));
    }

    public function test_progress_compares_the_second_period_with_the_first(): void
    {
        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $first = LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subMonths(6),
            'vegetation_percentage' => 40.0,
            'land_health_score' => 50,
        ]);
        $second = LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subMonth(),
            'vegetation_percentage' => 49.5,
            'land_health_score' => 56,
        ]);

        $this->actingAs($farmer)->getJson("/api/v1/analyses/{$second->id}")
            ->assertOk()
            ->assertJsonPath('data.progress.compared_to.id', $first->id)
            ->assertJsonPath('data.progress.vegetation_delta', 9.5)
            ->assertJsonPath('data.progress.health_delta', 6)
            ->assertJsonPath('data.progress.direction', 'improving');

        // The first period has no earlier evidence to be measured against.
        $this->actingAs($farmer)->getJson("/api/v1/analyses/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.progress', null);

        // The monitoring history carries the same per-period progression.
        $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/analyses")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.progress.vegetation_delta', 9.5)
            ->assertJsonPath('data.0.progress.direction', 'improving')
            ->assertJsonPath('data.1.progress', null);
    }

    public function test_failed_analyses_never_enter_the_monitoring_series(): void
    {
        $farmer = $this->farmer();
        $land = $this->landOwnedBy($farmer);

        $first = LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subDays(30),
            'vegetation_percentage' => 40.0,
            'land_health_score' => 50,
        ]);
        $failed = LandAnalysis::factory()->for($land)->failed()->create([
            'captured_at' => now()->subDays(20),
            'vegetation_percentage' => 99.0,
            'land_health_score' => 99,
        ]);
        $latest = LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subDays(10),
            'vegetation_percentage' => 49.5,
            'land_health_score' => 56,
        ]);

        $response = $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}");

        $response->assertOk()
            ->assertJsonPath('data.progress.periods', 2)
            ->assertJsonPath('data.progress.comparison.from.id', $first->id)
            ->assertJsonPath('data.progress.comparison.to.id', $latest->id)
            ->assertJsonPath('data.analyses.0.id', $latest->id)
            ->assertJsonPath('data.analyses.1.id', $failed->id)
            ->assertJsonPath('data.analyses.1.progress', null)
            ->assertJsonPath('data.analyses.2.id', $first->id)
            ->assertJsonPath('data.analyses.2.progress', null);
    }

    public function test_another_farmer_cannot_upload_to_a_land_they_do_not_own(): void
    {
        Storage::fake('public');

        $owner = $this->farmer();
        $intruder = $this->farmer();
        $land = $this->landOwnedBy($owner);

        $this->actingAs($intruder)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertForbidden();

        $this->assertDatabaseCount('land_analyses', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_institution_may_read_a_lands_analyses_but_not_upload(): void
    {
        Storage::fake('public');

        $farmer = $this->farmer();
        $institution = User::factory()->create(['role' => UserRole::Institution]);
        $land = $this->landOwnedBy($farmer);
        $analysis = LandAnalysis::factory()->for($land)->create();

        $this->actingAs($institution)->getJson("/api/v1/lands/{$land->id}/analyses")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $analysis->id);

        $this->actingAs($institution)->getJson("/api/v1/analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $analysis->id);

        $this->actingAs($institution)
            ->post("/api/v1/lands/{$land->id}/analyses", ['image' => $this->fakeImage()])
            ->assertForbidden();

        $this->assertDatabaseCount('land_analyses', 1);
    }
}
