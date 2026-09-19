<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'land_id',
    'name',
    'data_status',
    'crs',
    'geometry',
    'area_ha',
    'notes',
])]
class Parcel extends Model
{
    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'area_ha' => 'float',
        ];
    }

    /** @return BelongsTo<Land, $this> */
    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
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
}
