<?php

namespace App\Services\Geospatial;

use InvalidArgumentException;

final class GeoJsonGeometry
{
    /**
     * Validate a GeoJSON polygon and return a conservative area estimate.
     *
     * Areas are only computed for EPSG:4326 using a spherical approximation.
     * The value is useful for project planning, not a cadastral survey.
     *
     * @param  array<string, mixed>|null  $geometry
     * @return array{geometry: array<string, mixed>|null, area_ha: float|null}
     */
    public function validate(?array $geometry, ?string $crs): array
    {
        if ($geometry === null) {
            return ['geometry' => null, 'area_ha' => null];
        }

        if (blank($crs)) {
            throw new InvalidArgumentException('A CRS is required when geometry is supplied.');
        }

        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! in_array($type, ['Polygon', 'MultiPolygon'], true) || ! is_array($coordinates)) {
            throw new InvalidArgumentException('Only GeoJSON Polygon or MultiPolygon geometry is supported.');
        }

        $polygons = $type === 'Polygon' ? [$coordinates] : $coordinates;
        $areaM2 = 0.0;

        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                throw new InvalidArgumentException('A polygon must contain at least one linear ring.');
            }

            $outerRing = $polygon[0] ?? null;
            $this->validateRing($outerRing);
            $areaM2 += abs($this->ringAreaM2($outerRing, $crs));

            foreach (array_slice($polygon, 1) as $hole) {
                $this->validateRing($hole);
                $areaM2 -= abs($this->ringAreaM2($hole, $crs));
            }
        }

        return [
            'geometry' => $geometry,
            'area_ha' => str_starts_with(strtoupper($crs), 'EPSG:4326')
                ? round(max(0.0, $areaM2) / 10000, 4)
                : null,
        ];
    }

    private function validateRing(mixed $ring): void
    {
        if (! is_array($ring) || count($ring) < 4) {
            throw new InvalidArgumentException('Each polygon ring needs at least four coordinates.');
        }

        $first = $ring[0] ?? null;
        $last = $ring[array_key_last($ring)] ?? null;

        if ($first !== $last) {
            throw new InvalidArgumentException('Each polygon ring must be closed.');
        }

        foreach ($ring as $coordinate) {
            if (! is_array($coordinate) || count($coordinate) < 2 || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])) {
                throw new InvalidArgumentException('Each coordinate must contain numeric longitude and latitude.');
            }

            $longitude = (float) $coordinate[0];
            $latitude = (float) $coordinate[1];

            if (! is_finite($longitude) || ! is_finite($latitude) || $longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                throw new InvalidArgumentException('Coordinates must be within longitude/latitude bounds.');
            }
        }
    }

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $ring
     */
    private function ringAreaM2(array $ring, string $crs): float
    {
        if (! str_starts_with(strtoupper($crs), 'EPSG:4326')) {
            return 0.0;
        }

        $earthRadius = 6371008.8;
        $referenceLatitude = deg2rad((float) $ring[0][1]);
        $area = 0.0;

        for ($index = 0, $last = count($ring) - 1; $index < $last; $index++) {
            $current = $ring[$index];
            $next = $ring[$index + 1];
            $currentX = deg2rad((float) $current[0]) * $earthRadius * cos($referenceLatitude);
            $currentY = deg2rad((float) $current[1]) * $earthRadius;
            $nextX = deg2rad((float) $next[0]) * $earthRadius * cos($referenceLatitude);
            $nextY = deg2rad((float) $next[1]) * $earthRadius;
            $area += ($currentX * $nextY) - ($nextX * $currentY);
        }

        return abs($area) / 2;
    }
}
