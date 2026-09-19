<?php

namespace App\Services\Ml;

use App\Services\Land\LandIntelligenceEngine;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the ReGreen ML land-analysis service.
 *
 * This is the swappable Computer Vision boundary described in the product spec:
 * the rest of the application depends on the normalized result shape returned
 * here, not on any specific model implementation. Swapping the underlying model
 * (YOLO, U-Net, DeepLab, SegFormer, ...) only changes the ML service.
 */
class LandAnalysisClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 60,
    ) {}

    /**
     * Send image bytes to the ML service and return a normalized perception
     * result consumable by {@see LandIntelligenceEngine}.
     *
     * Survey coordinates are forwarded so the ML service can sample a Digital
     * Elevation Model (BIG DEMNAS / NASA SRTM) around the point and return the
     * terrain context next to the land cover.
     *
     * @return array{land_cover: array<string, float>, confidence: array<string, float>, segmentation: mixed, terrain: array<string, mixed>, image: array{width: int|null, height: int|null}, model: array{name: string, version: string}}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function analyze(
        string $contents,
        string $filename,
        ?float $latitude = null,
        ?float $longitude = null,
    ): array {
        $request = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(2, 250, throw: false);

        if (! empty($this->apiKey)) {
            $request = $request->withHeaders(['X-API-Key' => $this->apiKey]);
        }

        $fields = array_filter(
            ['latitude' => $latitude, 'longitude' => $longitude],
            fn (?float $value): bool => $value !== null,
        );

        $response = $request
            ->attach('image', $contents, $filename)
            ->post('/api/v1/land-analysis', $fields);

        $response->throw();

        return $this->normalize((array) $response->json());
    }

    /**
     * Coerce the raw ML response into the stable internal contract, filling
     * defaults so downstream code never has to guard against missing keys.
     *
     * The environmental context blocks (`terrain`, `soil`, `climate`) are passed
     * through as the ML service reported them: each one carries its own
     * `available` / `reason` pair, and {@see LandIntelligenceEngine} owns their
     * normalization into the analysis result.
     *
     * @param  array<string, mixed>  $payload
     * @return array{land_cover: array<string, float>, confidence: array<string, float>, segmentation: mixed, fire_severity: array<string, mixed>|null, detections: list<array{label: string, confidence: float, box: list<float>}>, terrain: array<string, mixed>|null, soil: array<string, mixed>|null, climate: array<string, mixed>|null, image: array{width: int|null, height: int|null}, model: array{name: string, version: string, task: string|null, weights: string|null, device: string|null}}
     */
    private function normalize(array $payload): array
    {
        $cover = (array) ($payload['land_cover'] ?? []);
        $confidence = (array) ($payload['confidence'] ?? []);
        $image = (array) ($payload['image'] ?? []);
        $model = (array) ($payload['model'] ?? []);

        return [
            'land_cover' => [
                'dense_vegetation' => (float) ($cover['dense_vegetation'] ?? 0),
                'sparse_vegetation' => (float) ($cover['sparse_vegetation'] ?? 0),
                'bare_soil' => (float) ($cover['bare_soil'] ?? 0),
                'charred_soil' => (float) ($cover['charred_soil'] ?? 0),
                'water' => (float) ($cover['water'] ?? 0),
                'built_area' => (float) ($cover['built_area'] ?? 0),
                'other' => (float) ($cover['other'] ?? 0),
            ],
            'confidence' => [
                'overall' => (float) ($confidence['overall'] ?? 0.85),
                'vegetation' => (float) ($confidence['vegetation'] ?? $confidence['overall'] ?? 0.85),
                'bare_soil' => (float) ($confidence['bare_soil'] ?? $confidence['overall'] ?? 0.8),
                'water' => (float) ($confidence['water'] ?? $confidence['overall'] ?? 0.8),
            ],
            'segmentation' => $payload['segmentation'] ?? null,
            'fire_severity' => $this->fireSeverity($payload['fire_severity'] ?? null),
            'detections' => $this->detections($payload['detections'] ?? null),
            'terrain' => $this->context($payload['terrain'] ?? null),
            'soil' => $this->context($payload['soil'] ?? null),
            'climate' => $this->context($payload['climate'] ?? null),
            'image' => [
                'width' => isset($image['width']) ? (int) $image['width'] : null,
                'height' => isset($image['height']) ? (int) $image['height'] : null,
            ],
            'model' => [
                'name' => (string) ($model['name'] ?? 'unknown'),
                'version' => (string) ($model['version'] ?? '0.0.0'),
                'task' => isset($model['task']) ? (string) $model['task'] : null,
                'weights' => isset($model['weights']) ? (string) $model['weights'] : null,
                'device' => isset($model['device']) ? (string) $model['device'] : null,
            ],
        ];
    }

    /**
     * The fire-severity block as the ML service reported it, or null when it
     * sent none — {@see LandIntelligenceEngine} then derives the level from the
     * cover mix with the same weights, so severity degrades instead of vanishing.
     *
     * @return array<string, mixed>|null
     */
    private function fireSeverity(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        return [
            'available' => (bool) ($value['available'] ?? true),
            'reason' => isset($value['reason']) ? (string) $value['reason'] : null,
            'level' => isset($value['level']) ? (string) $value['level'] : null,
            'label' => isset($value['label']) ? (string) $value['label'] : null,
            'score' => isset($value['score']) ? (float) $value['score'] : null,
            'confidence' => isset($value['confidence']) ? (float) $value['confidence'] : null,
            'method' => isset($value['method']) ? (string) $value['method'] : null,
            'evidence' => is_array($value['evidence'] ?? null) ? $value['evidence'] : null,
        ];
    }

    /**
     * Model detections, normalized to normalized xywh boxes the UI can overlay.
     *
     * @return list<array{label: string, confidence: float, box: list<float>}>
     */
    private function detections(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $detections = [];

        foreach ($value as $detection) {
            if (! is_array($detection) || ! isset($detection['label'])) {
                continue;
            }

            $box = $detection['box'] ?? null;

            if (! is_array($box) || count($box) !== 4) {
                continue;
            }

            $detections[] = [
                'label' => (string) $detection['label'],
                'confidence' => (float) ($detection['confidence'] ?? 0),
                'box' => array_map(fn (mixed $edge): float => (float) $edge, array_values($box)),
            ];
        }

        return $detections;
    }

    /**
     * A context block as the ML service reported it, or null when it sent none.
     *
     * @return array<string, mixed>|null
     */
    private function context(mixed $context): ?array
    {
        return is_array($context) ? $context : null;
    }
}
