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
     * @return array{land_cover: array<string, float>, confidence: array<string, float>, segmentation: mixed, image: array{width: int|null, height: int|null}, model: array{name: string, version: string}}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function analyze(string $contents, string $filename): array
    {
        $request = Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(2, 250, throw: false);

        if (! empty($this->apiKey)) {
            $request = $request->withHeaders(['X-API-Key' => $this->apiKey]);
        }

        $response = $request
            ->attach('image', $contents, $filename)
            ->post('/api/v1/land-analysis');

        $response->throw();

        return $this->normalize((array) $response->json());
    }

    /**
     * Coerce the raw ML response into the stable internal contract, filling
     * defaults so downstream code never has to guard against missing keys.
     *
     * @param  array<string, mixed>  $payload
     * @return array{land_cover: array<string, float>, confidence: array<string, float>, segmentation: mixed, image: array{width: int|null, height: int|null}, model: array{name: string, version: string}}
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
            'image' => [
                'width' => isset($image['width']) ? (int) $image['width'] : null,
                'height' => isset($image['height']) ? (int) $image['height'] : null,
            ],
            'model' => [
                'name' => (string) ($model['name'] ?? 'unknown'),
                'version' => (string) ($model['version'] ?? '0.0.0'),
            ],
        ];
    }
}
