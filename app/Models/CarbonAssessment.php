<?php

namespace App\Models;

use App\Enums\CarbonEligibility;
use App\Enums\CarbonSubmissionStatus;
use Database\Factories\CarbonAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Carbon-credit pre-feasibility for one land.
 *
 * One row per land, refreshed every time an analysis changes the estimate. It
 * holds three things: the numbers behind the estimate (so a report can explain
 * them), the eligibility checklist, and the application the farmer forwarded
 * to a certification partner. The platform never claims a credit was issued —
 * certification happens at the partner.
 */
#[Fillable([
    'land_id',
    'user_id',
    'calculated_at',
    'area_ha',
    'vegetation_cover_pct',
    'vegetation_class',
    'basis',
    'sequestration_tco2e_per_year',
    'sequestration_5yr_tco2e',
    'eligibility_status',
    'eligibility_checklist',
    'method',
    'submission_status',
    'partner',
    'contact_name',
    'contact_email',
    'offered_area_ha',
    'notes',
    'submitted_at',
])]
class CarbonAssessment extends Model
{
    /** @use HasFactory<CarbonAssessmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'calculated_at' => 'datetime',
            'area_ha' => 'float',
            'vegetation_cover_pct' => 'float',
            'sequestration_tco2e_per_year' => 'float',
            'sequestration_5yr_tco2e' => 'float',
            'offered_area_ha' => 'float',
            'submitted_at' => 'datetime',
            'eligibility_status' => CarbonEligibility::class,
            'submission_status' => CarbonSubmissionStatus::class,
            'eligibility_checklist' => 'array',
            'method' => 'array',
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

    public function isSubmitted(): bool
    {
        return $this->submission_status !== CarbonSubmissionStatus::NotSubmitted;
    }
}
