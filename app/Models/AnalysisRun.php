<?php

namespace App\Models;

use Database\Factories\AnalysisRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'land_id',
    'asset_id',
    'type',
    'status',
    'input_metadata',
    'result',
    'provenance',
    'model_name',
    'model_version',
    'error',
    'started_at',
    'completed_at',
])]
class AnalysisRun extends Model
{
    /** @use HasFactory<AnalysisRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'input_metadata' => 'array',
            'result' => 'array',
            'provenance' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Land, $this> */
    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return HasMany<AnalysisReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(AnalysisReview::class);
    }
}
