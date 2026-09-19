<?php

namespace App\Services\Agriculture;

/**
 * Crop Suitability Engine.
 *
 * Weighted multi-criteria scoring: each parameter a crop cares about gets a
 * sub-score from 0 to 100 for how well the site's measured value fits that
 * crop's envelope, the sub-scores are combined with the crop's own weights, and
 * the total is classified.
 *
 *   Very Suitable  85-100
 *   Suitable       70-84
 *   Moderately Suitable 50-69
 *   Not Suitable   <50
 *
 * Ported from the ReGreen prototype's `engine/cropSuitability.js`, with two
 * deliberate corrections:
 *
 * 1. The prototype's `scores[param] || 50` treated a sub-score of exactly 0 as
 *    50 (in JavaScript 0 is falsy), so a crop sitting far outside its absolute
 *    range was scored as if that parameter were neutral. Here a sub-score of 0
 *    stays 0, which is what the range test means.
 * 2. A parameter that was never measured is not invented as 50 either: its
 *    weight is dropped and the remaining weights are renormalized, and the
 *    parameter is reported in `missing_inputs` so the caller can say what the
 *    score does not know about.
 *
 * Everything else — the piecewise scoring curve, the texture groups, the
 * classification bands and colours — is the prototype's, so the numbers a
 * farmer sees match the design.
 */
class CropSuitabilityEngine
{
    /**
     * Sub-score bands, highest first. Keys match the API contract.
     *
     * @var list<array{key: string, label: string, min: int, color: string}>
     */
    private const CLASSES = [
        ['key' => 'very_suitable', 'label' => 'Very Suitable', 'min' => 85, 'color' => '#1B9E4B'],
        ['key' => 'suitable', 'label' => 'Suitable', 'min' => 70, 'color' => '#52B788'],
        ['key' => 'moderately_suitable', 'label' => 'Moderately Suitable', 'min' => 50, 'color' => '#F9C74F'],
        ['key' => 'not_suitable', 'label' => 'Not Suitable', 'min' => 0, 'color' => '#E76F51'],
    ];

    /**
     * Texture groupings. The prototype listed only the eight classes its
     * simulated soil could produce; the four remaining USDA classes are added
     * here so that real SoilGrids textures never fall through to the default.
     *
     * Precedence note: when a crop's preferred textures span more than one
     * group, the last group in this order that contains a preference decides
     * the comparison group — the prototype's behaviour, kept for score parity.
     *
     * @var array<string, list<string>>
     */
    private const TEXTURE_GROUPS = [
        'fine' => ['Clay', 'Silty Clay', 'Silty Clay Loam', 'Silt'],
        'medium' => ['Loam', 'Clay Loam', 'Silt Loam', 'Sandy Clay Loam', 'Sandy Clay'],
        'coarse' => ['Sandy Loam', 'Loamy Sand', 'Sand'],
    ];

    /**
     * Score every crop in the catalog against one site.
     *
     * @param  array<string, float|int|string|null>  $inputs
     *                                                        elevation (m), slope (deg), temperature (C), rainfall (mm/yr),
     *                                                        soil_ph, soil_texture (USDA class), soil_moisture (%), land_health (0-100)
     * @return array{crops: list<array<string, mixed>>, missing_inputs: list<string>, scored_count: int}
     */
    public function rank(array $inputs): array
    {
        $available = $this->availableInputs($inputs);
        $missing = array_values(array_diff($this->scoringParameters(), array_keys($available)));

        $crops = [];

        foreach (CropCatalog::all() as $crop) {
            $crops[] = $this->score($crop, $available, $missing);
        }

        usort($crops, fn (array $a, array $b): int => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);

        return [
            'crops' => $crops,
            'missing_inputs' => $missing,
            'scored_count' => count($crops),
        ];
    }

    /**
     * The parameters the engine scores, in the catalog's declared order.
     *
     * @return list<string>
     */
    private function scoringParameters(): array
    {
        return ['elevation', 'temperature', 'rainfall', 'soil_ph', 'soil_texture', 'slope', 'soil_moisture', 'land_health'];
    }

    /**
     * Only the inputs that were actually measured, keyed by scoring parameter.
     *
     * @param  array<string, float|int|string|null>  $inputs
     * @return array<string, float|int|string>
     */
    private function availableInputs(array $inputs): array
    {
        $available = [];

        foreach ($this->scoringParameters() as $parameter) {
            $value = $inputs[$parameter] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $available[$parameter] = $value;
        }

        return $available;
    }

    /**
     * Score one crop against the measured inputs.
     *
     * @param  array<string, mixed>  $crop
     * @param  array<string, float|int|string>  $available
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function score(array $crop, array $available, array $missing): array
    {
        $requirements = $crop['requirements'];
        $weights = $crop['weights'];

        $scores = [];
        foreach ($this->scoringParameters() as $parameter) {
            if (! array_key_exists($parameter, $available)) {
                continue;
            }

            $scores[$parameter] = match ($parameter) {
                'soil_texture' => $this->textureScore((string) $available[$parameter], $requirements['soil_texture']),
                // Land health is already a 0-100 assessment of the ground itself,
                // so it is taken as its own sub-score rather than scored against
                // a range (the prototype did the same).
                'land_health' => (int) round(min(100.0, max(0.0, (float) $available[$parameter]))),
                default => $this->parameterScore((float) $available[$parameter], $requirements[$parameter]),
            };
        }

        $total = 0.0;
        $weightSum = 0.0;

        foreach ($weights as $parameter => $weight) {
            if (! array_key_exists($parameter, $scores)) {
                continue;
            }

            $total += $scores[$parameter] * $weight;
            $weightSum += $weight;
        }

        $score = $weightSum > 0 ? (int) round(min(100.0, max(0.0, $total / $weightSum))) : 0;
        $classification = $this->classify($score);

        // What is holding this crop back: the parameters scoring below the
        // "acceptable" band, worst first.
        $limiting = array_keys(array_filter($scores, fn (int $value): bool => $value < 50));
        usort($limiting, fn (string $a, string $b): int => [$scores[$a], $a] <=> [$scores[$b], $b]);

        return [
            'id' => $crop['id'],
            'name' => $crop['name'],
            'icon' => $crop['icon'],
            'category' => $crop['category'],
            'color' => $crop['color'],
            'description' => $crop['description'],
            'score' => $score,
            'classification' => $classification['label'],
            'classification_key' => $classification['key'],
            'classification_color' => $classification['color'],
            'parameter_scores' => $scores,
            'limiting_parameters' => $limiting,
            'agroforestry_potential' => $crop['agroforestry_potential'],
            'market_value' => $crop['market_value'],
            'growing_period' => $crop['growing_period'],
            'planting_guide' => $crop['planting_guide'],
            'not_measured' => $missing,
        ];
    }

    /**
     * Sub-score for a numeric parameter, following the prototype's curve:
     * 90-100 inside the optimal range (peaking at its centre), 50-90 between the
     * optimal range and the absolute limit, and 0-40 outside the limit, decaying
     * with distance.
     *
     * @param  array{min: float|int, max: float|int, optimal: array{min: float|int, max: float|int}}  $requirement
     */
    private function parameterScore(float $value, array $requirement): int
    {
        $min = (float) $requirement['min'];
        $max = (float) $requirement['max'];
        $optimalMin = (float) $requirement['optimal']['min'];
        $optimalMax = (float) $requirement['optimal']['max'];

        if ($value < $min || $value > $max) {
            $distance = max($min - $value, $value - $max);
            $range = $max - $min;
            $penalty = $range > 0 ? min(1.0, $distance / ($range * 0.5)) : 1.0;

            return (int) round(max(0.0, 40 * (1 - $penalty)));
        }

        if ($value >= $optimalMin && $value <= $optimalMax) {
            $halfRange = ($optimalMax - $optimalMin) / 2;
            $fromCenter = abs($value - ($optimalMin + $optimalMax) / 2) / ($halfRange ?: 1.0);

            return (int) round(90 + (1 - $fromCenter) * 10);
        }

        if ($value < $optimalMin) {
            $span = $optimalMin - $min;
            $ratio = $span != 0.0 ? ($value - $min) / $span : 1.0;

            return (int) round(50 + $ratio * 40);
        }

        $span = $max - $optimalMax;
        $ratio = $span != 0.0 ? ($max - $value) / $span : 1.0;

        return (int) round(50 + $ratio * 40);
    }

    /**
     * Sub-score for soil texture: 90 when the class is one the crop lists, 70
     * when it merely belongs to the same group, 35 when it belongs to another.
     *
     * @param  list<string>  $preferred
     */
    private function textureScore(string $texture, array $preferred): int
    {
        if (in_array($texture, $preferred, true)) {
            return 90;
        }

        $cellGroup = $this->textureGroup($texture);
        $preferredGroup = 'medium';

        foreach (self::TEXTURE_GROUPS as $group => $textures) {
            if (array_intersect($preferred, $textures) !== []) {
                $preferredGroup = $group;
            }
        }

        return $cellGroup === $preferredGroup ? 70 : 35;
    }

    /**
     * The texture group a USDA class belongs to.
     */
    private function textureGroup(string $texture): string
    {
        foreach (self::TEXTURE_GROUPS as $group => $textures) {
            if (in_array($texture, $textures, true)) {
                return $group;
            }
        }

        return 'medium';
    }

    /**
     * The classification band for a total score.
     *
     * @return array{key: string, label: string, min: int, color: string}
     */
    private function classify(int $score): array
    {
        foreach (self::CLASSES as $class) {
            if ($score >= $class['min']) {
                return $class;
            }
        }

        return self::CLASSES[array_key_last(self::CLASSES)];
    }
}
