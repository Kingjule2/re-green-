<?php

namespace App\Enums;

/**
 * Who is using the platform, and therefore what they are allowed to see.
 *
 * Farmers (petani) manage their own land and are the source of every field
 * photo. Pemda/NGO ("institution") and corporate CSR/ESG accounts are data
 * consumers: they read the aggregate picture across every monitored land and
 * export the reports that compliance and sustainability disclosures need.
 */
enum UserRole: string
{
    case Farmer = 'farmer';
    case Institution = 'institution';
    case Corporate = 'corporate';

    /**
     * Human-readable label shown in the UI and in reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::Farmer => 'Petani / Pengelola Lahan',
            self::Institution => 'Pemerintah Daerah / NGO',
            self::Corporate => 'Korporasi (CSR/ESG)',
        };
    }

    /**
     * Short label for navigation and badges.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Farmer => 'Petani',
            self::Institution => 'Pemda/NGO',
            self::Corporate => 'Korporasi',
        };
    }

    /**
     * Whether the role reads across every land instead of only its own.
     */
    public function viewsAllLands(): bool
    {
        return $this !== self::Farmer;
    }
}
