<?php

namespace App\Services\Geospatial;

final class VerticalDatumNormalizer
{
    /**
     * Normalize drone and ground vertical observations to metres.
     *
     * No missing field is replaced with zero. The result explicitly tells the
     * caller whether the values are valid, incomplete, or rejected.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $missing = [];
        $problems = [];
        $metadata = [
            'vertical_reference' => $this->stringValue($input['vertical_reference'] ?? null),
            'geoid_model' => $this->stringValue($input['geoid_model'] ?? null),
            'epoch' => $this->stringValue($input['epoch'] ?? null),
            'source' => $this->stringValue($input['source'] ?? null),
            'distance_reference' => $this->stringValue($input['distance_reference'] ?? null),
            'surface' => $this->stringValue($input['surface'] ?? null),
        ];

        $hasMeasurement = $this->hasAny($input, ['h', 'N', 'H', 'd_vertikal_ke_tanah']);
        if ($hasMeasurement) {
            foreach (['vertical_reference', 'geoid_model'] as $key) {
                if ($metadata[$key] === null) {
                    $missing[] = $key;
                }
            }
        }

        $h = $this->measurement($input, 'h', $missing, $problems);
        $n = $this->measurement($input, 'N', $missing, $problems);
        $providedH = $this->measurement($input, 'H', $missing, $problems);
        $deltaTakeoff = $this->measurement($input, 'delta_h_takeoff', $missing, $problems);
        $distanceToGround = $this->measurement($input, 'd_vertikal_ke_tanah', $missing, $problems);

        $distanceProvided = array_key_exists('d_vertikal_ke_tanah', $input)
            && $input['d_vertikal_ke_tanah'] !== null
            && $input['d_vertikal_ke_tanah'] !== '';

        if ($distanceProvided) {
            if ($metadata['surface'] !== 'ground') {
                $problems[] = 'surface_ground_reference';
            }

            // An unstated distance reference is missing metadata, not a
            // contradiction: only a reference that is present and says the
            // distance is not vertical makes the measurement unusable.
            if ($metadata['distance_reference'] !== null && $metadata['distance_reference'] !== 'takeoff_to_ground_vertical') {
                $problems[] = 'distance_must_be_vertical';
            }
        }

        if ($metadata['vertical_reference'] !== null && ! in_array($metadata['vertical_reference'], ['ellipsoidal', 'orthometric'], true)) {
            $problems[] = 'unsupported_vertical_reference';
        }

        if ($h !== null || $n !== null) {
            if ($h === null) {
                $missing[] = 'h';
            }
            if ($n === null) {
                $missing[] = 'N';
            }

            if ($metadata['vertical_reference'] !== null && $metadata['vertical_reference'] !== 'ellipsoidal') {
                $problems[] = 'h_requires_ellipsoidal_reference';
            }
        }

        if ($hasMeasurement && $distanceToGround === null) {
            $missing[] = 'd_vertikal_ke_tanah';
        }

        $orthometric = null;
        $orthometricFormula = null;
        if ($h !== null && $n !== null && $metadata['vertical_reference'] === 'ellipsoidal') {
            $orthometric = $h - $n;
            $orthometricFormula = 'h - N';
        } elseif ($providedH !== null && $metadata['vertical_reference'] === 'orthometric') {
            $orthometric = $providedH;
            $orthometricFormula = 'H supplied';
        }

        if ($providedH !== null && $orthometric !== null && abs($providedH - $orthometric) > 0.05) {
            $problems[] = 'provided_H_does_not_match_h_minus_N';
        }

        $ground = null;
        if ($orthometric !== null && $distanceToGround !== null && $problems === []) {
            $ground = $orthometric - $distanceToGround;
        }

        $uncertainty = $this->uncertainty($input, $missing, $problems);
        $status = $this->status($hasMeasurement, $missing, $problems, $orthometric, $ground);

        return [
            'status' => $status,
            'normalized' => [
                'h_ellipsoidal_m' => $h,
                'N_geoid_m' => $n,
                'H_orthometric_m' => $orthometric === null ? null : round($orthometric, 4),
                'delta_h_takeoff_m' => $deltaTakeoff,
                'd_vertikal_ke_tanah_m' => $distanceToGround,
                'H_ground_m' => $ground === null ? null : round($ground, 4),
            ],
            'uncertainty_m' => $uncertainty,
            'metadata' => $metadata,
            'missing' => array_values(array_unique($missing)),
            'problems' => array_values(array_unique($problems)),
            'calculation_trace' => [
                'H_orthometric' => $orthometric === null
                    ? null
                    : ['formula' => $orthometricFormula, 'value_m' => round($orthometric, 4)],
                'H_ground' => $ground === null
                    ? null
                    : ['formula' => 'H - d_vertikal_ke_tanah', 'value_m' => round($ground, 4)],
            ],
        ];
    }

    /** @param array<string, mixed> $input */
    private function hasAny(array $input, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $missing
     * @param  list<string>  $problems
     */
    private function measurement(array $input, string $key, array &$missing, array &$problems): ?float
    {
        if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return null;
        }

        if (! is_numeric($input[$key]) || ! is_finite((float) $input[$key])) {
            $problems[] = $key.'_must_be_numeric';

            return null;
        }

        $unit = $input[$key.'_unit']
            ?? ($key === 'd_vertikal_ke_tanah' ? ($input['d_unit'] ?? null) : null)
            ?? ($key === 'delta_h_takeoff' ? ($input['delta_h_takeoff_unit'] ?? null) : null)
            ?? $input['unit']
            ?? null;
        $unit = is_string($unit) ? strtolower(trim($unit)) : null;
        if ($unit === null || ! in_array($unit, ['m', 'meter', 'meters', 'metre', 'metres', 'ft', 'foot', 'feet'], true)) {
            $missing[] = $key.'_unit';

            return null;
        }

        $value = (float) $input[$key];

        return in_array($unit, ['ft', 'foot', 'feet'], true) ? $value * 0.3048 : $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $missing
     * @param  list<string>  $problems
     * @return array{H_orthometric: float|null, H_ground: float|null}
     */
    private function uncertainty(array $input, array &$missing, array &$problems): array
    {
        $values = [];
        foreach (['h', 'N', 'd'] as $key) {
            $inputKey = 'uncertainty_'.$key;
            if (! array_key_exists($inputKey, $input) || $input[$inputKey] === null || $input[$inputKey] === '') {
                continue;
            }

            if (! is_numeric($input[$inputKey]) || (float) $input[$inputKey] < 0) {
                $problems[] = $inputKey.'_must_be_non_negative';

                continue;
            }

            $unit = $input[$inputKey.'_unit'] ?? $input['uncertainty_unit'] ?? null;
            if (! is_string($unit)) {
                $missing[] = $inputKey.'_unit';

                continue;
            }

            $unit = strtolower(trim($unit));
            if (! in_array($unit, ['m', 'meter', 'meters', 'metre', 'metres', 'ft', 'foot', 'feet'], true)) {
                $problems[] = $inputKey.'_unit_invalid';

                continue;
            }

            $value = (float) $input[$inputKey];
            $values[$key] = in_array($unit, ['ft', 'foot', 'feet'], true) ? $value * 0.3048 : $value;
        }

        if ($values === []) {
            return ['H_orthometric' => null, 'H_ground' => null];
        }

        $orthometric = null;
        if (isset($values['h']) || isset($values['N'])) {
            $orthometric = sqrt(($values['h'] ?? 0) ** 2 + ($values['N'] ?? 0) ** 2);
        }

        $ground = $orthometric === null && ! isset($values['d'])
            ? null
            : sqrt(($orthometric ?? 0) ** 2 + ($values['d'] ?? 0) ** 2);

        return ['H_orthometric' => $orthometric, 'H_ground' => $ground];
    }

    /** @param list<string> $missing @param list<string> $problems */
    private function status(bool $hasMeasurement, array $missing, array $problems, ?float $orthometric, ?float $ground): string
    {
        if ($problems !== [] && in_array('distance_must_be_vertical', $problems, true)) {
            return 'rejected';
        }

        if ($problems !== [] && (in_array('unsupported_vertical_reference', $problems, true) || in_array('provided_H_does_not_match_h_minus_N', $problems, true))) {
            return 'rejected';
        }

        if (! $hasMeasurement || $missing !== [] || $problems !== [] || $orthometric === null || $ground === null) {
            return 'incomplete';
        }

        return 'valid';
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
