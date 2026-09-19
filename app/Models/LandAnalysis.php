<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\BurnSeverity;
use App\Services\Land\LandIntelligenceEngine;
use App\Services\Restoration\RestorationRecommendationEngine;
use Database\Factories\LandAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded land photo and its AI-derived land intelligence.
 *
 * Analyses of the same land form the monitoring time series: `captured_at`
 * orders them and the headline metrics are compared between neighbouring
 * analyses to show whether vegetation is coming back.
 *
 * @property-read string|null $image_url
 */
#[Fillable([
    'user_id',
    'land_id',
    'image_path',
    'image_filename',
    'image_width',
    'image_height',
    'file_size',
    'latitude',
    'longitude',
    'captured_at',
    'capture_source',
    'notes',
    'vertical_status',
    'vertical_metadata',
    'analysis_status',
    'analysis_started_at',
    'analysis_completed_at',
    'analysis_error',
    'land_health_score',
    'burn_severity',
    'burn_severity_score',
    'burn_severity_confidence',
    'vegetation_percentage',
    'bare_soil_percentage',
    'charred_percentage',
    'water_percentage',
    'degraded_percentage',
    'restoration_potential',
    'ai_model',
    'ai_model_version',
    'analysis_result',
    'recommendation_result',
])]
class LandAnalysis extends Model
{
    /** @use HasFactory<LandAnalysisFactory> */
    use HasFactory;

    public const IMAGE_DISK = 'public';

    protected function casts(): array
    {
        return [
            'analysis_status' => AnalysisStatus::class,
            'burn_severity' => BurnSeverity::class,
            'image_width' => 'integer',
            'image_height' => 'integer',
            'file_size' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'captured_at' => 'datetime',
            'vertical_metadata' => 'array',
            'analysis_started_at' => 'datetime',
            'analysis_completed_at' => 'datetime',
            'land_health_score' => 'integer',
            'burn_severity_score' => 'integer',
            'burn_severity_confidence' => 'float',
            'vegetation_percentage' => 'float',
            'bare_soil_percentage' => 'float',
            'charred_percentage' => 'float',
            'water_percentage' => 'float',
            'degraded_percentage' => 'float',
            'analysis_result' => 'array',
            'recommendation_result' => 'array',
        ];
    }

    /**
     * Store a derived intelligence result on this analysis: the full payloads
     * plus the denormalized headline columns every list view reads.
     *
     * This is the single mapping from "engine output" to "database row", so the
     * pipeline job and the demo seeder can never drift apart on column meaning.
     *
     * @param  array<string, mixed>  $result  output of {@see LandIntelligenceEngine::derive()}
     * @param  array<string, mixed>  $recommendation  output of {@see RestorationRecommendationEngine::recommend()}
     */
    public function recordResult(array $result, array $recommendation): void
    {
        $this->forceFill([
            'analysis_status' => AnalysisStatus::Completed,
            'analysis_completed_at' => now(),
            'analysis_error' => null,
            'land_health_score' => $result['land_health']['score'],
            'burn_severity' => $result['burn_severity']['level'],
            'burn_severity_score' => $result['burn_severity']['score'],
            'burn_severity_confidence' => $result['burn_severity']['confidence'],
            'vegetation_percentage' => $result['metrics']['vegetation_coverage'],
            'bare_soil_percentage' => $result['metrics']['bare_soil'],
            'charred_percentage' => $result['metrics']['charred_soil'],
            'water_percentage' => $result['metrics']['water_presence'],
            'degraded_percentage' => $result['metrics']['degraded_area'],
            'restoration_potential' => $result['metrics']['restoration_potential'],
            'ai_model' => $result['model']['name'],
            'ai_model_version' => $result['model']['version'],
            'analysis_result' => $result,
            'recommendation_result' => $recommendation,
        ])->save();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Land, $this> */
    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    /**
     * Whether the photo carried coordinates the ML service could sample.
     */
    public function hasReliableGeolocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The date the monitoring period is keyed on: when the photo was taken, or
     * when it was uploaded if the farmer did not say.
     */
    public function monitoringDate(): ?Carbon
    {
        return $this->captured_at ?? $this->created_at;
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (blank($this->image_path)) {
                return null;
            }

            return Storage::disk(self::IMAGE_DISK)->url($this->image_path);
        });
    }
}
