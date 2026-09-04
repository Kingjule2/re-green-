<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Jobs\AnalyzeDroneImage;
use App\Models\DroneAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DroneAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A representative ML land-analysis response.
     *
     * @return array<string, mixed>
     */
    private function fakePerception(): array
    {
        return [
            'land_cover' => [
                'dense_vegetation' => 40,
                'sparse_vegetation' => 20,
                'bare_soil' => 25,
                'water' => 8,
                'built_area' => 2,
                'other' => 5,
            ],
            'confidence' => ['overall' => 0.9, 'vegetation' => 0.92, 'bare_soil' => 0.84, 'water' => 0.8],
            'image' => ['width' => 5472, 'height' => 3648],
            'model' => ['name' => 'color-histogram', 'version' => '0.1.0'],
        ];
    }

    private function fakeImage(): UploadedFile
    {
        // create() avoids the GD dependency that image() requires.
        return UploadedFile::fake()->create('drone_land_001.jpg', 8000, 'image/jpeg');
    }

    public function test_upload_stores_image_and_queues_analysis(): void
    {
        Queue::fake();
        Storage::fake('public');

        $response = $this->post('/api/v1/drone-analyses', [
            'image' => $this->fakeImage(),
            'area_name' => 'Area A',
            'latitude' => -6.4025,
            'longitude' => 106.7942,
            'flight_altitude' => '120 m',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AnalysisStatus::Pending->value)
            ->assertJsonPath('data.metadata.area_name', 'Area A')
            ->assertJsonPath('data.metadata.has_reliable_geolocation', true);

        $analysis = DroneAnalysis::sole();
        $this->assertSame(AnalysisStatus::Pending, $analysis->analysis_status);
        Storage::disk('public')->assertExists($analysis->image_path);

        Queue::assertPushed(AnalyzeDroneImage::class);
    }

    public function test_upload_runs_pipeline_to_completion(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response($this->fakePerception())]);

        $response = $this->post('/api/v1/drone-analyses', ['image' => $this->fakeImage()]);

        $response->assertCreated();

        $analysis = DroneAnalysis::sole();
        $this->assertSame(AnalysisStatus::Completed, $analysis->analysis_status);
        $this->assertNotNull($analysis->land_health_score);
        $this->assertNotNull($analysis->analysis_completed_at);
        $this->assertArrayHasKey('land_health', $analysis->analysis_result);
        $this->assertArrayHasKey('recommendations', $analysis->recommendation_result);
        $this->assertSame('color-histogram', $analysis->ai_model);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/land-analysis'));
    }

    public function test_upload_requires_an_image(): void
    {
        $this->postJson('/api/v1/drone-analyses', ['area_name' => 'Area A'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_upload_rejects_unsupported_formats(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/drone-analyses', [
            'image' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_show_returns_analysis_state_and_results(): void
    {
        $analysis = DroneAnalysis::factory()->create();

        $this->getJson("/api/v1/drone-analyses/{$analysis->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $analysis->id)
            ->assertJsonPath('data.status', AnalysisStatus::Completed->value)
            ->assertJsonPath('data.analysis.land_health.score', $analysis->analysis_result['land_health']['score']);
    }

    public function test_index_lists_recent_analyses(): void
    {
        DroneAnalysis::factory()->count(3)->create();

        $this->getJson('/api/v1/drone-analyses')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_destroy_removes_analysis_and_image(): void
    {
        Storage::fake('public');
        $analysis = DroneAnalysis::factory()->create(['image_path' => 'drone-analyses/sample.jpg']);
        Storage::disk('public')->put('drone-analyses/sample.jpg', 'bytes');

        $this->deleteJson("/api/v1/drone-analyses/{$analysis->id}")->assertNoContent();

        $this->assertDatabaseMissing('drone_analyses', ['id' => $analysis->id]);
        Storage::disk('public')->assertMissing('drone-analyses/sample.jpg');
    }

    public function test_pipeline_failure_marks_analysis_failed(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(['message' => 'model unavailable'], 500)]);

        $analysis = DroneAnalysis::factory()->pending()->create(['image_path' => 'drone-analyses/sample.jpg']);
        Storage::disk('public')->put('drone-analyses/sample.jpg', 'bytes');

        try {
            AnalyzeDroneImage::dispatchSync($analysis);
        } catch (\Throwable) {
            // The job rethrows after marking the record failed; that is expected.
        }

        $this->assertSame(AnalysisStatus::Failed, $analysis->fresh()->analysis_status);
        $this->assertNotNull($analysis->fresh()->analysis_error);
    }
}
