<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'land_id',
    'parcel_id',
    'type',
    'path',
    'original_filename',
    'mime_type',
    'file_size',
    'captured_at',
    'latitude',
    'longitude',
    'data_status',
    'status',
    'provenance',
    'vertical_metadata',
])]
class Asset extends Model
{
    public const DISK = 'public';

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'captured_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'provenance' => 'array',
            'vertical_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Land, $this> */
    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    /** @return BelongsTo<Parcel, $this> */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    /** @return HasMany<AnalysisRun, $this> */
    public function analysisRuns(): HasMany
    {
        return $this->hasMany(AnalysisRun::class);
    }

    public function url(): ?string
    {
        return filled($this->path) ? Storage::disk(self::DISK)->url($this->path) : null;
    }
}
