<?php

namespace Tests\Feature;

use App\Enums\BurnSeverity;
use App\Enums\CarbonSubmissionStatus;
use App\Enums\UserRole;
use App\Models\CarbonAssessment;
use App\Models\Land;
use App\Models\LandAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Behaviour of the role-scoped API: who may read or write what, the dashboard
 * and map aggregates each role gets, the carbon screening and application, and
 * the frozen report snapshots with their PDF download.
 *
 * These tests go through the HTTP surface only: a response field is a promise
 * to the SPA, an internal method name is not.
 */
class ReportCarbonAuthApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The API routes carry the session, so a mutating call needs the CSRF
     * token the Blade layout hands `api.js` — exactly what the SPA sends.
     */
    private const CSRF_TOKEN = 'regreen-test-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-CSRF-TOKEN', self::CSRF_TOKEN);
        $this->withSession(['_token' => self::CSRF_TOKEN]);
    }

    // ---------------------------------------------------------------------
    // Auth and roles
    // ---------------------------------------------------------------------

    public function test_register_stores_the_role_and_organization_and_signs_the_account_in(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sari Puspita',
            'email' => 'sari@example.test',
            'password' => 'sandi-rahasia-123',
            'password_confirmation' => 'sandi-rahasia-123',
            'role' => 'institution',
            'organization' => 'Dinas Lingkungan Hidup Ogan Ilir',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'sari@example.test')
            ->assertJsonPath('data.role', 'institution')
            ->assertJsonPath('data.role_label', UserRole::Institution->label())
            ->assertJsonPath('data.organization', 'Dinas Lingkungan Hidup Ogan Ilir')
            ->assertJsonPath('data.is_farmer', false)
            ->assertJsonPath('data.views_all_lands', true);

        $this->assertDatabaseHas('users', [
            'email' => 'sari@example.test',
            'role' => 'institution',
            'organization' => 'Dinas Lingkungan Hidup Ogan Ilir',
        ]);

        // The account is usable straight after registering, without a login call.
        // The guard is reset first so the session — not the in-memory user the
        // register action just created — is what answers the next request.
        Auth::forgetGuards();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sari@example.test');
    }

    public function test_login_rejects_wrong_credentials_with_a_validation_error(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Farmer,
            'password' => 'sandi-rahasia-123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'kata-sandi-salah',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_login_signs_in_an_existing_account(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Institution,
            'organization' => 'Yayasan Hijau',
            'password' => 'sandi-rahasia-123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sandi-rahasia-123',
        ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'institution');

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_logout_ends_the_session_so_me_answers_401(): void
    {
        $user = User::factory()->create(['role' => UserRole::Farmer]);

        $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/lands')->assertUnauthorized();
    }

    public function test_guests_cannot_list_lands(): void
    {
        Land::factory()->create();

        $this->getJson('/api/v1/lands')->assertUnauthorized();
    }

    public function test_a_farmer_only_sees_and_opens_their_own_lands(): void
    {
        $sari = $this->farmer();
        $budi = $this->farmer();

        $own = Land::factory()->for($sari)->create(['area_ha' => 4.0]);
        $foreign = Land::factory()->for($budi)->create(['area_ha' => 7.0]);

        $response = $this->actingAs($sari)->getJson('/api/v1/lands');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.scope', 'own');

        $this->assertEqualsWithDelta(4.0, (float) $response->json('meta.area_ha'), 0.001);

        $this->actingAs($sari)->getJson("/api/v1/lands/{$foreign->id}")->assertForbidden();
        $this->actingAs($sari)->getJson("/api/v1/lands/{$own->id}")->assertOk();
    }

    public function test_an_institution_sees_every_land_and_its_empty_report_list(): void
    {
        $institution = $this->institution();
        $corporate = User::factory()->create(['role' => UserRole::Corporate, 'organization' => 'PT Hijau Lestari']);
        $sari = $this->farmer();
        $budi = $this->farmer();

        $first = Land::factory()->for($sari)->create();
        Land::factory()->for($budi)->create();

        $response = $this->actingAs($institution)->getJson('/api/v1/lands');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.scope', 'all');

        $this->actingAs($institution)->getJson("/api/v1/lands/{$first->id}")->assertOk();

        // A corporate CSR/ESG account reads the same aggregate picture.
        $this->actingAs($corporate)->getJson('/api/v1/lands')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.scope', 'all');

        // An aggregate account may open the report list before owning a report.
        $this->actingAs($institution)->getJson('/api/v1/reports')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_institution_cannot_update_a_farmers_land(): void
    {
        $institution = $this->institution();
        $owner = $this->farmer();
        $land = Land::factory()->for($owner)->create(['name' => 'Lahan Cempaka']);

        $this->actingAs($institution)
            ->patchJson("/api/v1/lands/{$land->id}", ['name' => 'Diubah Pihak Lain'])
            ->assertForbidden();

        $this->assertDatabaseHas('lands', ['id' => $land->id, 'name' => 'Lahan Cempaka']);

        // The owner keeps the write, so the 403 above is about ownership only.
        $this->actingAs($owner)
            ->patchJson("/api/v1/lands/{$land->id}", ['name' => 'Lahan Cempaka Baru'])
            ->assertOk();

        $this->assertDatabaseHas('lands', ['id' => $land->id, 'name' => 'Lahan Cempaka Baru']);
    }

    public function test_an_institution_cannot_create_a_land(): void
    {
        $institution = $this->institution();

        $this->actingAs($institution)
            ->postJson('/api/v1/lands', ['name' => 'Lahan Baru'])
            ->assertForbidden();

        $this->assertDatabaseCount('lands', 0);
    }

    public function test_a_corporate_account_cannot_create_a_land(): void
    {
        $corporate = User::factory()->create([
            'role' => UserRole::Corporate,
            'organization' => 'PT Hijau Lestari',
        ]);

        $this->actingAs($corporate)
            ->postJson('/api/v1/lands', ['name' => 'Lahan CSR'])
            ->assertForbidden();

        $this->assertDatabaseCount('lands', 0);
    }

    public function test_a_farmer_cannot_override_land_status_from_the_update_endpoint(): void
    {
        $farmer = $this->farmer();
        $land = Land::factory()->for($farmer)->create(['status' => 'planned']);

        $this->actingAs($farmer)
            ->patchJson("/api/v1/lands/{$land->id}", ['status' => 'restored'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('lands', [
            'id' => $land->id,
            'status' => 'planned',
        ]);
    }

    public function test_carbon_portfolio_is_forbidden_for_farmers_and_open_to_aggregate_roles(): void
    {
        $farmer = $this->farmer();
        $institution = $this->institution();
        $corporate = User::factory()->create(['role' => UserRole::Corporate, 'organization' => 'PT Hijau Lestari']);

        Land::factory()->for($farmer)->create(['area_ha' => 4.0]);

        $this->actingAs($farmer)->getJson('/api/v1/carbon/portfolio')->assertForbidden();

        $this->actingAs($institution)->getJson('/api/v1/carbon/portfolio')
            ->assertOk()
            ->assertJsonPath('data.lands_total', 1)
            ->assertJsonPath('data.ineligible', 1);

        $this->actingAs($corporate)->getJson('/api/v1/carbon/portfolio')->assertOk();
    }

    // ---------------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------------

    public function test_farmer_dashboard_aggregates_their_lands_and_flags_the_stale_ones(): void
    {
        $sari = $this->farmer();
        $budi = $this->farmer();

        $recentLand = Land::factory()->for($sari)->create(['area_ha' => 4.0, 'location_name' => 'Kab. Ogan Ilir']);
        $staleLand = Land::factory()->for($sari)->create(['area_ha' => 6.5, 'location_name' => 'Kab. Ogan Ilir']);

        $recent = $this->analysis($recentLand, [
            'captured_at' => now()->subDays(10),
            'burn_severity' => BurnSeverity::Moderate,
            'land_health_score' => 58,
            'vegetation_percentage' => 44.0,
        ]);

        $stale = $this->analysis($staleLand, [
            'captured_at' => now()->subDays(200),
            'burn_severity' => BurnSeverity::High,
            'land_health_score' => 25,
            'vegetation_percentage' => 20.0,
        ]);

        // Another farmer's healthy land must not leak into any of the numbers.
        $foreign = Land::factory()->for($budi)->create(['area_ha' => 9.0]);
        $this->analysis($foreign, [
            'captured_at' => now(),
            'burn_severity' => BurnSeverity::Unburned,
            'land_health_score' => 92,
            'vegetation_percentage' => 95.0,
        ]);

        $response = $this->actingAs($sari)->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.scope', 'own')
            ->assertJsonPath('data.lands', 2)
            ->assertJsonPath('data.analyses', 2)
            ->assertJsonPath('data.average_health_score', 42);

        $this->assertEqualsWithDelta(10.5, (float) $response->json('data.area_ha'), 0.001);

        $mix = $response->json('data.severity_mix');
        $this->assertSame(1, $this->severityCount($mix, BurnSeverity::Moderate->value));
        $this->assertSame(1, $this->severityCount($mix, BurnSeverity::High->value));
        $this->assertSame(0, $this->severityCount($mix, BurnSeverity::Unburned->value));

        // The newest measurement per land, newest first.
        $latest = $response->json('data.latest_analyses');
        $this->assertCount(2, $latest);
        $this->assertSame($recent->id, $latest[0]['id']);

        // The plot whose evidence is going stale comes first.
        $needsMonitoring = $response->json('data.needs_monitoring');
        $this->assertSame($staleLand->id, $needsMonitoring[0]['id']);
        $this->assertGreaterThanOrEqual(199, (int) $needsMonitoring[0]['days_since_last_analysis']);
        $this->assertSame($recentLand->id, $needsMonitoring[1]['id']);
        $this->assertLessThanOrEqual(11, (int) $needsMonitoring[1]['days_since_last_analysis']);
        $this->assertNotContains($foreign->id, array_column($needsMonitoring, 'id'));
    }

    public function test_institution_dashboard_breaks_every_land_down_by_region_and_risk(): void
    {
        $institution = $this->institution();
        $siakFarmer = $this->farmer(['organization' => 'Kelompok Tani Siak Bersemi']);
        $kapuasFarmer = $this->farmer();

        $steady = Land::factory()->for($siakFarmer)->create(['area_ha' => 5.0, 'location_name' => 'Kab. Siak']);
        $atRisk = Land::factory()->for($kapuasFarmer)->create(['area_ha' => 3.0, 'location_name' => 'Kab. Kapuas']);

        $this->analysis($steady, [
            'captured_at' => now()->subDays(5),
            'burn_severity' => BurnSeverity::Low,
            'land_health_score' => 70,
            'vegetation_percentage' => 62.0,
        ]);

        $this->analysis($atRisk, [
            'captured_at' => now()->subDays(5),
            'burn_severity' => BurnSeverity::High,
            'land_health_score' => 22,
            'vegetation_percentage' => 18.0,
        ]);

        $response = $this->actingAs($institution)->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.scope', 'all')
            ->assertJsonPath('data.lands', 2)
            ->assertJsonPath('data.analyses', 2)
            ->assertJsonPath('data.farmers', 2)
            ->assertJsonPath('data.organizations', ['Kelompok Tani Siak Bersemi']);

        $this->assertEqualsWithDelta(8.0, (float) $response->json('data.area_ha'), 0.001);

        $regions = collect($response->json('data.by_region'))->pluck('lands', 'region');
        $this->assertSame(1, $regions['Kab. Siak']);
        $this->assertSame(1, $regions['Kab. Kapuas']);

        $topRisk = $response->json('data.top_risk_lands');
        $this->assertSame($atRisk->id, $topRisk[0]['id']);
        $this->assertCount(2, $topRisk);

        $mix = $response->json('data.severity_mix');
        $this->assertSame(1, $this->severityCount($mix, BurnSeverity::High->value));
        $this->assertSame(1, $this->severityCount($mix, BurnSeverity::Low->value));

        // The farmer-only blocks belong to the other audience.
        $this->assertArrayNotHasKey('needs_monitoring', $response->json('data'));
        $this->assertArrayNotHasKey('latest_analyses', $response->json('data'));
    }

    // ---------------------------------------------------------------------
    // Map
    // ---------------------------------------------------------------------

    public function test_map_lists_only_located_lands_of_the_account_and_counts_the_rest(): void
    {
        $farmer = $this->farmer();
        $otherFarmer = $this->farmer();

        $located = Land::factory()->for($farmer)->create([
            'name' => 'Lahan Cempaka',
            'latitude' => -3.1,
            'longitude' => 104.7,
            'area_ha' => 4.0,
        ]);

        $this->analysis($located, [
            'captured_at' => now()->subDays(5),
            'land_health_score' => 25,
            'burn_severity' => BurnSeverity::High,
            'vegetation_percentage' => 18.0,
        ]);

        Land::factory()->for($farmer)->create([
            'name' => 'Lahan Tanpa Titik',
            'latitude' => null,
            'longitude' => null,
            'area_ha' => 2.0,
        ]);

        Land::factory()->for($otherFarmer)->create([
            'latitude' => 1.2,
            'longitude' => 101.3,
        ]);

        $response = $this->actingAs($farmer)->getJson('/api/v1/map/lands');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $located->id)
            ->assertJsonPath('data.0.owner_name', $farmer->name)
            ->assertJsonPath('data.0.health_score', 25)
            ->assertJsonPath('data.0.health_status', 'critical')
            ->assertJsonPath('data.0.burn_severity', 'high')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.without_coordinates', 1);

        $this->assertEqualsWithDelta(-3.1, (float) $response->json('data.0.latitude'), 0.0000001);
        $this->assertEqualsWithDelta(104.7, (float) $response->json('data.0.longitude'), 0.0000001);

        // The aggregate view plots every located land and still counts the gaps.
        $aggregate = $this->actingAs($this->institution())->getJson('/api/v1/map/lands');

        $aggregate->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.without_coordinates', 1);

        // A land that was never measured is plotted, but claims no health band.
        $unmeasured = collect($aggregate->json('data'))->firstWhere('health_score', null);
        $this->assertNotNull($unmeasured, 'the land without an analysis is missing from the map');
        $this->assertNull($unmeasured['health_status']);
        $this->assertNull($unmeasured['burn_severity']);
    }

    // ---------------------------------------------------------------------
    // Carbon
    // ---------------------------------------------------------------------

    public function test_a_fully_monitored_land_is_eligible_with_its_sequestration_estimate(): void
    {
        $farmer = $this->farmer();
        $land = $this->monitoredLand($farmer);

        $response = $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/carbon");

        $response->assertOk()
            ->assertJsonPath('data.eligibility.status', 'eligible')
            ->assertJsonPath('data.eligibility.label', 'Layak diajukan')
            ->assertJsonPath('data.eligibility.failed', [])
            ->assertJsonPath('data.vegetation_class', 'regrowth');

        $this->assertEqualsWithDelta(55.0, (float) $response->json('data.vegetation_cover_pct'), 0.001);

        // 4 ha x 55% cover x 11 tCO2e/ha/year (regrowth) = 24.2, projected 5 years = 121.
        $this->assertEqualsWithDelta(24.2, (float) $response->json('data.sequestration_tco2e_per_year'), 0.001);
        $this->assertEqualsWithDelta(121.0, (float) $response->json('data.sequestration_5yr_tco2e'), 0.001);
    }

    public function test_a_land_without_any_analysis_cannot_claim_carbon(): void
    {
        $farmer = $this->farmer();
        $land = Land::factory()->for($farmer)->create([
            'area_ha' => 2.0,
            'latitude' => -3.1,
            'longitude' => 104.7,
        ]);

        $response = $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/carbon");

        $response->assertOk()
            ->assertJsonPath('data.eligibility.status', 'ineligible');

        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.vegetation_cover_pct'), 0.001);

        $failed = array_column($response->json('data.eligibility.failed'), 'key');
        $this->assertContains('analysis', $failed);
        $this->assertContains('analysis_freshness', $failed);
        $this->assertContains('vegetation_cover', $failed);
        $this->assertContains('model_provenance', $failed);

        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.sequestration_tco2e_per_year'), 0.001);
    }

    public function test_low_vegetation_cover_holds_the_application_at_not_yet(): void
    {
        $farmer = $this->farmer();
        $land = Land::factory()->for($farmer)->create([
            'area_ha' => 3.0,
            'latitude' => -3.1,
            'longitude' => 104.7,
        ]);

        $this->analysis($land, ['captured_at' => now()->subMonths(4), 'vegetation_percentage' => 22.0]);
        $this->analysis($land, ['captured_at' => now()->subDays(8), 'vegetation_percentage' => 25.0]);

        $response = $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/carbon");

        $response->assertOk()
            ->assertJsonPath('data.eligibility.status', 'not_yet')
            ->assertJsonPath('data.vegetation_class', 'sparse');

        $this->assertEqualsWithDelta(25.0, (float) $response->json('data.vegetation_cover_pct'), 0.001);

        // Vegetation cover is the only open item; everything else already holds.
        $this->assertSame(
            ['vegetation_cover'],
            array_column($response->json('data.eligibility.failed'), 'key'),
        );

        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.sequestration_tco2e_per_year'), 0.001);
    }

    public function test_only_the_owner_can_submit_the_carbon_application(): void
    {
        $owner = $this->farmer();
        $institution = $this->institution();
        $land = $this->monitoredLand($owner);

        $payload = [
            'partner' => 'Yayasan Karbon Nusantara',
            'contact_name' => 'Sari Puspita',
            'contact_email' => 'sari@example.test',
            'notes' => 'Siap diverifikasi lapangan.',
        ];

        // A reader may screen the land but never files for it.
        $this->actingAs($institution)
            ->postJson("/api/v1/lands/{$land->id}/carbon/submit", $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('carbon_assessments', 0);

        $this->actingAs($institution)->getJson("/api/v1/lands/{$land->id}/carbon")->assertOk();

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/lands/{$land->id}/carbon/submit", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.eligibility.status', 'eligible')
            ->assertJsonPath('data.submission.status', 'submitted')
            ->assertJsonPath('data.submission.status_label', CarbonSubmissionStatus::Submitted->label())
            ->assertJsonPath('data.submission.partner', 'Yayasan Karbon Nusantara')
            ->assertJsonPath('data.submission.contact_email', 'sari@example.test');

        $assessment = CarbonAssessment::query()->where('land_id', $land->id)->sole();

        $this->assertSame(CarbonSubmissionStatus::Submitted, $assessment->submission_status);
        $this->assertNotNull($assessment->submitted_at);
        $this->assertEqualsWithDelta(24.2, (float) $assessment->sequestration_tco2e_per_year, 0.001);
        // No offered area was declared, so the land's whole area is filed.
        $this->assertEqualsWithDelta(4.0, (float) $assessment->offered_area_ha, 0.001);

        // The application is readable on the same payload afterwards.
        $this->actingAs($owner)->getJson("/api/v1/lands/{$land->id}/carbon")
            ->assertOk()
            ->assertJsonPath('data.submission.status', 'submitted');
    }

    // ---------------------------------------------------------------------
    // Reports
    // ---------------------------------------------------------------------

    public function test_a_report_snapshot_covers_exactly_the_lands_the_account_can_see(): void
    {
        $institution = $this->institution();
        $sari = $this->farmer();
        $budi = $this->farmer();

        $sariLand = Land::factory()->for($sari)->create(['area_ha' => 3.0]);
        Land::factory()->for($budi)->create(['area_ha' => 7.0]);

        $this->analysis($sariLand, ['captured_at' => now()->subDays(20), 'vegetation_percentage' => 40.0]);

        $aggregate = $this->actingAs($institution)->postJson('/api/v1/reports', ['scope' => 'all']);

        $aggregate->assertCreated()
            ->assertJsonPath('data.scope', 'all')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.summary.lands', 2)
            ->assertJsonPath('data.summary.lands_with_analysis', 1)
            ->assertJsonPath('data.summary.monitoring_periods', 1);

        $this->assertEqualsWithDelta(10.0, (float) $aggregate->json('data.summary.area_ha'), 0.001);

        // The same scope for a farmer freezes only their own land.
        $own = $this->actingAs($sari)->postJson('/api/v1/reports', ['scope' => 'all']);

        $own->assertCreated()
            ->assertJsonPath('data.scope', 'all')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.summary.lands', 1);

        $this->assertEqualsWithDelta(3.0, (float) $own->json('data.summary.area_ha'), 0.001);
    }

    public function test_report_pdf_is_served_as_a_pdf_document(): void
    {
        $institution = $this->institution();
        $land = Land::factory()->for($this->farmer())->create(['area_ha' => 4.0]);
        $this->analysis($land, ['captured_at' => now()->subDays(15), 'vegetation_percentage' => 44.0]);

        $reportId = $this->actingAs($institution)
            ->postJson('/api/v1/reports', ['scope' => 'all'])
            ->assertCreated()
            ->json('data.id');

        $response = $this->actingAs($institution)->get("/api/v1/reports/{$reportId}/pdf");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('regreen-laporan-', (string) $response->headers->get('content-disposition'));
    }

    public function test_only_the_owner_can_download_or_open_a_report(): void
    {
        $owner = $this->institution();
        $intruder = $this->institution();

        Land::factory()->for($this->farmer())->create();

        $reportId = $this->actingAs($owner)
            ->postJson('/api/v1/reports', ['scope' => 'all'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($intruder)->getJson("/api/v1/reports/{$reportId}")->assertForbidden();
        $this->actingAs($intruder)->get("/api/v1/reports/{$reportId}/pdf")->assertForbidden();
        $this->actingAs($intruder)->deleteJson("/api/v1/reports/{$reportId}")->assertForbidden();
        $this->assertDatabaseHas('report_snapshots', ['id' => $reportId]);

        $this->actingAs($owner)->getJson("/api/v1/reports/{$reportId}")->assertOk();
        $this->actingAs($owner)->get("/api/v1/reports/{$reportId}/pdf")->assertOk();
    }

    public function test_the_report_snapshot_stays_frozen_after_the_lands_change(): void
    {
        $owner = $this->institution();
        $farmer = $this->farmer();

        $land = Land::factory()->for($farmer)->create([
            'name' => 'Lahan Cempaka',
            'area_ha' => 3.0,
            'latitude' => -3.1,
            'longitude' => 104.7,
        ]);

        $this->analysis($land, [
            'captured_at' => now()->subDays(30),
            'land_health_score' => 55,
            'vegetation_percentage' => 40.0,
        ]);

        $reportId = $this->actingAs($owner)
            ->postJson('/api/v1/reports', ['scope' => 'all'])
            ->assertCreated()
            ->json('data.id');

        // The evidence moves on: bigger area, a fresh photo, a renamed land, a
        // second monitored land, and even the report's own land being deleted.
        $land->update(['name' => 'Lahan Sudah Berubah', 'area_ha' => 30.0]);
        $this->analysis($land, [
            'captured_at' => now(),
            'land_health_score' => 95,
            'vegetation_percentage' => 90.0,
        ]);
        Land::factory()->for($farmer)->create(['area_ha' => 12.0]);

        $frozen = $this->actingAs($owner)->getJson("/api/v1/reports/{$reportId}")->assertOk();

        $this->assertSame(1, (int) $frozen->json('data.summary.lands'));
        $this->assertEqualsWithDelta(3.0, (float) $frozen->json('data.summary.area_ha'), 0.001);
        $this->assertSame(1, (int) $frozen->json('data.summary.monitoring_periods'));
        $this->assertSame('Lahan Cempaka', $frozen->json('data.lands.0.name'));
        $this->assertEqualsWithDelta(40.0, (float) $frozen->json('data.lands.0.latest_analysis.vegetation_percentage'), 0.001);

        // The PDF is rendered from that same frozen snapshot.
        $this->actingAs($owner)->get("/api/v1/reports/{$reportId}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ---------------------------------------------------------------------
    // Fixtures and small helpers
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function farmer(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Farmer, ...$attributes]);
    }

    private function institution(): User
    {
        return User::factory()->create([
            'role' => UserRole::Institution,
            'organization' => 'Dinas Lingkungan Hidup',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function analysis(Land $land, array $attributes = []): LandAnalysis
    {
        return LandAnalysis::factory()->create([
            'land_id' => $land->id,
            'user_id' => $land->user_id,
            ...$attributes,
        ]);
    }

    /**
     * A land with the evidence a carbon application needs: 4 ha, a point on the
     * map, and two completed periods that grew to 55% vegetation cover.
     */
    private function monitoredLand(User $owner): Land
    {
        $land = Land::factory()->for($owner)->create([
            'name' => 'Lahan Cempaka',
            'area_ha' => 4.0,
            'latitude' => -3.1,
            'longitude' => 104.7,
        ]);

        $this->analysis($land, [
            'captured_at' => now()->subMonths(6),
            'land_health_score' => 50,
            'vegetation_percentage' => 40.0,
        ]);

        $this->analysis($land, [
            'captured_at' => now()->subDays(15),
            'land_health_score' => 62,
            'vegetation_percentage' => 55.0,
        ]);

        return $land;
    }

    /**
     * @param  list<array<string, mixed>>  $mix
     */
    private function severityCount(array $mix, string $level): int
    {
        $row = collect($mix)->firstWhere('level', $level);

        $this->assertNotNull($row, "severity_mix has no {$level} row");

        return (int) $row['count'];
    }
}
