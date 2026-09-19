<?php

namespace App\Enums;

/**
 * Where a land stands in the restoration timeline.
 *
 * The status follows the evidence rather than a manual switch: a land is
 * `planned` until its first photo is analysed, becomes `monitoring` while
 * analyses keep arriving, and turns `restored` once the latest analysis puts
 * the land health score in the healthy band.
 */
enum LandStatus: string
{
    case Planned = 'planned';
    case Monitoring = 'monitoring';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Terdaftar',
            self::Monitoring => 'Dipantau',
            self::Restored => 'Pulih',
        };
    }

    /**
     * Badge colour used by the map and the land list.
     */
    public function color(): string
    {
        return match ($this) {
            self::Planned => '#94a3b8',
            self::Monitoring => '#0ea5e9',
            self::Restored => '#1B9E4B',
        };
    }

    /**
     * The status implied by the latest measured land health score.
     */
    public static function fromHealthScore(?int $score): self
    {
        return match (true) {
            $score === null => self::Planned,
            $score >= 80 => self::Restored,
            default => self::Monitoring,
        };
    }
}
