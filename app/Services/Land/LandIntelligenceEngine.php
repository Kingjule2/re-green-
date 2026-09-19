<?php

namespace App\Services\Land;

use App\Enums\BurnSeverity;
use App\Services\Agriculture\AgricultureEngine;
use App\Services\Ml\LandAnalysisClient;

/**
 * Land Intelligence Engine.
 *
 * Turns a raw land-cover perception result (produced by the Computer Vision
 * model behind {@see LandAnalysisClient}) into structured land
 * intelligence: a health score, headline metrics, and detected problems.
 *
 * When the survey carried coordinates, the perception also contains the
 * environmental context sampled around the point: terrain from a Digital
 * Elevation Model (BIG DEMNAS inside Indonesia, NASA SRTM elsewhere), soil
 * properties from ISRIC SoilGrids, and climate normals from NASA POWER. That
 * context is what tells a floodplain from a ridge and a 3000 mm valley from a
 * 900 mm one, so it upgrades the result from "how much vegetation" to "what can
 * this land carry": an elevation band and slope class for erosion and landslide
 * exposure, soil texture and pH for what will grow, and the rainfall regime for
 * how much water management the crop needs.
 *
 * Crop ranking and rainfall impact are delegated to {@see AgricultureEngine};
 * this engine owns the land-cover intelligence and normalizes the context
 * blocks that everything downstream reads.
 *
 * Every value produced here is an AI- or dataset-derived estimate, never ground
 * truth. The logic is fully deterministic so the same perception always yields
 * the same intelligence, which keeps the pipeline testable and explainable.
 */
class LandIntelligenceEngine
{
    private AgricultureEngine $agriculture;

    public function __construct(?AgricultureEngine $agriculture = null)
    {
        $this->agriculture = $agriculture ?? new AgricultureEngine;
    }

    /**
     * Display metadata for each supported land-cover class.
     *
     * @var array<string, array{label: string, color: string}>
     */
    private const CLASSES = [
        'dense_vegetation' => ['label' => 'Dense Vegetation', 'color' => '#15803d'],
        'sparse_vegetation' => ['label' => 'Sparse Vegetation', 'color' => '#84cc16'],
        'bare_soil' => ['label' => 'Bare Soil', 'color' => '#b45309'],
        'charred_soil' => ['label' => 'Charred Soil', 'color' => '#3f3f46'],
        'water' => ['label' => 'Water', 'color' => '#0ea5e9'],
        'built_area' => ['label' => 'Built Area', 'color' => '#78716c'],
        'other' => ['label' => 'Other', 'color' => '#94a3b8'],
    ];

    /**
     * Slope classes, in degrees, following the FAO slope classes collapsed to
     * the five bands the recommendation engine needs. Percentages decide the
     * practical consequence: erosion risk, mechanisation, and whether an annual
     * crop is viable at all without terracing.
     *
     * @var list<array{key: string, max_deg: float}>
     */
    private const SLOPE_CLASSES = [
        ['key' => 'flat', 'max_deg' => 2.0],
        ['key' => 'gentle', 'max_deg' => 8.0],
        ['key' => 'sloping', 'max_deg' => 15.0],
        ['key' => 'steep', 'max_deg' => 30.0],
        ['key' => 'very_steep', 'max_deg' => 90.0],
    ];

    /**
     * Indonesian agro-ecological elevation bands. Which crops are viable is
     * decided mostly by temperature, and temperature is decided by elevation,
     * which is why "tanaman cocok di ketinggian sekian" needs this band.
     *
     * The first and last bands are open-ended: `null` there means "no bound",
     * which keeps the values finite for the JSON column the analysis is stored
     * in. Mountains stop at 8848 m; anything higher simply lands in the last
     * band. The crops each band supports are ranked by
     * {@see AgricultureEngine} rather than listed here.
     *
     * @var list<array{key: string, label: string, min_m: float|null, max_m: float|null}>
     */
    private const ELEVATION_BANDS = [
        ['key' => 'lowland', 'label' => 'Lowland', 'min_m' => null, 'max_m' => 200.0],
        ['key' => 'lower_montane', 'label' => 'Lower montane', 'min_m' => 200.0, 'max_m' => 700.0],
        ['key' => 'upper_montane', 'label' => 'Upper montane', 'min_m' => 700.0, 'max_m' => 1500.0],
        ['key' => 'highland', 'label' => 'Highland', 'min_m' => 1500.0, 'max_m' => 2500.0],
        ['key' => 'alpine', 'label' => 'Alpine', 'min_m' => 2500.0, 'max_m' => null],
    ];

    /**
     * Derive the canonical analysis result from a normalized perception result.
     *
     * @param  array{land_cover?: array<string, float|int>, confidence?: array<string, float|int>, segmentation?: mixed, fire_severity?: array<string, mixed>|null, detections?: list<array<string, mixed>>, image?: array<string, int|null>, model?: array{name?: string, version?: string, task?: string|null, weights?: string|null, device?: string|null}}  $perception
     * @param  array{soil_texture?: string|null, rainfall_mm?: int|null}  $site  the land's declared location data, used by the agriculture engine whenever the ML service could not sample a dataset
     * @return array<string, mixed>
     */
    public function derive(array $perception, array $site = []): array
    {
        $model = is_array($perception['model'] ?? null) ? $perception['model'] : [];
        $cover = $this->normalizeCover($perception['land_cover'] ?? []);
        $confidence = $perception['confidence'] ?? [];
        $overallConfidence = (float) ($confidence['overall'] ?? 0.85);

        $vegetation = round($cover['dense_vegetation'] + $cover['sparse_vegetation'], 1);
        $bareSoil = round($cover['bare_soil'], 1);
        $charred = round($cover['charred_soil'], 1);
        $water = round($cover['water'], 1);
        // Charred ground counts as fully degraded: it lost the organic layer,
        // not just the plant cover that bare soil lost.
        $degraded = round(min(100, $charred + $bareSoil + 0.5 * $cover['sparse_vegetation']), 1);

        $score = $this->healthScore($vegetation, $cover['dense_vegetation'], $water, $bareSoil, $charred, $degraded);
        $terrain = $this->terrain($perception['terrain'] ?? null);
        $soil = $this->soil($perception['soil'] ?? null);
        $climate = $this->climate($perception['climate'] ?? null);

        return [
            'land_health' => [
                'score' => $score,
                'status' => $this->healthStatus($score),
            ],
            'burn_severity' => $this->burnSeverity($perception['fire_severity'] ?? null, $cover, $confidence),
            'detections' => $this->detections($perception['detections'] ?? null),
            'land_cover' => $this->coverBreakdown($cover),
            'metrics' => [
                'vegetation_coverage' => $vegetation,
                'bare_soil' => $bareSoil,
                'charred_soil' => $charred,
                'water_presence' => $water,
                'degraded_area' => $degraded,
                'restoration_potential' => $this->restorationPotential($vegetation, $degraded),
                // Hectares require a reliable ground sample distance, which a
                // single image cannot provide. Never fabricate an area estimate.
                'estimated_area_hectares' => null,
            ],
            'terrain' => $terrain,
            'soil' => $soil,
            'climate' => $climate,
            'agriculture' => $this->agriculture->assess($terrain, $soil, $climate, [
                'health_score' => $score,
                'bare_soil' => $bareSoil,
                'vegetation' => $vegetation,
                // Declared location data: the PRD's rule-based path combines the
                // detected condition with the soil texture and rainfall the
                // farmer recorded when no dataset could be sampled for the point.
                'declared_soil_texture' => $site['soil_texture'] ?? null,
                'declared_rainfall_mm' => $site['rainfall_mm'] ?? null,
            ]),
            'issues' => $this->detectIssues($vegetation, $bareSoil, $charred, $degraded, $cover, $confidence, $terrain),
            'confidence' => [
                'overall' => round($overallConfidence, 2),
                'vegetation_detection' => round((float) ($confidence['vegetation'] ?? $overallConfidence), 2),
                'bare_soil_detection' => round((float) ($confidence['bare_soil'] ?? $overallConfidence), 2),
                'water_detection' => round((float) ($confidence['water'] ?? $overallConfidence), 2),
            ],
            'segmentation' => $this->segmentation($perception['segmentation'] ?? null),
            'model' => [
                'name' => is_string($model['name'] ?? null) && $model['name'] !== '' ? $model['name'] : 'unknown',
                'version' => is_string($model['version'] ?? null) && $model['version'] !== '' ? $model['version'] : '0.0.0',
                'task' => is_string($model['task'] ?? null) ? $model['task'] : null,
                'weights' => is_string($model['weights'] ?? null) ? $model['weights'] : null,
                'device' => is_string($model['device'] ?? null) ? $model['device'] : null,
            ],
            'image' => [
                'width' => $perception['image']['width'] ?? null,
                'height' => $perception['image']['height'] ?? null,
            ],
        ];
    }

    /**
     * Coerce arbitrary cover input into the full class set and normalize the
     * percentages so they sum to 100.
     *
     * @param  array<string, float|int>  $raw
     * @return array<string, float>
     */
    private function normalizeCover(array $raw): array
    {
        $cover = [];
        foreach (array_keys(self::CLASSES) as $key) {
            $cover[$key] = max(0.0, (float) ($raw[$key] ?? 0.0));
        }

        $total = array_sum($cover);
        if ($total <= 0) {
            $cover['other'] = 100.0;

            return $cover;
        }

        foreach ($cover as $key => $value) {
            $cover[$key] = round($value / $total * 100, 1);
        }

        return $cover;
    }

    /**
     * Compute the 0-100 land health score.
     *
     * Vegetation drives the score up (canopy density adds a bonus); exposed
     * soil and degradation drive it down, and charred ground is penalised on
     * top of the degradation it already counts into. The result is clamped to
     * 0-100.
     */
    private function healthScore(float $vegetation, float $dense, float $water, float $bareSoil, float $charred, float $degraded): int
    {
        $raw = $vegetation * 0.8
            + $dense * 0.2
            + $water * 0.2
            - $degraded * 0.5
            - $bareSoil * 0.2
            - $charred * 0.3
            + 6;

        return (int) max(0, min(100, round($raw)));
    }

    /**
     * Map a score to its health classification band.
     */
    private function healthStatus(int $score): string
    {
        return match (true) {
            $score >= 80 => 'healthy',
            $score >= 60 => 'moderate',
            $score >= 40 => 'degraded',
            default => 'critical',
        };
    }

    /**
     * Estimate ecological restoration potential from detected conditions.
     */
    private function restorationPotential(float $vegetation, float $degraded): string
    {
        return match (true) {
            $vegetation < 45 || $degraded >= 25 => 'high',
            $vegetation < 65 || $degraded >= 12 => 'medium',
            default => 'low',
        };
    }

    /**
     * Build the ordered land-cover breakdown for stacked visualization.
     *
     * @param  array<string, float>  $cover
     * @return list<array{key: string, label: string, color: string, percentage: float}>
     */
    private function coverBreakdown(array $cover): array
    {
        $breakdown = [];
        foreach (self::CLASSES as $key => $meta) {
            $breakdown[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'percentage' => $cover[$key],
            ];
        }

        usort($breakdown, fn (array $a, array $b): int => $b['percentage'] <=> $a['percentage']);

        return $breakdown;
    }

    /**
     * Detect potential land issues from the derived metrics.
     *
     * Terrain-driven issues are added only when a DEM was actually sampled:
     * without an elevation model there is nothing honest to say about slope.
     *
     * @param  array<string, float>  $cover
     * @param  array<string, float|int>  $confidence
     * @param  array<string, mixed>  $terrain
     * @return list<array<string, mixed>>
     */
    private function detectIssues(float $vegetation, float $bareSoil, float $charred, float $degraded, array $cover, array $confidence, array $terrain): array
    {
        $issues = [];
        $vegConfidence = round((float) ($confidence['vegetation'] ?? $confidence['overall'] ?? 0.85), 2);
        $soilConfidence = round((float) ($confidence['bare_soil'] ?? $confidence['overall'] ?? 0.8), 2);

        // Charred ground first: it is the direct evidence of the fire the
        // restoration is responding to, and it outranks the generic soil issue.
        if ($charred >= 10.0) {
            $issues[] = [
                'type' => 'burn_scar',
                'title' => 'Burn Scar',
                'severity' => $charred >= 30 ? 'high' : 'medium',
                'affected_area' => $charred,
                'confidence' => $soilConfidence,
                'description' => 'Charred ground was detected: the fire consumed the surface layer, so organic matter and seed bank have to be rebuilt before cover returns.',
            ];
        }

        if ($vegetation < 65) {
            $severity = $vegetation < 40 ? 'high' : ($vegetation < 55 ? 'medium' : 'low');
            $issues[] = [
                'type' => 'vegetation_loss',
                'title' => 'Vegetation Loss',
                'severity' => $severity,
                'affected_area' => round(max(0, 100 - $vegetation) * 0.5 + $cover['sparse_vegetation'] * 0.5, 1),
                'confidence' => $vegConfidence,
                'description' => 'Significant areas show sparse or absent vegetation coverage.',
            ];
        }

        if ($bareSoil >= 10) {
            $issues[] = [
                'type' => 'soil_exposure',
                'title' => 'Soil Exposure',
                'severity' => $bareSoil >= 25 ? 'high' : 'medium',
                'affected_area' => $bareSoil,
                'confidence' => $soilConfidence,
                'description' => 'Exposed soil areas may indicate vegetation degradation or potential erosion risk.',
            ];
        }

        if ($cover['sparse_vegetation'] > $cover['dense_vegetation'] && $vegetation >= 20) {
            $issues[] = [
                'type' => 'fragmented_vegetation',
                'title' => 'Fragmented Vegetation',
                'severity' => 'medium',
                'affected_area' => round($cover['sparse_vegetation'], 1),
                'confidence' => $vegConfidence,
                'description' => 'Vegetation appears fragmented across the analyzed area.',
            ];
        }

        if (($terrain['available'] ?? false) === true) {
            $slope = (float) $terrain['profile']['slope_deg'];

            // Steep ground plus exposed soil is the erosion recipe: runoff
            // accelerates with slope and has nothing holding the topsoil back.
            if ($slope >= 15.0 && ($bareSoil >= 10.0 || $degraded >= 15.0)) {
                $issues[] = [
                    'type' => 'slope_erosion_risk',
                    'title' => 'Slope Erosion Risk',
                    'severity' => $slope >= 30.0 ? 'high' : 'medium',
                    'affected_area' => round(max($bareSoil, $degraded), 1),
                    'confidence' => $soilConfidence,
                    'description' => "A {$slope}° slope with exposed soil sheds runoff and topsoil downhill.",
                ];
            }

            // Steep ground with little root reinforcement is where a heavy
            // rain season after a disaster turns into landslides and debris flows.
            if ($slope >= 25.0 && $vegetation < 65.0) {
                $issues[] = [
                    'type' => 'slope_instability',
                    'title' => 'Slope Instability',
                    'severity' => $slope >= 35.0 ? 'high' : 'medium',
                    'affected_area' => round(100 - $vegetation, 1),
                    'confidence' => $vegConfidence,
                    'description' => 'Steep, sparsely vegetated ground is prone to landslides and debris flows, especially in the rainy season after a disaster.',
                ];
            }
        }

        return $issues;
    }

    /**
     * The fire severity of the analysed photo.
     *
     * The ML service normally reports this itself (YOLOv8 detections, or the
     * colour heuristic). When it does not, the severity is derived from the
     * cover mix with the same weights the model uses — charred soil 100, bare
     * soil 60, regrowth 20, intact canopy 0 — so a level means the same thing
     * whichever path produced it.
     *
     * @param  array<string, float>  $cover  normalized land-cover percentages
     * @param  array<string, float|int>  $confidence
     * @return array<string, mixed>
     */
    private function burnSeverity(mixed $raw, array $cover, array $confidence): array
    {
        $raw = is_array($raw) ? $raw : [];
        $dense = $cover['dense_vegetation'];
        $sparse = $cover['sparse_vegetation'];

        $score = isset($raw['score'])
            ? (float) $raw['score']
            : $this->severityFromCover($dense, $sparse, $cover['bare_soil'], $cover['charred_soil']);

        $level = isset($raw['level']) ? BurnSeverity::tryFrom((string) $raw['level']) : null;
        $level ??= BurnSeverity::fromScore($score);

        $reason = $raw['reason'] ?? null;
        $evidence = is_array($raw['evidence'] ?? null) ? $raw['evidence'] : [
            'charred_soil_pct' => $cover['charred_soil'],
            'bare_soil_pct' => $cover['bare_soil'],
            'vegetation_pct' => round($dense + $sparse, 1),
        ];

        return [
            'level' => $level->value,
            'label' => $level->label(),
            'short_label' => $level->shortLabel(),
            'color' => $level->color(),
            'score' => (int) max(0, min(100, round($score))),
            'confidence' => round((float) ($raw['confidence'] ?? $confidence['overall'] ?? 0.85), 2),
            'method' => is_string($raw['method'] ?? null) && $raw['method'] !== '' ? $raw['method'] : 'cover-derived',
            // Present when the model could not run and the service fell back,
            // so the UI and the report can say why in plain words.
            'reason' => is_string($reason) && $reason !== '' ? $reason : null,
            'evidence' => $evidence,
        ];
    }

    /**
     * Area-weighted severity of the detected cover, on the 0-100 scale the
     * detector classes use. Water and built area carry no burn evidence and are
     * excluded from the denominator.
     */
    private function severityFromCover(float $dense, float $sparse, float $bareSoil, float $charred): float
    {
        $evidence = $dense + $sparse + $bareSoil + $charred;

        if ($evidence <= 0.0) {
            return 0.0;
        }

        return ($charred * 100.0 + $bareSoil * 60.0 + $sparse * 20.0) / $evidence;
    }

    /**
     * The detection boxes the model returned, normalized to 0-1 so the UI can
     * draw them over any rendered size of the photo.
     *
     * @return list<array{label: string, confidence: float, box: list<float>}>
     */
    private function detections(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $detections = [];

        foreach ($raw as $detection) {
            if (! is_array($detection) || ! isset($detection['label'])) {
                continue;
            }

            $box = $detection['box'] ?? null;

            if (! is_array($box) || count($box) !== 4) {
                continue;
            }

            $detections[] = [
                'label' => (string) $detection['label'],
                'confidence' => round((float) ($detection['confidence'] ?? 0), 3),
                'box' => array_map(fn (mixed $value): float => round((float) $value, 4), array_values($box)),
            ];
        }

        return $detections;
    }

    /**
     * Normalize the perception's terrain context for the rest of the pipeline.
     *
     * The ML service reports `available => false` with a reason whenever no DEM
     * could be sampled, and that reason is passed through verbatim: a consumer
     * must be able to tell "flat land" from "we never measured it".
     *
     * @return array<string, mixed>
     */
    private function terrain(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        if (($raw['available'] ?? false) !== true) {
            $reason = $raw['reason'] ?? null;

            return [
                'available' => false,
                'reason' => is_string($reason) && $reason !== '' ? $reason : 'No terrain context was provided for this survey.',
                'source' => null,
                'sampled_at' => null,
                'profile' => null,
                'elevation_band' => null,
                'slope_class' => null,
                'aspect_label' => null,
            ];
        }

        $profile = is_array($raw['profile'] ?? null) ? $raw['profile'] : [];
        $elevation = (float) ($profile['elevation_m'] ?? 0);
        $slope = (float) ($profile['slope_deg'] ?? 0);
        $aspect = isset($profile['aspect_deg']) ? (float) $profile['aspect_deg'] : null;

        return [
            'available' => true,
            'reason' => null,
            'source' => $this->source($raw['source'] ?? null),
            'sampled_at' => $this->sampledAt($raw['sampled_at'] ?? null),
            'profile' => [
                'elevation_m' => round($elevation, 1),
                'slope_deg' => round($slope, 1),
                'aspect_deg' => $aspect === null ? null : round($aspect, 1),
                'hillshade' => round((float) ($profile['hillshade'] ?? 0), 3),
                'ruggedness_m' => round((float) ($profile['ruggedness_m'] ?? 0), 2),
                'resolution_m' => round((float) ($profile['resolution_m'] ?? 0), 1),
                'window' => is_array($profile['window'] ?? null)
                    ? $profile['window']
                    : ['rows' => null, 'cols' => null, 'spacing_x_m' => null, 'spacing_y_m' => null],
            ],
            // Derived from the profile above, so downstream code reads a band and
            // a class instead of re-deriving them per consumer.
            'elevation_band' => $this->elevationBand($elevation),
            'slope_class' => $this->slopeClass($slope),
            'aspect_label' => $this->aspectLabel($aspect),
        ];
    }

    /**
     * Normalize the perception's soil block for the rest of the pipeline.
     *
     * Soil is optional in exactly the same way terrain is: SoilGrids may be
     * switched off or unreachable, and the reason it could not answer is passed
     * through verbatim.
     *
     * @return array<string, mixed>
     */
    private function soil(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        if (($raw['available'] ?? false) !== true) {
            $reason = $raw['reason'] ?? null;

            return [
                'available' => false,
                'reason' => is_string($reason) && $reason !== '' ? $reason : 'No soil context was provided for this survey.',
                'source' => null,
                'sampled_at' => null,
                'depth_cm' => null,
                'resolution_m' => null,
                'profile' => null,
            ];
        }

        $profile = is_array($raw['profile'] ?? null) ? $raw['profile'] : [];

        return [
            'available' => true,
            'reason' => null,
            'source' => $this->source($raw['source'] ?? null),
            'sampled_at' => $this->sampledAt($raw['sampled_at'] ?? null),
            'depth_cm' => isset($raw['depth_cm']) ? (int) $raw['depth_cm'] : null,
            'resolution_m' => isset($raw['resolution_m']) ? round((float) $raw['resolution_m'], 1) : null,
            'profile' => [
                'texture' => $this->texture($profile['texture'] ?? null),
                'subsoil_texture' => $this->texture($profile['subsoil_texture'] ?? null),
                'ph' => (float) ($profile['ph'] ?? 0),
                'organic_carbon_g_kg' => (float) ($profile['organic_carbon_g_kg'] ?? 0),
                'nitrogen_g_kg' => (float) ($profile['nitrogen_g_kg'] ?? 0),
            ],
        ];
    }

    /**
     * Normalize the perception's climate block for the rest of the pipeline.
     *
     * @return array<string, mixed>
     */
    private function climate(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        if (($raw['available'] ?? false) !== true) {
            $reason = $raw['reason'] ?? null;

            return [
                'available' => false,
                'reason' => is_string($reason) && $reason !== '' ? $reason : 'No climate context was provided for this survey.',
                'source' => null,
                'sampled_at' => null,
                'profile' => null,
            ];
        }

        $profile = is_array($raw['profile'] ?? null) ? $raw['profile'] : [];
        $monthly = is_array($profile['monthly_rainfall_mm'] ?? null) ? $profile['monthly_rainfall_mm'] : [];

        return [
            'available' => true,
            'reason' => null,
            'source' => $this->source($raw['source'] ?? null),
            'sampled_at' => $this->sampledAt($raw['sampled_at'] ?? null),
            'profile' => [
                'annual_rainfall_mm' => round((float) ($profile['annual_rainfall_mm'] ?? 0), 1),
                'monthly_rainfall_mm' => array_map(fn (mixed $mm): float => round((float) $mm, 1), $monthly),
                'dry_months' => (int) ($profile['dry_months'] ?? 0),
                'dry_month_names' => array_values((array) ($profile['dry_month_names'] ?? [])),
                'wet_months' => (int) ($profile['wet_months'] ?? 0),
                'driest_month' => $this->month($profile['driest_month'] ?? null),
                'wettest_month' => $this->month($profile['wettest_month'] ?? null),
                'mean_temperature_c' => round((float) ($profile['mean_temperature_c'] ?? 0), 1),
                'mean_daily_max_c' => $this->nullableFloat($profile['mean_daily_max_c'] ?? null),
                'mean_humidity_pct' => $this->nullableFloat($profile['mean_humidity_pct'] ?? null),
                'topsoil_wetness_pct' => $this->nullableFloat($profile['topsoil_wetness_pct'] ?? null),
            ],
        ];
    }

    /**
     * A provenance block, or null when the sampler could not attribute it.
     *
     * @return array<string, mixed>|null
     */
    private function source(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        return [
            'id' => $raw['id'] ?? 'unknown',
            'label' => $raw['label'] ?? 'unknown',
            'dataset' => $raw['dataset'] ?? '',
            'provider' => $raw['provider'] ?? '',
            'service_url' => $raw['service_url'] ?? '',
            'portal_url' => $raw['portal_url'] ?? '',
        ];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}|null
     */
    private function sampledAt(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        return [
            'latitude' => isset($raw['latitude']) ? (float) $raw['latitude'] : null,
            'longitude' => isset($raw['longitude']) ? (float) $raw['longitude'] : null,
        ];
    }

    /**
     * A soil texture block.
     *
     * @return array{class_name: string, sand_pct: float, silt_pct: float, clay_pct: float}
     */
    private function texture(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        return [
            'class_name' => (string) ($raw['class_name'] ?? 'unknown'),
            'sand_pct' => (float) ($raw['sand_pct'] ?? 0),
            'silt_pct' => (float) ($raw['silt_pct'] ?? 0),
            'clay_pct' => (float) ($raw['clay_pct'] ?? 0),
        ];
    }

    /**
     * A named rainfall extreme.
     *
     * @return array{month: string, rainfall_mm: float}|null
     */
    private function month(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        return [
            'month' => (string) ($raw['month'] ?? ''),
            'rainfall_mm' => round((float) ($raw['rainfall_mm'] ?? 0), 1),
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * The elevation band a point falls in.
     *
     * @return array{key: string, label: string, min_m: float|null, max_m: float|null}
     */
    private function elevationBand(float $elevation): array
    {
        foreach (self::ELEVATION_BANDS as $band) {
            if ($band['max_m'] === null || $elevation < $band['max_m']) {
                return $band;
            }
        }

        return self::ELEVATION_BANDS[array_key_last(self::ELEVATION_BANDS)];
    }

    /**
     * The slope class key for a gradient in degrees.
     */
    private function slopeClass(float $slope): string
    {
        foreach (self::SLOPE_CLASSES as $class) {
            if ($slope < $class['max_deg']) {
                return $class['key'];
            }
        }

        return self::SLOPE_CLASSES[array_key_last(self::SLOPE_CLASSES)]['key'];
    }

    /**
     * Eight-way compass label for the direction a slope faces.
     */
    private function aspectLabel(?float $aspect): string
    {
        if ($aspect === null) {
            return 'flat';
        }

        $sectors = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) floor((fmod($aspect, 360.0) + 22.5) / 45.0) % 8;

        return $sectors[$index];
    }

    /**
     * Pass a coarse segmentation grid through when the model provided one.
     *
     * @return array{available: bool, cols: int|null, rows: int|null, grid: mixed, legend: mixed}
     */
    private function segmentation(mixed $segmentation): array
    {
        if (! is_array($segmentation) || empty($segmentation['grid'])) {
            return ['available' => false, 'cols' => null, 'rows' => null, 'grid' => null, 'legend' => null];
        }

        return [
            'available' => true,
            'cols' => $segmentation['cols'] ?? null,
            'rows' => $segmentation['rows'] ?? null,
            'grid' => $segmentation['grid'],
            'legend' => $segmentation['legend'] ?? array_map(
                fn (array $meta): string => $meta['color'],
                self::CLASSES,
            ),
        ];
    }
}
