<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'land_id',
    'parcel_id',
    'observed_at',
    'observer',
    'source',
    'data_status',
    'burn_severity',
    'land_cover',
    'slope_deg',
    'soil_ph',
    'soil_texture',
    'soil_moisture_pct',
    'erosion_signs',
    'fire_date',
    'values',
    'notes',
])]
class FieldObservation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'observed_at' => 'date',
            'fire_date' => 'date',
            'slope_deg' => 'float',
            'soil_ph' => 'float',
            'soil_moisture_pct' => 'float',
            'erosion_signs' => 'boolean',
            'values' => 'array',
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
}
