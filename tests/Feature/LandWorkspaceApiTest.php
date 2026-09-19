<?php

namespace Tests\Feature;

use App\Enums\LandStatus;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Land;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The survey workspace that hangs off a land: sub-plots (parcels), manual field
 * observations and evidence assets.
 *
 * All of it is addressed through the land, and all of it follows the land
 * policy: the owner may write, while a pemda/NGO or corporate account may read
 * the same workspace but change nothing on somebody else's land.
 */
class LandWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The workspace routes carry the session, so a mutating call needs the CSRF
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
     * A closed polygon around the given corner.
     *
     * @return array<string, mixed>
     */
    private function polygon(float $longitude = 110.0, float $latitude = -7.0): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$longitude, $latitude],
                [$longitude + 0.001, $latitude],
                [$longitude + 0.001, $latitude - 0.001],
                [$longitude, $latitude - 0.001],
                [$longitude, $latitude],
            ]],
        ];
    }

    public function test_it_creates_a_land_and_keeps_geometry_and_data_status_explicit(): void
    {
        $farmer = $this->userWithRole(UserRole::Farmer);

        $response = $this->actingAs($farmer)->postJson('/api/v1/lands', [
            'name' => 'Pemulihan Lereng Utara',
            'objective' => 'restoration',
            'location_name' => 'Kabupaten Contoh',
            'latitude' => -7.0,
            'longitude' => 110.0,
            'crs' => 'EPSG:4326',
            'geometry' => $this->polygon(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Pemulihan Lereng Utara')
            ->assertJsonPath('data.objective', 'restoration')
            ->assertJsonPath('data.location_name', 'Kabupaten Contoh')
            ->assertJsonPath('data.has_coordinates', true)
            // A land nobody has analysed yet is registered, and its numbers are
            // still demo data: the API says so instead of implying a survey.
            ->assertJsonPath('data.status', LandStatus::Planned->value)
            ->assertJsonPath('data.data_status', 'demo');

        $land = Land::sole();

        $this->assertSame('Polygon', $land->geometry['type']);
        $this->assertSame('EPSG:4326', $land->crs);
        $this->assertSame('demo', $land->data_status);
        $this->assertGreaterThan(0, $land->area_ha);
        $this->assertEquals($land->area_ha, $response->json('data.area_ha'));

        // The land page is where the owner is named.
        $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}")
            ->assertOk()
            ->assertJsonPath('data.owner.id', $farmer->id)
            ->assertJsonPath('data.name', 'Pemulihan Lereng Utara');

        $this->assertDatabaseHas('lands', [
            'id' => $land->id,
            'name' => 'Pemulihan Lereng Utara',
            'data_status' => 'demo',
            'crs' => 'EPSG:4326',
            'user_id' => $farmer->id,
        ]);
    }

    public function test_it_rejects_a_land_with_only_one_coordinate(): void
    {
        $farmer = $this->userWithRole(UserRole::Farmer);

        $this->actingAs($farmer)
            ->postJson('/api/v1/lands', [
                'name' => 'Titik Tidak Lengkap',
                'latitude' => -7.0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->assertDatabaseCount('lands', 0);
    }

    public function test_it_rejects_a_land_polygon_that_is_not_closed(): void
    {
        $farmer = $this->userWithRole(UserRole::Farmer);

        $this->actingAs($farmer)->postJson('/api/v1/lands', [
            'name' => 'Invalid Parcel',
            'objective' => 'crop_suitability',
            'location_name' => 'Kabupaten Contoh',
            'crs' => 'EPSG:4326',
            'geometry' => [
                'type' => 'Polygon',
                // Four corners, but the ring never returns to its first point.
                'coordinates' => [[[110, -7], [110.001, -7], [110.001, -7.001], [110, -7.001]]],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('geometry');

        $this->assertDatabaseCount('lands', 0);
    }

    public function test_it_records_a_field_observation_without_filling_unknown_values(): void
    {
        $farmer = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($farmer)->create(['data_status' => 'measured']);

        $this->actingAs($farmer)->postJson("/api/v1/lands/{$land->id}/observations", [
            'observed_at' => '2026-01-15',
            'observer' => 'Tim lapangan',
            'burn_severity' => 'moderate',
            'slope_deg' => 24.5,
            'source' => 'survey-form',
            'data_status' => 'measured',
            'notes' => 'Tanah terbuka di sisi timur.',
        ])->assertCreated()
            // Nothing was measured for pH, so the API says null rather than
            // implying a value nobody took.
            ->assertJsonPath('data.soil_ph', null)
            ->assertJsonPath('data.land_id', $land->id)
            ->assertJsonPath('data.observed_at', '2026-01-15')
            ->assertJsonPath('data.burn_severity', 'moderate')
            ->assertJsonPath('data.data_status', 'measured');

        $this->assertDatabaseHas('field_observations', [
            'land_id' => $land->id,
            'burn_severity' => 'moderate',
            'data_status' => 'measured',
        ]);

        $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/observations")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_it_records_a_parcel_with_validated_geometry_under_the_land(): void
    {
        $farmer = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($farmer)->create(['data_status' => 'measured']);

        $response = $this->actingAs($farmer)->postJson("/api/v1/lands/{$land->id}/parcels", [
            'name' => 'Petak A',
            'crs' => 'EPSG:4326',
            'geometry' => $this->polygon(),
            'notes' => 'Sisi timur.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Petak A')
            ->assertJsonPath('data.land_id', $land->id)
            ->assertJsonPath('data.geometry.type', 'Polygon')
            // The parcel inherits the land's data status when the farmer does
            // not state one for the sub-plot.
            ->assertJsonPath('data.data_status', 'measured');

        $this->assertGreaterThan(0, $response->json('data.area_ha'));

        $this->assertDatabaseHas('parcels', [
            'land_id' => $land->id,
            'name' => 'Petak A',
            'data_status' => 'measured',
        ]);

        $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/parcels")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Petak A');
    }

    public function test_it_stores_a_field_asset_against_the_land_and_its_parcel(): void
    {
        Storage::fake('public');

        $farmer = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($farmer)->create();
        $parcel = $land->parcels()->create(['name' => 'Petak A', 'data_status' => 'demo']);

        $response = $this->actingAs($farmer)->post("/api/v1/lands/{$land->id}/assets", [
            'asset' => UploadedFile::fake()->create('sebelum.jpg', 500, 'image/jpeg'),
            'parcel_id' => $parcel->id,
            'type' => 'field_photo',
            'captured_at' => '2026-02-01',
            'data_status' => 'measured',
            'source' => 'survey-form',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.land_id', $land->id)
            ->assertJsonPath('data.parcel_id', $parcel->id)
            ->assertJsonPath('data.type', 'field_photo')
            ->assertJsonPath('data.original_filename', 'sebelum.jpg')
            ->assertJsonPath('data.data_status', 'measured')
            ->assertJsonPath('data.status', 'uploaded')
            ->assertJsonPath('data.provenance.source', 'survey-form')
            // Missing vertical metadata is recorded as incomplete, never filled
            // in with zeroes that would look like a calculation.
            ->assertJsonPath('data.vertical_metadata.status', 'incomplete');

        $asset = Asset::sole();
        Storage::disk('public')->assertExists($asset->path);

        $this->assertDatabaseHas('assets', [
            'land_id' => $land->id,
            'parcel_id' => $parcel->id,
            'type' => 'field_photo',
        ]);

        $this->actingAs($farmer)->getJson("/api/v1/lands/{$land->id}/assets")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_parcel_from_another_land_cannot_be_attached(): void
    {
        Storage::fake('public');

        $farmer = $this->userWithRole(UserRole::Farmer);
        $land = Land::factory()->for($farmer)->create();
        $otherLand = Land::factory()->for($farmer)->create();
        $foreignParcel = $otherLand->parcels()->create(['name' => 'Petak lain', 'data_status' => 'demo']);

        // The parcel exists, so validation passes; the workspace still refuses
        // to file evidence from another land under this one.
        $this->actingAs($farmer)->post("/api/v1/lands/{$land->id}/assets", [
            'asset' => UploadedFile::fake()->create('sebelum.jpg', 200, 'image/jpeg'),
            'parcel_id' => $foreignParcel->id,
            'type' => 'field_photo',
            'data_status' => 'measured',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_an_institution_may_read_the_workspace_but_not_write_to_a_foreign_land(): void
    {
        Storage::fake('public');

        $farmer = $this->userWithRole(UserRole::Farmer);
        $institution = $this->userWithRole(UserRole::Institution);
        $land = Land::factory()->for($farmer)->create();
        $land->parcels()->create(['name' => 'Petak A', 'data_status' => 'demo']);

        $this->actingAs($institution)->getJson("/api/v1/lands/{$land->id}/parcels")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        foreach (['observations', 'assets'] as $section) {
            $this->actingAs($institution)->getJson("/api/v1/lands/{$land->id}/{$section}")
                ->assertOk();
        }

        $this->actingAs($institution)->postJson("/api/v1/lands/{$land->id}/parcels", [
            'name' => 'Petak B',
        ])->assertForbidden();

        $this->actingAs($institution)->postJson("/api/v1/lands/{$land->id}/observations", [
            'observed_at' => '2026-01-15',
            'data_status' => 'demo',
        ])->assertForbidden();

        $this->actingAs($institution)->post("/api/v1/lands/{$land->id}/assets", [
            'asset' => UploadedFile::fake()->create('sebelum.jpg', 200, 'image/jpeg'),
            'type' => 'field_photo',
            'data_status' => 'demo',
        ])->assertForbidden();

        // The owner can still write the same workspace.
        $this->actingAs($farmer)->postJson("/api/v1/lands/{$land->id}/parcels", [
            'name' => 'Petak B',
        ])->assertCreated();

        $this->assertDatabaseHas('parcels', ['land_id' => $land->id, 'name' => 'Petak B']);
    }
}
