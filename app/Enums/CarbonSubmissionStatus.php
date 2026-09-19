<?php

namespace App\Enums;

/**
 * How far a carbon-credit application has travelled.
 *
 * `not_submitted` — the land is only being assessed;
 * `submitted` — the farmer filled in the application form on the platform;
 * `forwarded` — re:green handed the package to a certification partner.
 *
 * The platform stops here on purpose: the certification itself happens at the
 * partner, so no case claims a credit has been issued.
 */
enum CarbonSubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Forwarded = 'forwarded';

    public function label(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Belum diajukan',
            self::Submitted => 'Diajukan',
            self::Forwarded => 'Diteruskan ke partner',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotSubmitted => '#94a3b8',
            self::Submitted => '#0ea5e9',
            self::Forwarded => '#1B9E4B',
        };
    }
}
