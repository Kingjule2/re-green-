<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Database\Factories\DroneAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A single drone/aerial image analysis and its AI-derived land intelligence.
 *
 * @property-read string|null $image_url
 */
#[Fillable([
    'user_id',
    'project_id',
    'image_path',
    'image_filename',
    'image_width',
    'image_height',
    'file_size',
    'latitude',
    'longitude',
    'area_name',
    'survey_date',
    'drone_model',
    'flight_altitude',
    'image_type',
    'analysis_status',
    'analysis_started_at',
    'analysis_completed_at',
    'analysis_error',
    'land_health_score',
    'vegetation_percentage',
    'bare_soil_percentage',
    'water_percentage',
    'degraded_percentage',
    'restoration_potential',
    'ai_model',
    'ai_model_version',
    'analysis_result',
    'recommendation_result',
])]
class DroneAnalysis extends Model
{
    /** @use HasFactory<DroneAnalysisFactory> */
    use HasFactory;

    /**
     * The disk used to store uploaded drone imagery.
     */
    public const IMAGE_DISK = 'public';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'analysis_status' => AnalysisStatus::class,
            'image_width' => 'integer',
            'image_height' => 'integer',
            'file_size' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'survey_date' => 'date',
            'analysis_started_at' => 'datetime',
            'analysis_completed_at' => 'datetime',
            'land_health_score' => 'integer',
            'vegetation_percentage' => 'float',
            'bare_soil_percentage' => 'float',
            'water_percentage' => 'float',
            'degraded_percentage' => 'float',
            'analysis_result' => 'array',
            'recommendation_result' => 'array',
        ];
    }

    /**
     * The user who uploaded the analysis, when authenticated.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether reliable geospatial metadata is present. Without it the system
     * must not present results as accurate geographic/GIS measurements.
     */
    public function hasReliableGeolocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Publicly resolvable URL for the stored image, if any.
     *
     * @return Attribute<string|null, never>
     */
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
