<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A human review of an analysis run: the record of who accepted, corrected or
 * rejected a model output, and why.
 */
#[Fillable([
    'analysis_run_id',
    'user_id',
    'decision',
    'reason',
    'corrections',
])]
class AnalysisReview extends Model
{
    protected function casts(): array
    {
        return [
            'corrections' => 'array',
        ];
    }

    /** @return BelongsTo<AnalysisRun, $this> */
    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
