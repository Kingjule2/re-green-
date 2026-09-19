<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'organization',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /** @return HasMany<Land, $this> */
    public function lands(): HasMany
    {
        return $this->hasMany(Land::class);
    }

    /** @return HasMany<LandAnalysis, $this> */
    public function landAnalyses(): HasMany
    {
        return $this->hasMany(LandAnalysis::class);
    }

    /** @return HasMany<AnalysisReview, $this> */
    public function analysisReviews(): HasMany
    {
        return $this->hasMany(AnalysisReview::class);
    }

    /** @return HasMany<ReportSnapshot, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class);
    }

    /**
     * Pemda/NGO and corporate accounts read the aggregate picture; a farmer
     * only ever sees their own land.
     */
    public function viewsAllLands(): bool
    {
        return $this->role->viewsAllLands();
    }

    public function isFarmer(): bool
    {
        return $this->role === UserRole::Farmer;
    }

    /**
     * Up to two initials for the account avatar.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials === '' ? 'RG' : $initials;
    }
}
