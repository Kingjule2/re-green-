<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AnalysisRun;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The survey-grade remote-sensing workflow kept from the previous platform: a
 * land owner files a before/after dNBR run, the run is read back with its
 * provenance, and a reviewer records a decision on it.
 *
 * It also covers the frozen report the workflow feeds: a snapshot is generated
 * once and keeps the numbers (and the gaps) it was issued with.
 */
class AnalysisWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The workflow routes carry the session, so a mutating call needs the CSRF
     * token the Blade layout hands `api.js` — exactly what the SPA sends.
     */
    private const CSRF_TOKEN = 'regreen-test-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-CSRF-TOKEN', self::CSRF_TOKEN);
        $this->withSession(['_token' => self::CSRF_TOKEN]);
    }

    private function userWithRole(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * One-pixel before/after NIR-SWIR pair, as the survey form posts it.
     *
     * @return array<string, mixed>
     */
    private function dnbrPayload(): array
    {
        return [
            'before' => [
                'nir' => [[8000]],
                'swir' => [[2000]],
                'reflectance_scale' => 10000,
                'acquired_at' => '2025-01-01',
            ],
            'after' => [
                'nir' => [[3000]],
                'swir' => [[5000]],
                'reflectance_scale' => 10000,
                'acquired_at' => '2025-02-01',
            ],
            'crs' => 'EPSG:4326',
            'pixel_size_m' => 10,
            'data_status' => 'demo',
            'source' => 'fixture',
        ];
    }

    public function test_it_persists_a_dnbr_run_with_provenance_and_supports_review(): void
    {
        $owner = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson("/api/v1/lands/{$land->id}/analyses/dnbr", $this->dnbrPayload());

        $response->assertCreated()
            ->assertJsonPath('data.type', 'dnbr')
            ->assertJsonPath('data.land_id', $land->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.provenance.data_status', 'demo')
            ->assertJsonPath('data.provenance.source', 'fixture')
            ->assertJsonPath('data.result.valid_pixel_count', 1);

        $run = AnalysisRun::sole();

        // The run is readable on its own, before any review exists.
        $this->actingAs($owner)->getJson("/api/v1/analysis-runs/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.land_id', $land->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.result.valid_pixel_count', 1)
            ->assertJsonCount(0, 'data.reviews');

        $this->actingAs($owner)->postJson("/api/v1/analysis-runs/{$run->id}/reviews", [
            'decision' => 'needs_review',
            'reason' => 'Perlu verifikasi citra sebelum keputusan lapangan.',
        ])->assertCreated()->assertJsonPath('data.decision', 'needs_review');

        $this->assertDatabaseHas('analysis_reviews', [
            'analysis_run_id' => $run->id,
            'decision' => 'needs_review',
        ]);

        // The review moves the run's status, which the run endpoint reports.
        $this->assertSame('needs_review', $run->fresh()->status);
    }

    public function test_only_the_land_owner_may_start_a_dnbr_run(): void
    {
        $owner = $this->userWithRole(UserRole::Farmer);
        $other = $this->userWithRole(UserRole::Farmer);
        $institution = $this->userWithRole(UserRole::Institution);
        $land = Land::factory()->for($owner)->create();

        $this->actingAs($other)->postJson("/api/v1/lands/{$land->id}/analyses/dnbr", $this->dnbrPayload())
            ->assertForbidden();

        $this->actingAs($institution)->postJson("/api/v1/lands/{$land->id}/analyses/dnbr", $this->dnbrPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('analysis_runs', 0);

        // The aggregate roles may still read the land and its runs.
        $this->actingAs($institution)->getJson("/api/v1/lands/{$land->id}")->assertOk();
    }

    public function test_a_land_scoped_report_covers_exactly_that_land(): void
    {
        $owner = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($owner)->create(['area_ha' => 4.0, 'data_status' => 'measured']);
        // A second land of the same account must not leak into the report.
        Land::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->postJson('/api/v1/reports', [
            'title' => 'Laporan Lahan Cempaka',
            'scope' => 'land',
            'land_id' => $land->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Laporan Lahan Cempaka')
            ->assertJsonPath('data.scope', 'land')
            ->assertJsonPath('data.is_global', false)
            ->assertJsonPath('data.land_id', $land->id)
            ->assertJsonPath('data.summary.lands', 1);

        $this->assertEquals(4.0, $response->json('data.summary.area_ha'));

        $this->actingAs($owner)->getJson('/api/v1/reports/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonCount(1, 'data.lands')
            ->assertJsonPath('data.lands.0.id', $land->id);

        // A second report over the same land is the next version of it.
        $this->actingAs($owner)->postJson('/api/v1/reports', [
            'scope' => 'land',
            'land_id' => $land->id,
        ])->assertCreated()->assertJsonPath('data.version', 2);
    }

    public function test_a_report_over_a_land_the_account_cannot_see_is_refused(): void
    {
        $owner = $this->userWithRole(UserRole::Farmer);
        $stranger = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($owner)->create();

        $this->actingAs($stranger)->postJson('/api/v1/reports', [
            'scope' => 'land',
            'land_id' => $land->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_it_creates_versioned_report_snapshot_without_claiming_missing_area(): void
    {
        $owner = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($owner)->create(['area_ha' => null, 'data_status' => 'demo']);

        $response = $this->actingAs($owner)->postJson('/api/v1/reports', [
            'title' => 'Laporan awal',
            'scope' => 'land',
            'land_id' => $land->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.summary.lands', 1);

        // An area nobody measured stays out of the totals instead of being
        // invented for the report.
        $this->assertEquals(0.0, $response->json('data.summary.area_ha'));

        $this->actingAs($owner)->getJson('/api/v1/reports/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.lands.0.id', $land->id)
            ->assertJsonPath('data.lands.0.area_ha', null)
            ->assertJsonPath('data.lands.0.data_status', 'demo')
            ->assertJsonPath('data.lands.0.latest_analysis', null)
            ->assertJsonPath('data.lands.0.periods', 0);

        // A second report for the same land is the next version of it.
        $this->actingAs($owner)->postJson('/api/v1/reports', [
            'scope' => 'land',
            'land_id' => $land->id,
        ])->assertCreated()->assertJsonPath('data.version', 2);
    }

    public function test_a_frozen_report_keeps_the_numbers_it_was_issued_with(): void
    {
        $owner = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($owner)->create([
            'area_ha' => 4.0,
            'data_status' => 'measured',
            'soil_texture' => 'Clay Loam',
            'rainfall_mm' => 2100,
        ]);

        LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subMonths(2),
            'land_health_score' => 50,
            'vegetation_percentage' => 40.0,
            'bare_soil_percentage' => 30.0,
            'charred_percentage' => 10.0,
        ]);

        $report = $this->actingAs($owner)->postJson('/api/v1/reports', [
            'scope' => 'land',
            'land_id' => $land->id,
        ])->assertCreated();

        $reportId = $report->json('data.id');
        $issued = $report->json('data.summary');

        $this->assertSame(1, $issued['monitoring_periods']);
        $this->assertEquals(50, $issued['average_health_score']);
        $this->assertEquals(40.0, $issued['average_vegetation_percentage']);

        // New evidence is uploaded after the report was filed.
        LandAnalysis::factory()->for($land)->create([
            'captured_at' => now()->subDays(5),
            'land_health_score' => 70,
            'vegetation_percentage' => 80.0,
            'bare_soil_percentage' => 15.0,
            'charred_percentage' => 5.0,
        ]);

        // The filed report still says exactly what it said.
        $frozen = $this->actingAs($owner)->getJson("/api/v1/reports/{$reportId}")->assertOk();

        $this->assertEquals($issued, $frozen->json('data.summary'));
        $frozen->assertJsonPath('data.lands.0.periods', 1)
            ->assertJsonPath('data.lands.0.latest_analysis.health_score', 50)
            ->assertJsonPath('data.lands.0.progress.direction', null);

        $created = $this->actingAs($owner)->postJson('/api/v1/reports', [
            'scope' => 'land',
            'land_id' => $land->id,
        ])->assertCreated();
        $latest = $this->actingAs($owner)->getJson('/api/v1/reports/'.$created->json('data.id'))->assertOk();

        $latest->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.summary.monitoring_periods', 2)
            ->assertJsonPath('data.lands.0.progress.direction', 'improving');
    }
}
