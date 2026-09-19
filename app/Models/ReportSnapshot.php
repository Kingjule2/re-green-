<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen, versioned report.
 *
 * A snapshot is generated once and never recomputed, so a report a regulator
 * already received keeps the numbers it was issued with. `scope` says whether
 * it covers one land or every land the account could see at that moment.
 */
#[Fillable([
    'land_id',
    'user_id',
    'scope',
    'version',
    'title',
    'format',
    'snapshot',
])]
class ReportSnapshot extends Model
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_LAND = 'land';

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Land, $this> */
    public function land(): BelongsTo
    {
        return $this->belongsTo(Land::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGlobal(): bool
    {
        return $this->scope === self::SCOPE_ALL;
    }
}
