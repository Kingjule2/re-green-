<?php

namespace App\Enums;

/**
 * Burn severity of a land as detected in an uploaded photo.
 *
 * The bands are the same four the YOLOv8 detector predicts, and the score is
 * the area-weighted severity of everything the model found (charred soil 100,
 * bare soil 60, regrowth 20, unburned vegetation 0). The colour-heuristic
 * fallback applies the identical formula to pixel fractions, so a severity
 * level means the same thing whichever path produced it.
 */
enum BurnSeverity: string
{
    case Unburned = 'unburned';
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Unburned => 'Tidak Terbakar',
            self::Low => 'Keparahan Ringan',
            self::Moderate => 'Keparahan Sedang',
            self::High => 'Keparahan Berat',
        };
    }

    /**
     * Short label for chips and map legends.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Unburned => 'Tidak terbakar',
            self::Low => 'Ringan',
            self::Moderate => 'Sedang',
            self::High => 'Berat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unburned => '#1B9E4B',
            self::Low => '#F9C74F',
            self::Moderate => '#F3722C',
            self::High => '#D7263D',
        };
    }

    /**
     * Ordinal rank, so severities can be compared and sorted.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Unburned => 0,
            self::Low => 1,
            self::Moderate => 2,
            self::High => 3,
        };
    }

    /**
     * The band a 0-100 severity score falls in.
     */
    public static function fromScore(float|int|null $score): ?self
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score < 10 => self::Unburned,
            $score < 35 => self::Low,
            $score < 65 => self::Moderate,
            default => self::High,
        };
    }
}
