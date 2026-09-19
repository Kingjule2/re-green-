<?php

namespace App\Enums;

use App\Services\Land\LandIntelligenceEngine;

/**
 * The band a land health score falls in. The score itself is produced by
 * {@see LandIntelligenceEngine}; this enum is the display
 * vocabulary for it, shared by the API and the reports.
 */
enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Moderate = 'moderate';
    case Degraded = 'degraded';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Sehat',
            self::Moderate => 'Sedang',
            self::Degraded => 'Terdegradasi',
            self::Critical => 'Kritis',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Healthy => '#1B9E4B',
            self::Moderate => '#F9C74F',
            self::Degraded => '#F3722C',
            self::Critical => '#D7263D',
        };
    }

    public static function fromScore(int|float|null $score): ?self
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 80 => self::Healthy,
            $score >= 60 => self::Moderate,
            $score >= 40 => self::Degraded,
            default => self::Critical,
        };
    }

    /**
     * The status a raw engine string maps to, when it is one of ours.
     */
    public static function fromEngineStatus(?string $status): ?self
    {
        return $status === null ? null : self::tryFrom($status);
    }
}
