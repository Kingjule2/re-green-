<?php

namespace App\Enums;

/**
 * Lifecycle states for a land photo analysis job.
 *
 * The pipeline moves Pending -> Processing -> Completed, or -> Failed when the
 * ML service or the image cannot produce a usable result.
 */
enum AnalysisStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Human-readable label for display in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Short, user-facing status message shown while polling.
     */
    public function message(): string
    {
        return match ($this) {
            self::Pending => 'Foto menunggu antrean analisis...',
            self::Processing => 'Model YOLOv8 sedang membaca kondisi lahan...',
            self::Completed => 'Analisis selesai.',
            self::Failed => 'Foto ini tidak bisa dianalisis. Coba unggah foto lain.',
        };
    }

    /**
     * Whether the analysis has reached a terminal state.
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
