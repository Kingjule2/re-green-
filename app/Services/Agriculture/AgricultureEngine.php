<?php

namespace App\Services\Agriculture;

/**
 * Agriculture Engine.
 *
 * Turns the measured context — terrain (elevation, slope), soil (texture, pH,
 * organic carbon) and climate (rainfall distribution, temperature) — into what
 * the land can actually grow, and what the weather will do to it:
 *
 * * **capacity** — the land's own verdict from slope and elevation band, before
 *   any crop is considered.
 * * **crops** — every crop in {@see CropCatalog} ranked by
 *   {@see CropSuitabilityEngine} against this site.
 * * **rainfall impact** — erosivity (rain energy on that gradient), drainage
 *   risk (frequent wet months on flat heavy soil), and the dry season that has
 *   to be bridged with stored water.
 *
 * Nothing here is invented: a block that was not measured is reported as
 * missing (`missing_inputs`) and lowers what the engine claims, rather than
 * being filled in with a default.
 */
class AgricultureEngine
{
    /**
     * Suitability levels, ordered worst to best, so two limits can be combined
     * by taking the more restrictive one.
     *
     * @var array<string, int>
     */
    private const SUITABILITY = ['unsuitable' => 0, 'low' => 1, 'moderate' => 2, 'high' => 3];

    /**
     * How many crops the response carries. All of them are still scored.
     */
    private const CROP_LIMIT = 5;

    /**
     * How many site parameters — sampled or declared — a crop ranking needs
     * before it is worth showing, one of which must be rainfall or temperature.
     * Three is the platform's minimum case: the detected land condition plus
     * the soil texture and rainfall a farmer declared for a land without
     * coordinates.
     */
    private const MIN_RANKING_INPUTS = 3;

    /**
     * Rainfall classes in mm per year, as (upper bound, index, label).
     *
     * @var list<array{max: float, index: int, label: string}>
     */
    private const RAINFALL_CLASSES = [
        ['max' => 1000.0, 'index' => 0, 'label' => 'low'],
        ['max' => 1500.0, 'index' => 1, 'label' => 'moderate'],
        ['max' => 2500.0, 'index' => 2, 'label' => 'high'],
        ['max' => 1.0e9, 'index' => 3, 'label' => 'very_high'],
    ];

    /**
     * Slope class keys mapped to the same 0-3 scale, so erosion risk can be read
     * as the product of how hard it rains and how steep the ground is.
     *
     * @var array<string, int>
     */
    private const SLOPE_RISK_INDEX = ['flat' => 0, 'gentle' => 0, 'sloping' => 1, 'steep' => 2, 'very_steep' => 3];

    /**
     * Textures that hold water too long to drain freely.
     *
     * @var list<string>
     */
    private const HEAVY_TEXTURES = ['Clay', 'Silty Clay', 'Silty Clay Loam', 'Clay Loam'];

    public function __construct(private readonly CropSuitabilityEngine $crops = new CropSuitabilityEngine) {}

    /**
     * Assess the agriculture potential of a site.
     *
     * @param  array<string, mixed>  $terrain  normalized terrain block
     * @param  array<string, mixed>  $soil  normalized soil block
     * @param  array<string, mixed>  $climate  normalized climate block
     * @param  array{health_score: int, bare_soil: float, vegetation: float}  $land
     * @return array<string, mixed>
     */
    public function assess(array $terrain, array $soil, array $climate, array $land): array
    {
        $inputs = $this->inputs($terrain, $soil, $climate, $land);
        $slopeClass = $terrain['available'] ? $terrain['slope_class'] : null;
        $texture = ($soil['available'] ?? false)
            ? $soil['profile']['texture']['class_name']
            : ($land['declared_soil_texture'] ?? null);
        $rainfall = $this->rainfall($climate, $slopeClass, $texture);

        $ranking = $this->crops->rank($inputs);
        $measured = count($inputs) - count($ranking['missing_inputs']);
        // A ranking needs enough site parameters to mean something, and it
        // always needs the climate side: without rainfall or temperature there
        // is nothing to say about water or heat, which is most of what decides
        // whether a crop can grow here.
        $hasClimate = ($inputs['rainfall'] ?? null) !== null || ($inputs['temperature'] ?? null) !== null;
        $hasCore = $measured >= self::MIN_RANKING_INPUTS && $hasClimate;

        return [
            'assessable' => ($terrain['available'] ?? false)
                || ($soil['available'] ?? false)
                || ($climate['available'] ?? false)
                || ($land['declared_soil_texture'] ?? null) !== null
                || ($land['declared_rainfall_mm'] ?? null) !== null,
            'suitability' => $terrain['available'] ? $this->capacity($terrain) : null,
            'limiting_factors' => $this->limitingFactors($terrain, $soil, $climate, $rainfall, $land),
            'elevation_band' => $terrain['available'] ? $terrain['elevation_band'] : null,
            'slope_class' => $slopeClass,
            'soil_texture' => $texture,
            'crops' => $hasCore ? array_slice($ranking['crops'], 0, self::CROP_LIMIT) : [],
            'crop_count' => $ranking['scored_count'],
            'ranking_available' => $hasCore,
            'ranking_reason' => $hasCore ? null : sprintf(
                'crop suitability needs at least %d measured or declared site parameters including rainfall or temperature; this site has %d',
                self::MIN_RANKING_INPUTS,
                $measured,
            ),
            'rainfall' => $rainfall,
            'inputs' => $inputs,
            'input_sources' => $this->inputSources($terrain, $soil, $climate, $land),
            'missing_inputs' => $ranking['missing_inputs'],
        ];
    }

    /**
     * The measured values the crop engine scores, keyed by scoring parameter.
     *
     * A parameter the ML service could not sample falls back to what the farmer
     * declared on the land record; {@see inputSources()} records which one won,
     * so nothing downstream has to guess whether a number was measured.
     *
     * @return array<string, float|int|string|null>
     */
    private function inputs(array $terrain, array $soil, array $climate, array $land): array
    {
        return [
            'elevation' => $terrain['available'] ? $terrain['profile']['elevation_m'] : null,
            'slope' => $terrain['available'] ? $terrain['profile']['slope_deg'] : null,
            'temperature' => $climate['available'] ? $climate['profile']['mean_temperature_c'] : null,
            'rainfall' => $climate['available']
                ? $climate['profile']['annual_rainfall_mm']
                : ($land['declared_rainfall_mm'] ?? null),
            'soil_ph' => $soil['available'] ? $soil['profile']['ph'] : null,
            'soil_texture' => $soil['available']
                ? $soil['profile']['texture']['class_name']
                : ($land['declared_soil_texture'] ?? null),
            // NASA POWER's topsoil wetness stands in for the prototype's
            // soilMoisture input: both are a 0-100% state of the topsoil.
            'soil_moisture' => $climate['available'] ? $climate['profile']['topsoil_wetness_pct'] : null,
            'land_health' => $land['health_score'],
        ];
    }

    /**
     * Where each scored parameter came from: sampled from a dataset by the ML
     * service, declared by the user, or not known at all.
     *
     * @return array<string, string>
     */
    private function inputSources(array $terrain, array $soil, array $climate, array $land): array
    {
        $sampled = static fn (bool $available): string => $available ? 'sampled' : 'missing';
        $declared = static fn (bool $available, mixed $value): string => $available
            ? 'sampled'
            : ($value === null ? 'missing' : 'declared');

        return [
            'elevation' => $sampled($terrain['available'] ?? false),
            'slope' => $sampled($terrain['available'] ?? false),
            'temperature' => $sampled($climate['available'] ?? false),
            'rainfall' => $declared($climate['available'] ?? false, $land['declared_rainfall_mm'] ?? null),
            'soil_ph' => $sampled($soil['available'] ?? false),
            'soil_texture' => $declared($soil['available'] ?? false, $land['declared_soil_texture'] ?? null),
            'soil_moisture' => $sampled($climate['available'] ?? false),
            'land_health' => 'measured',
        ];
    }

    /**
     * The land's own capacity from slope and elevation band.
     *
     * @param  array<string, mixed>  $terrain
     */
    private function capacity(array $terrain): string
    {
        $bySlope = match ($terrain['slope_class']) {
            'very_steep' => 'unsuitable',
            'steep' => 'low',
            'sloping' => 'moderate',
            default => 'high',
        };
        $byElevation = match ($terrain['elevation_band']['key']) {
            'alpine' => 'low',
            'highland' => 'moderate',
            default => 'high',
        };

        return self::SUITABILITY[$bySlope] <= self::SUITABILITY[$byElevation] ? $bySlope : $byElevation;
    }

    /**
     * The conditions that limit what this land can carry, most fundamental first.
     *
     * @return list<array{key: string, label: string}>
     */
    private function limitingFactors(
        array $terrain,
        array $soil,
        array $climate,
        array $rainfall,
        array $land,
    ): array {
        $factors = [];
        $slope = ($terrain['available'] ?? false) ? (float) $terrain['profile']['slope_deg'] : null;

        if ($slope !== null && $slope >= 15.0) {
            $factors[] = [
                'key' => 'slope',
                'label' => "Steep slope ({$slope}°) rules out mechanised annual cropping without terracing.",
            ];
        }

        if (($terrain['available'] ?? false) && in_array($terrain['elevation_band']['key'], ['highland', 'alpine'], true)) {
            $factors[] = [
                'key' => 'elevation',
                'label' => "The {$terrain['elevation_band']['label']} elevation band narrows the crop set.",
            ];
        }

        if ($slope !== null && $slope >= 15.0 && $land['bare_soil'] >= 10.0) {
            $factors[] = [
                'key' => 'erosion',
                'label' => 'Exposed soil on this slope would erode before a crop could establish cover.',
            ];
        }

        if (($soil['available'] ?? false)) {
            $ph = (float) $soil['profile']['ph'];

            if ($ph < 5.5) {
                $factors[] = ['key' => 'soil_ph', 'label' => "Soil pH {$ph} is strongly acidic; most food crops want 5.5-7.0."];
            } elseif ($ph > 7.5) {
                $factors[] = ['key' => 'soil_ph', 'label' => "Soil pH {$ph} is alkaline; several nutrients become unavailable."];
            }

            if (in_array($soil['profile']['texture']['class_name'], ['Clay', 'Silty Clay'], true)) {
                $factors[] = ['key' => 'soil_texture', 'label' => 'Heavy clay soil is slow to drain and hard to work.'];
            }
        }

        if (($climate['available'] ?? false)) {
            if ($rainfall['dry_months'] >= 3) {
                $factors[] = [
                    'key' => 'dry_season',
                    'label' => "{$rainfall['dry_months']} dry months ({$rainfall['dry_season']}) need stored water or irrigation.",
                ];
            } elseif ($rainfall['dry_months'] >= 1) {
                $factors[] = [
                    'key' => 'dry_season',
                    'label' => "A {$rainfall['dry_months']}-month dry season ({$rainfall['dry_season']}) constrains rain-fed planting.",
                ];
            }

            if ($rainfall['drainage_risk'] === 'high') {
                $factors[] = ['key' => 'drainage', 'label' => 'Flat, heavy soil with frequent wet months drains poorly.'];
            }
        }

        return $factors;
    }

    /**
     * How the local rainfall pattern acts on this site.
     *
     * @return array<string, mixed>
     */
    private function rainfall(array $climate, ?string $slopeClass, ?string $texture): array
    {
        if (! ($climate['available'] ?? false)) {
            return [
                'available' => false,
                'reason' => $climate['reason'] ?? 'no climate context was provided for this survey',
                'annual_rainfall_mm' => null,
                'dry_months' => 0,
                'dry_season' => null,
                'dry_month_names' => [],
                'wet_months' => 0,
                'driest_month' => null,
                'wettest_month' => null,
                'erosivity' => null,
                'drainage_risk' => null,
                'impacts' => [],
            ];
        }

        $profile = $climate['profile'];
        $rainfallClass = $this->rainfallClass((float) $profile['annual_rainfall_mm']);
        $slopeIndex = $slopeClass === null ? null : (self::SLOPE_RISK_INDEX[$slopeClass] ?? null);

        // Erosion needs both: rain supplies the energy, the gradient supplies
        // the transport. Whichever is weaker caps the risk.
        $erosivity = ($slopeIndex === null)
            ? null
            : $this->riskLabel(min($rainfallClass['index'], $slopeIndex));

        $drainage = $this->drainageRisk($profile['wet_months'], $slopeClass, $texture);

        return [
            'available' => true,
            'reason' => null,
            'annual_rainfall_mm' => $profile['annual_rainfall_mm'],
            'dry_months' => $profile['dry_months'],
            'dry_season' => $profile['dry_month_names'] === [] ? null : implode(', ', $profile['dry_month_names']),
            'dry_month_names' => $profile['dry_month_names'],
            'wet_months' => $profile['wet_months'],
            'driest_month' => $profile['driest_month'],
            'wettest_month' => $profile['wettest_month'],
            'erosivity' => $erosivity,
            'drainage_risk' => $drainage,
            'impacts' => $this->impacts($profile, $rainfallClass['label'], $erosivity, $drainage),
        ];
    }

    /**
     * Human-readable consequences of the rainfall pattern.
     *
     * @param  array<string, mixed>  $profile
     * @return list<string>
     */
    private function impacts(array $profile, string $rainfallClass, ?string $erosivity, ?string $drainage): array
    {
        $impacts = [];

        if ($profile['dry_months'] > 0) {
            $impacts[] = sprintf(
                '%d dry months (%s) below 60 mm: rain-fed crops need stored water or irrigation to bridge the season.',
                $profile['dry_months'],
                implode(', ', $profile['dry_month_names']),
            );
        }

        if ($erosivity === 'high' || $erosivity === 'very_high') {
            $impacts[] = 'Rain energy on this gradient erodes bare soil, so keep the surface covered between harvests.';
        }

        if ($drainage === 'high') {
            $impacts[] = 'Frequent wet months on flat heavy soil drain poorly: plan drains before planting.';
        }

        if ((float) $profile['wettest_month']['rainfall_mm'] >= 300.0) {
            $impacts[] = sprintf(
                'Peak rainfall in %s (%s mm) can wash out young plants: plant after the peak and protect the rows.',
                $profile['wettest_month']['month'],
                $profile['wettest_month']['rainfall_mm'],
            );
        }

        if ((float) $profile['annual_rainfall_mm'] < 1000.0) {
            $impacts[] = "Total rainfall of {$profile['annual_rainfall_mm']} mm/yr is low ({$rainfallClass}), which limits rain-fed cropping.";
        }

        return $impacts;
    }

    /**
     * Rainfall class for an annual total.
     *
     * @return array{max: float, index: int, label: string}
     */
    private function rainfallClass(float $annualRainfallMm): array
    {
        foreach (self::RAINFALL_CLASSES as $class) {
            if ($annualRainfallMm < $class['max']) {
                return $class;
            }
        }

        return self::RAINFALL_CLASSES[array_key_last(self::RAINFALL_CLASSES)];
    }

    /**
     * Waterlogging needs three things at once: frequent rain, ground flat enough
     * that water cannot run off, and soil heavy enough to hold it.
     *
     * @param  list<string>  $wetMonths
     */
    private function drainageRisk(int $wetMonths, ?string $slopeClass, ?string $texture): ?string
    {
        if ($slopeClass === null) {
            return null;
        }

        // Any real gradient sheds water before it can pond.
        if (in_array($slopeClass, ['sloping', 'steep', 'very_steep'], true)) {
            return 'low';
        }

        $score = ($wetMonths >= 8 ? 2 : ($wetMonths >= 5 ? 1 : 0))
            + ($texture !== null && in_array($texture, self::HEAVY_TEXTURES, true) ? 1 : 0);

        return match (true) {
            $score >= 2 => 'high',
            $score === 1 => 'moderate',
            default => 'low',
        };
    }

    /**
     * A 0-3 risk index as its label.
     */
    private function riskLabel(int $index): string
    {
        return ['low', 'moderate', 'high', 'very_high'][max(0, min(3, $index))];
    }
}
