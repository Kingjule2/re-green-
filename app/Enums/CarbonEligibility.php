<?php

namespace App\Enums;

/**
 * Whether a land currently satisfies the pre-feasibility checklist for a
 * carbon-credit application.
 *
 * This is a screening verdict, not a certification: the checklist answers "is
 * this land worth taking to a certification partner", and every failed item
 * carries the remedy that would fix it.
 */
enum CarbonEligibility: string
{
    case Eligible = 'eligible';
    case NotYet = 'not_yet';
    case Ineligible = 'ineligible';

    public function label(): string
    {
        return match ($this) {
            self::Eligible => 'Layak diajukan',
            self::NotYet => 'Belum memenuhi syarat',
            self::Ineligible => 'Tidak layak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Eligible => '#1B9E4B',
            self::NotYet => '#F9C74F',
            self::Ineligible => '#E76F51',
        };
    }
}
