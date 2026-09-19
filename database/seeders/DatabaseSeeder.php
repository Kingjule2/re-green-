<?php

namespace Database\Seeders;

use App\Enums\AnalysisStatus;
use App\Enums\CarbonSubmissionStatus;
use App\Enums\LandStatus;
use App\Enums\UserRole;
use App\Models\Land;
use App\Models\User;
use App\Services\Carbon\CarbonCreditAssessor;
use App\Services\Land\LandIntelligenceEngine;
use App\Services\Restoration\RestorationRecommendationEngine;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo workspace for a pitch: four monitoring histories that show the whole
 * product — a land recovering after a fire, one whose datasets could not be
 * reached (so the declared soil and rainfall carry the recommendation), a
 * highland plot that ranks coffee, a peat block that still needs its first
 * follow-up photo, a plot that has not been photographed yet, and a
 * government-owned restoration block.
 *
 * Everything is derived by the same engines the live pipeline uses, so the demo
 * cannot show numbers the real platform would not produce. Every row is marked
 * `data_status = demo`: the app, the dashboard and the reports all say which
 * data is demo instead of measured.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * USDA texture fractions, as SoilGrids would report them.
     *
     * @var array<string, array{sand: float, silt: float, clay: float}>
     */
    private const TEXTURES = [
        'Clay Loam' => ['sand' => 33.0, 'silt' => 34.0, 'clay' => 33.0],
        'Sandy Loam' => ['sand' => 65.0, 'silt' => 25.0, 'clay' => 10.0],
        'Loam' => ['sand' => 43.0, 'silt' => 39.0, 'clay' => 18.0],
        'Silt Loam' => ['sand' => 20.0, 'silt' => 65.0, 'clay' => 15.0],
    ];

    public function run(): void
    {
        $farmer = $this->user('Sari Wulandari', 'petani@regreen.id', UserRole::Farmer);
        $secondFarmer = $this->user('Joko Santoso', 'petani2@regreen.id', UserRole::Farmer);
        $institution = $this->user('Rizky Pratama', 'bappeda@regreen.id', UserRole::Institution, 'Bappeda Kab. Ogan Ilir');
        $corporate = $this->user('Dimas Nugroho', 'csr@regreen.id', UserRole::Corporate, 'PT Rimba Lestari (CSR)');

        $this->land($farmer, [
            'name' => 'Lahan Sungai Keruh',
            'location_name' => 'Kab. Ogan Ilir, Sumatera Selatan',
            'latitude' => -3.1842,
            'longitude' => 104.6231,
            'area_ha' => 4.5,
            'fire_event_date' => '2025-09-14',
            'soil_texture' => 'Clay Loam',
            'rainfall_mm' => 2400,
            'notes' => 'Lahan gambut tipis di tepi Sungai Keruh; bekas kebakaran September 2025.',
            'context' => ['elevation' => 24.0, 'slope' => 3.2, 'rainfall' => 2400.0, 'temperature' => 27.4, 'ph' => 5.1],
            'periods' => [
                ['captured_at' => '2025-10-05', 'cover' => ['dense' => 3, 'sparse' => 10, 'bare' => 28, 'charred' => 52, 'water' => 2, 'built' => 1, 'other' => 4]],
                ['captured_at' => '2026-01-12', 'cover' => ['dense' => 8, 'sparse' => 22, 'bare' => 27, 'charred' => 32, 'water' => 2, 'built' => 1, 'other' => 8]],
                ['captured_at' => '2026-05-20', 'cover' => ['dense' => 19, 'sparse' => 33, 'bare' => 25, 'charred' => 14, 'water' => 3, 'built' => 1, 'other' => 5]],
            ],
            'carbon' => ['partner' => 'Yayasan Mitra Karbon Nusantara', 'contact' => 'sari@example.id'],
        ]);

        $this->land($secondFarmer, [
            'name' => 'Talang Buluh',
            'location_name' => 'Kab. Ogan Komering Ilir, Sumatera Selatan',
            'latitude' => -3.4521,
            'longitude' => 105.1234,
            'area_ha' => 8.0,
            'fire_event_date' => '2025-08-02',
            'soil_texture' => 'Sandy Loam',
            'rainfall_mm' => 2150,
            'notes' => 'Lahan mineral berpasir; dipantau dengan foto ponsel tiap 3 bulan.',
            // Neither SoilGrids nor NASA POWER could be reached for this point,
            // so the recommendation runs on the declared texture and rainfall.
            'context' => ['elevation' => 12.0, 'slope' => 1.4, 'rainfall' => null, 'temperature' => null, 'ph' => null],
            'periods' => [
                ['captured_at' => '2025-11-08', 'cover' => ['dense' => 9, 'sparse' => 21, 'bare' => 33, 'charred' => 30, 'water' => 1, 'built' => 0, 'other' => 6]],
                ['captured_at' => '2026-04-16', 'cover' => ['dense' => 18, 'sparse' => 28, 'bare' => 30, 'charred' => 16, 'water' => 1, 'built' => 1, 'other' => 6]],
            ],
        ]);

        $this->land($farmer, [
            'name' => 'Kebun Lereng Dempo',
            'location_name' => 'Kota Pagar Alam, Sumatera Selatan',
            'latitude' => -4.0213,
            'longitude' => 103.2456,
            'area_ha' => 2.0,
            'fire_event_date' => '2025-07-19',
            'soil_texture' => 'Loam',
            'rainfall_mm' => 2600,
            'notes' => 'Kebun lama di lereng; sebagian terbakar dan sebagian masih berkanopi.',
            'context' => ['elevation' => 1105.0, 'slope' => 18.4, 'rainfall' => 2600.0, 'temperature' => 21.2, 'ph' => 5.4],
            'periods' => [
                ['captured_at' => '2025-09-06', 'cover' => ['dense' => 20, 'sparse' => 18, 'bare' => 26, 'charred' => 24, 'water' => 0, 'built' => 2, 'other' => 10]],
                ['captured_at' => '2026-03-22', 'cover' => ['dense' => 40, 'sparse' => 26, 'bare' => 18, 'charred' => 10, 'water' => 0, 'built' => 2, 'other' => 4]],
            ],
        ]);

        $this->land($corporate, [
            'name' => 'Blok Pulang Pisau',
            'location_name' => 'Kab. Pulang Pisau, Kalimantan Tengah',
            'latitude' => -2.7456,
            'longitude' => 114.2678,
            'area_ha' => 12.5,
            'fire_event_date' => '2025-10-01',
            'soil_texture' => 'Clay Loam',
            'rainfall_mm' => 2800,
            'notes' => 'Blok restorasi CSR; satu periode monitoring, jadwal foto berikutnya jatuh tempo.',
            'context' => ['elevation' => 18.0, 'slope' => 0.8, 'rainfall' => 2800.0, 'temperature' => 26.8, 'ph' => 4.2],
            'periods' => [
                ['captured_at' => '2025-12-14', 'cover' => ['dense' => 2, 'sparse' => 6, 'bare' => 24, 'charred' => 60, 'water' => 4, 'built' => 0, 'other' => 4]],
            ],
        ]);

        $this->land($institution, [
            'name' => 'Restorasi Kubu Raya',
            'location_name' => 'Kab. Kubu Raya, Kalimantan Barat',
            'latitude' => -0.2145,
            'longitude' => 109.3412,
            'area_ha' => 6.0,
            'fire_event_date' => '2025-03-28',
            'soil_texture' => 'Silt Loam',
            'rainfall_mm' => 3050,
            'notes' => 'Pilot restorasi pemerintah daerah; progres paling jauh di antara lahan yang dipantau.',
            'context' => ['elevation' => 6.0, 'slope' => 1.1, 'rainfall' => 3050.0, 'temperature' => 27.9, 'ph' => 4.8],
            'periods' => [
                ['captured_at' => '2026-01-09', 'cover' => ['dense' => 24, 'sparse' => 28, 'bare' => 27, 'charred' => 12, 'water' => 3, 'built' => 1, 'other' => 5]],
                ['captured_at' => '2026-06-27', 'cover' => ['dense' => 66, 'sparse' => 24, 'bare' => 3, 'charred' => 2, 'water' => 3, 'built' => 1, 'other' => 1]],
            ],
        ]);

        // Registered, not yet photographed: the empty state a new farmer meets.
        $this->land($secondFarmer, [
            'name' => 'Lahan Siak',
            'location_name' => 'Kab. Siak, Riau',
            'area_ha' => 3.0,
            'fire_event_date' => '2026-02-11',
            'soil_texture' => 'Silt Loam',
            'rainfall_mm' => 2500,
            'notes' => 'Baru didaftarkan; menunggu foto lahan pertama.',
            'context' => [],
            'periods' => [],
        ]);
    }

    private function user(string $name, string $email, UserRole $role, ?string $organization = null): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'organization' => $organization,
        ]);
    }

    /**
     * Create a land, run every monitoring period through the real engines, and
     * leave the land status and carbon assessment consistent with the result.
     *
     * @param  array<string, mixed>  $spec
     */
    private function land(User $owner, array $spec): Land
    {
        $land = Land::create([
            'user_id' => $owner->id,
            'name' => $spec['name'],
            'objective' => 'restoration',
            'status' => LandStatus::Planned,
            'data_status' => 'demo',
            'location_name' => $spec['location_name'],
            'latitude' => $spec['latitude'] ?? null,
            'longitude' => $spec['longitude'] ?? null,
            'soil_texture' => $spec['soil_texture'] ?? null,
            'rainfall_mm' => $spec['rainfall_mm'] ?? null,
            'area_ha' => $spec['area_ha'],
            'fire_event_date' => $spec['fire_event_date'],
            'notes' => $spec['notes'] ?? null,
        ]);

        $intelligence = new LandIntelligenceEngine;
        $recommendations = new RestorationRecommendationEngine;
        $latest = null;

        foreach ($spec['periods'] as $period) {
            $result = $intelligence->derive(
                $this->perception($period['cover'], array_merge([
                    'latitude' => $spec['latitude'] ?? null,
                    'longitude' => $spec['longitude'] ?? null,
                ], $spec['context'])),
                [
                    'soil_texture' => $spec['soil_texture'] ?? null,
                    'rainfall_mm' => $spec['rainfall_mm'] ?? null,
                ],
            );

            $analysis = $land->analyses()->create([
                'user_id' => $owner->id,
                'image_filename' => null,
                'image_width' => null,
                'image_height' => null,
                'captured_at' => Carbon::parse($period['captured_at']),
                'capture_source' => 'phone',
                'latitude' => $spec['latitude'] ?? null,
                'longitude' => $spec['longitude'] ?? null,
                'notes' => 'Periode monitoring '.Carbon::parse($period['captured_at'])->translatedFormat('F Y'),
                'analysis_status' => AnalysisStatus::Processing,
                'analysis_started_at' => Carbon::parse($period['captured_at'])->addHour(),
            ]);

            $analysis->recordResult($result, $recommendations->recommend($result));

            $latest = $analysis;
        }

        if ($latest !== null) {
            $land->forceFill(['status' => LandStatus::fromHealthScore($latest->land_health_score)])->save();
            $this->carbon($land, $spec['carbon'] ?? null);
        }

        return $land;
    }

    /**
     * Screen the land with the real assessor, and file an application when the
     * scenario calls for one.
     *
     * @param  array{partner: string, contact: string}|null  $submission
     */
    private function carbon(Land $land, ?array $submission): void
    {
        $assessor = app(CarbonCreditAssessor::class);
        $assessment = $assessor->refresh($land->fresh(['analyses']));

        if ($submission === null) {
            return;
        }

        $assessment->forceFill([
            'partner' => $submission['partner'],
            'contact_name' => $land->user->name,
            'contact_email' => $submission['contact'],
            'offered_area_ha' => $land->area_ha,
            'notes' => 'Diajukan lewat platform re:green; menunggu verifikasi mitra.',
            'submission_status' => CarbonSubmissionStatus::Submitted,
            'submitted_at' => now()->subDays(12),
        ])->save();
    }

    /**
     * A perception payload shaped exactly like the ML service's response.
     *
     * @param  array<string, float|int>  $cover
     * @param  array<string, float|null>  $context
     * @return array<string, mixed>
     */
    private function perception(array $cover, array $context): array
    {
        $perception = [
            'land_cover' => [
                'dense_vegetation' => (float) $cover['dense'],
                'sparse_vegetation' => (float) $cover['sparse'],
                'bare_soil' => (float) $cover['bare'],
                'charred_soil' => (float) $cover['charred'],
                'water' => (float) $cover['water'],
                'built_area' => (float) $cover['built'],
                'other' => (float) $cover['other'],
            ],
            'confidence' => [
                'overall' => 0.9,
                'vegetation' => 0.92,
                'bare_soil' => 0.86,
                'water' => 0.8,
            ],
            'terrain' => $this->terrain($context),
            'soil' => $this->soil($context, $context['texture'] ?? 'Clay Loam'),
            'climate' => $this->climate($context),
            'image' => ['width' => 4000, 'height' => 3000],
            'model' => [
                'name' => 'yolov8n-burn',
                'version' => '0.2.0',
                'task' => 'detect',
                'weights' => 'models/regreen-burn-yolov8n.pt',
                'device' => 'cpu',
            ],
        ];

        // The severity block the ML service would report; the engine derives it
        // from the cover mix when a scenario has no detector output at all.
        $perception['fire_severity'] = $this->severity($cover);
        $perception['detections'] = [];

        return $perception;
    }

    /**
     * @param  array<string, float|int>  $cover
     * @return array<string, mixed>|null
     */
    private function severity(array $cover): ?array
    {
        $evidence = $cover['charred'] + $cover['bare'] + $cover['sparse'] + $cover['dense'];

        if ($evidence <= 0) {
            return null;
        }

        $score = ($cover['charred'] * 100 + $cover['bare'] * 60 + $cover['sparse'] * 20) / $evidence;
        $level = match (true) {
            $score < 10 => 'unburned',
            $score < 35 => 'low',
            $score < 65 => 'moderate',
            default => 'high',
        };

        return [
            'available' => true,
            'reason' => null,
            'level' => $level,
            'label' => ucfirst($level),
            'score' => round($score, 1),
            'confidence' => 0.88,
            'method' => 'yolov8',
            'evidence' => [
                'charred_soil_pct' => (float) $cover['charred'],
                'bare_soil_pct' => (float) $cover['bare'],
                'vegetation_pct' => (float) $cover['dense'] + $cover['sparse'],
            ],
        ];
    }

    /**
     * @param  array<string, float|null>  $context
     * @return array<string, mixed>
     */
    private function terrain(array $context): array
    {
        if (! isset($context['elevation'])) {
            return ['available' => false, 'reason' => 'Titik lahan belum punya koordinat, sehingga DEM tidak bisa disampel.'];
        }

        return [
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
            'sampled_at' => ['latitude' => $context['latitude'] ?? null, 'longitude' => $context['longitude'] ?? null],
            'profile' => [
                'elevation_m' => $context['elevation'],
                'slope_deg' => $context['slope'],
                'aspect_deg' => 148.0,
                'hillshade' => 0.42,
                'ruggedness_m' => 3.4,
                'resolution_m' => 8.3,
                'window' => ['rows' => 3, 'cols' => 3, 'spacing_x_m' => 8.3, 'spacing_y_m' => 8.3],
            ],
        ];
    }

    /**
     * @param  array<string, float|null>  $context
     * @return array<string, mixed>
     */
    private function soil(array $context, string $texture): array
    {
        if (! isset($context['ph'])) {
            return [
                'available' => false,
                'reason' => 'ISRIC SoilGrids tidak bisa dihubungi untuk titik ini: ConnectTimeout.',
            ];
        }

        $fractions = self::TEXTURES[$texture] ?? self::TEXTURES['Clay Loam'];

        return [
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
            'sampled_at' => ['latitude' => $context['latitude'] ?? null, 'longitude' => $context['longitude'] ?? null],
            'depth_cm' => 5,
            'resolution_m' => 250.0,
            'profile' => [
                'texture' => [
                    'class_name' => $texture,
                    'sand_pct' => $fractions['sand'],
                    'silt_pct' => $fractions['silt'],
                    'clay_pct' => $fractions['clay'],
                ],
                'subsoil_texture' => [
                    'class_name' => $texture,
                    'sand_pct' => $fractions['sand'],
                    'silt_pct' => $fractions['silt'],
                    'clay_pct' => $fractions['clay'],
                ],
                'ph' => $context['ph'],
                'organic_carbon_g_kg' => 42.5,
                'nitrogen_g_kg' => 3.1,
            ],
        ];
    }

    /**
     * @param  array<string, float|null>  $context
     * @return array<string, mixed>
     */
    private function climate(array $context): array
    {
        if (! isset($context['rainfall'])) {
            return [
                'available' => false,
                'reason' => 'NASA POWER tidak bisa dihubungi untuk titik ini: ConnectTimeout. Rekomendasi memakai curah hujan yang diisi petani.',
            ];
        }

        $annual = $context['rainfall'];
        $monthly = [
            'JAN' => round($annual * 0.11, 1), 'FEB' => round($annual * 0.10, 1),
            'MAR' => round($annual * 0.11, 1), 'APR' => round($annual * 0.10, 1),
            'MAY' => round($annual * 0.08, 1), 'JUN' => round($annual * 0.07, 1),
            'JUL' => round($annual * 0.05, 1), 'AUG' => round($annual * 0.04, 1),
            'SEP' => round($annual * 0.06, 1), 'OCT' => round($annual * 0.08, 1),
            'NOV' => round($annual * 0.10, 1), 'DEC' => round($annual * 0.10, 1),
        ];

        return [
            'available' => true,
            'reason' => null,
            'source' => [
                'id' => 'nasa-power',
                'label' => 'NASA POWER',
                'dataset' => 'NASA POWER agroclimatology (2001-2020)',
                'provider' => 'NASA Langley Research Center',
                'service_url' => 'https://power.larc.nasa.gov/api/temporal/climatology/point',
                'portal_url' => 'https://power.larc.nasa.gov',
            ],
            'sampled_at' => ['latitude' => $context['latitude'] ?? null, 'longitude' => $context['longitude'] ?? null],
            'profile' => [
                'annual_rainfall_mm' => $annual,
                'monthly_rainfall_mm' => $monthly,
                'dry_months' => 2,
                'dry_month_names' => ['JUL', 'AUG'],
                'wet_months' => 8,
                'driest_month' => ['month' => 'AUG', 'rainfall_mm' => $monthly['AUG']],
                'wettest_month' => ['month' => 'JAN', 'rainfall_mm' => $monthly['JAN']],
                'mean_temperature_c' => $context['temperature'],
                'mean_daily_max_c' => round(($context['temperature'] ?? 26.0) + 4.2, 1),
                'mean_humidity_pct' => 84.5,
                'topsoil_wetness_pct' => 62.0,
            ],
        ];
    }
}
