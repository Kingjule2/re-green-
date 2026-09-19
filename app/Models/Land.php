<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\LandStatus;
use Database\Factories\LandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A monitored restoration site ("lahan"): one burnt area with one owner, one
 * location, and one monitoring history.
 *
 * The land carries the context the rule-based recommendation needs next to the
 * YOLO result — where it is (the ML service samples terrain, soil and climate
 * for that point) and, when no coordinates or datasets are available, the
 * texture and rainfall the user declared.
 */
#[Fillable([
    'user_id',
    'name',
    'objective',
    'status',
    'data_status',
    'location_name',
    'latitude',
    'longitude',
    'soil_texture',
    'rainfall_mm',
    'crs',
    'geometry',
    'area_ha',
    'fire_event_date',
    'selected_crop_id',
    'selected_crop_at',
    'notes',
])]
class Land extends Model
{
    /** @use HasFactory<LandFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'area_ha' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'rainfall_mm' => 'integer',
            'fire_event_date' => 'date',
            'selected_crop_at' => 'datetime',
            'status' => LandStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<LandAnalysis, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(LandAnalysis::class);
    }

    /**
     * The newest completed analysis. Pending or failed uploads are operational
     * events, not current land evidence, so dashboards keep showing the last
     * reliable result while the farmer's timeline still exposes their status.
     *
     * @return HasOne<LandAnalysis, $this>
     */
    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(LandAnalysis::class)->ofMany(
            ['captured_at' => 'max', 'id' => 'max'],
            function (Builder $query): void {
                $query->where('analysis_status', AnalysisStatus::Completed->value);
            },
        );
    }

    /** @return HasMany<Parcel, $this> */
    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class);
    }

    /** @return HasMany<FieldObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(FieldObservation::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasMany<AnalysisRun, $this> */
    public function analysisRuns(): HasMany
    {
        return $this->hasMany(AnalysisRun::class);
    }

    /** @return HasMany<ReportSnapshot, $this> */
    public function reportSnapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class);
    }

    /** @return HasOne<CarbonAssessment, $this> */
    public function carbonAssessment(): HasOne
    {
        return $this->hasOne(CarbonAssessment::class);
    }

    /**
     * Whether the land can be plotted on the map and sampled for terrain,
     * soil and climate.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isDemo(): bool
    {
        return $this->data_status === 'demo';
    }
}
