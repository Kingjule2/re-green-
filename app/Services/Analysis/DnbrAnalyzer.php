<?php

namespace App\Services\Analysis;

final class DnbrAnalyzer
{
    /**
     * Configurable dNBR classes. These are a baseline for review, not a local
     * burn-severity truth model.
     *
     * @var list<array{key: string, min: float, max: float|null, label: string}>
     */
    private const CLASSES = [
        ['key' => 'enhanced_regrowth', 'min' => -INF, 'max' => -0.1, 'label' => 'Enhanced regrowth'],
        ['key' => 'unburned_or_low_change', 'min' => -0.1, 'max' => 0.1, 'label' => 'Unburned / low change'],
        ['key' => 'low_severity', 'min' => 0.1, 'max' => 0.27, 'label' => 'Low severity'],
        ['key' => 'moderate_low_severity', 'min' => 0.27, 'max' => 0.44, 'label' => 'Moderate-low severity'],
        ['key' => 'moderate_high_severity', 'min' => 0.44, 'max' => 0.66, 'label' => 'Moderate-high severity'],
        ['key' => 'high_severity', 'min' => 0.66, 'max' => null, 'label' => 'High severity'],
    ];

    /**
     * Calculate dNBR from aligned before/after NIR and SWIR matrices.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function analyze(array $input): array
    {
        $problems = [];
        $before = is_array($input['before'] ?? null) ? $input['before'] : null;
        $after = is_array($input['after'] ?? null) ? $input['after'] : null;

        if ($before === null) {
            $problems[] = 'before_image_required';
        }
        if ($after === null) {
            $problems[] = 'after_image_required';
        }

        $beforeNir = $this->matrix($before['nir'] ?? null, 'before_nir', $problems);
        $beforeSwir = $this->matrix($before['swir'] ?? null, 'before_swir', $problems);
        $afterNir = $this->matrix($after['nir'] ?? null, 'after_nir', $problems);
        $afterSwir = $this->matrix($after['swir'] ?? null, 'after_swir', $problems);

        $beforeGrid = $this->grid($beforeNir);
        foreach ([$beforeSwir, $afterNir, $afterSwir] as $grid) {
            if ($beforeGrid !== null && $this->grid($grid) !== $beforeGrid) {
                $problems[] = 'before_after_grid_mismatch';
            }
        }

        $scaleBefore = $this->scale($before['reflectance_scale'] ?? null, 'before_reflectance_scale', $problems);
        $scaleAfter = $this->scale($after['reflectance_scale'] ?? null, 'after_reflectance_scale', $problems);
        $mask = $this->mask($input['clear_mask'] ?? null, $beforeGrid, $problems);

        $dnbr = [];
        $classCounts = [];
        $validCount = 0;
        $nodataCount = 0;
        $values = [];

        if ($beforeGrid !== null && $this->grid($beforeSwir) === $beforeGrid && $this->grid($afterNir) === $beforeGrid && $this->grid($afterSwir) === $beforeGrid && $scaleBefore !== null && $scaleAfter !== null && $problems === []) {
            for ($row = 0; $row < $beforeGrid['rows']; $row++) {
                $dnbr[$row] = [];
                for ($column = 0; $column < $beforeGrid['cols']; $column++) {
                    if (($mask[$row][$column] ?? true) !== true) {
                        $dnbr[$row][$column] = null;
                        $nodataCount++;

                        continue;
                    }

                    $beforeValue = $this->nbr((float) $beforeNir[$row][$column] / $scaleBefore, (float) $beforeSwir[$row][$column] / $scaleBefore);
                    $afterValue = $this->nbr((float) $afterNir[$row][$column] / $scaleAfter, (float) $afterSwir[$row][$column] / $scaleAfter);

                    if ($beforeValue === null || $afterValue === null) {
                        $dnbr[$row][$column] = null;
                        $nodataCount++;
                        $problems[] = 'zero_nbr_denominator';

                        continue;
                    }

                    $value = round($beforeValue - $afterValue, 6);
                    $dnbr[$row][$column] = $value;
                    $values[] = $value;
                    $validCount++;
                    $class = $this->classify($value);
                    $classCounts[$class['key']] = ($classCounts[$class['key']] ?? 0) + 1;
                }
            }
        }

        $pixelSize = $this->positiveNumber($input['pixel_size_m'] ?? null);
        $crs = is_string($input['crs'] ?? null) && trim($input['crs']) !== '' ? trim($input['crs']) : null;
        $areaMissing = [];
        if ($pixelSize === null) {
            $areaMissing[] = 'pixel_size_m';
        }
        if ($crs === null) {
            $areaMissing[] = 'crs';
        }

        $status = $problems !== []
            ? ($this->onlyWarnings($problems) && $validCount > 0 ? 'valid' : 'rejected')
            : ($validCount > 0 ? 'valid' : 'incomplete');

        $classDistribution = [];
        foreach (self::CLASSES as $class) {
            $count = $classCounts[$class['key']] ?? 0;
            $classDistribution[] = [
                'key' => $class['key'],
                'label' => $class['label'],
                'pixel_count' => $count,
                'percentage' => $validCount > 0 ? round($count / $validCount * 100, 2) : null,
            ];
        }

        return [
            'status' => $status,
            'problems' => array_values(array_unique($problems)),
            'area_missing' => $areaMissing,
            'dnbr' => $dnbr,
            'valid_pixel_count' => $validCount,
            'nodata_pixel_count' => $nodataCount,
            'statistics' => [
                'min_dnbr' => $values === [] ? null : min($values),
                'max_dnbr' => $values === [] ? null : max($values),
                'mean_dnbr' => $values === [] ? null : round(array_sum($values) / count($values), 6),
            ],
            'classes' => $classDistribution,
            'area_ha' => [
                'total_valid' => $pixelSize !== null && $crs !== null && $validCount > 0
                    ? round($validCount * $pixelSize * $pixelSize / 10000, 4)
                    : null,
                'by_class' => $this->classAreas($classCounts, $pixelSize, $crs),
            ],
            'provenance' => [
                'data_status' => $input['data_status'] ?? 'unknown',
                'source' => $input['source'] ?? null,
                'before_acquired_at' => $before['acquired_at'] ?? null,
                'after_acquired_at' => $after['acquired_at'] ?? null,
                'threshold_status' => 'baseline_for_review',
                'reflectance_scale_before' => $scaleBefore,
                'reflectance_scale_after' => $scaleAfter,
                'crs' => $crs,
                'pixel_size_m' => $pixelSize,
            ],
        ];
    }

    /** @param mixed $matrix @param list<string> $problems @return list<list<float>>|null */
    private function matrix(mixed $matrix, string $name, array &$problems): ?array
    {
        if (! is_array($matrix) || $matrix === []) {
            $problems[] = $name.'_required';

            return null;
        }

        $cols = null;
        $normalised = [];
        foreach ($matrix as $row) {
            if (! is_array($row) || $row === []) {
                $problems[] = $name.'_must_be_rectangular';

                return null;
            }
            $cols ??= count($row);
            if (count($row) !== $cols) {
                $problems[] = $name.'_must_be_rectangular';

                return null;
            }
            $normalisedRow = [];
            foreach ($row as $value) {
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    $problems[] = $name.'_must_contain_finite_numbers';

                    return null;
                }
                $normalisedRow[] = (float) $value;
            }
            $normalised[] = $normalisedRow;
        }

        return $normalised;
    }

    /** @param list<list<float>>|null $matrix @return array{rows:int,cols:int}|null */
    private function grid(?array $matrix): ?array
    {
        return $matrix === null ? null : ['rows' => count($matrix), 'cols' => count($matrix[0])];
    }

    /** @param list<string> $problems */
    private function scale(mixed $value, string $name, array &$problems): ?float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            $problems[] = $name.'_must_be_positive';

            return null;
        }

        return (float) $value;
    }

    /** @param array{rows:int,cols:int}|null $grid @param list<string> $problems @return list<list<bool>> */
    private function mask(mixed $mask, ?array $grid, array &$problems): array
    {
        if ($grid === null) {
            return [];
        }
        if ($mask === null) {
            return array_fill(0, $grid['rows'], array_fill(0, $grid['cols'], true));
        }
        if (! is_array($mask) || count($mask) !== $grid['rows']) {
            $problems[] = 'clear_mask_grid_mismatch';

            return array_fill(0, $grid['rows'], array_fill(0, $grid['cols'], false));
        }

        $normalised = [];
        foreach ($mask as $row) {
            if (! is_array($row) || count($row) !== $grid['cols']) {
                $problems[] = 'clear_mask_grid_mismatch';

                return array_fill(0, $grid['rows'], array_fill(0, $grid['cols'], false));
            }
            $normalised[] = array_map(static fn (mixed $value): bool => $value === true || $value === 1 || $value === '1', $row);
        }

        return $normalised;
    }

    private function nbr(float $nir, float $swir): ?float
    {
        $denominator = $nir + $swir;

        return abs($denominator) < 1.0e-12 ? null : ($nir - $swir) / $denominator;
    }

    /** @return array{key:string,min:float,max:float|null,label:string} */
    private function classify(float $value): array
    {
        foreach (self::CLASSES as $class) {
            if ($value >= $class['min'] && ($class['max'] === null || $value < $class['max'])) {
                return $class;
            }
        }

        return self::CLASSES[array_key_last(self::CLASSES)];
    }

    /** @param array<string,int> $counts @return array<string,float|null> */
    private function classAreas(array $counts, ?float $pixelSize, ?string $crs): array
    {
        $areas = [];
        foreach ($counts as $key => $count) {
            $areas[$key] = $pixelSize !== null && $crs !== null
                ? round($count * $pixelSize * $pixelSize / 10000, 4)
                : null;
        }

        return $areas;
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /** @param list<string> $problems */
    private function onlyWarnings(array $problems): bool
    {
        return count(array_diff($problems, ['zero_nbr_denominator'])) === 0;
    }
}
