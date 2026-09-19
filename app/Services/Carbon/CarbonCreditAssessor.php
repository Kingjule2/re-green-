<?php

namespace App\Services\Carbon;

use App\Enums\AnalysisStatus;
use App\Enums\CarbonEligibility;
use App\Models\CarbonAssessment;
use App\Models\Land;
use App\Models\LandAnalysis;
use Illuminate\Support\Collection;

/**
 * Carbon-credit pre-feasibility: how much a land could sequester, and whether it
 * is worth taking to a certification partner.
 *
 * The estimate is deliberately simple, documented, and replaceable:
 *
 *     tCO2e/year = area_ha x (vegetation cover / 100) x rate(vegetation class)
 *
 * The rates are order-of-magnitude values for humid tropical restoration —
 * young regrowth fixes carbon fastest, a closed canopy slows to maintenance —
 * and they live in a single constant so a site-specific methodology can replace
 * them without touching the rest of the platform. Everything produced here is a
 * screening estimate for a farmer conversation and a pitch, never a certified
 * number, and `method` says so wherever the result is shown.
 */
class CarbonCreditAssessor
{
    /**
     * Sequestration rates in tCO2e per hectare per year, by vegetation class.
     *
     * @var array<string, float>
     */
    private const RATES_TCO2E_PER_HA_YEAR = [
        'regrowth' => 11.0,
        'established' => 6.5,
        'mature' => 3.0,
        'sparse' => 0.0,
    ];

    /**
     * @var array<string, string>
     */
    private const VEGETATION_LABELS = [
        'sparse' => 'Tutupan jarang (belum layak dihitung)',
        'regrowth' => 'Regrowth / agroforestri muda',
        'established' => 'Tanaman berkayu mapan',
        'mature' => 'Tajuk rapat / hutan muda',
    ];

    /**
     * Screening thresholds of the eligibility checklist.
     */
    private const MIN_AREA_HA = 0.5;

    private const MIN_VEGETATION_COVER = 30.0;

    private const MAX_ANALYSIS_AGE_DAYS = 365;

    private const MIN_ANALYSES = 2;

    private const PROJECTION_YEARS = 5;

    /**
     * Screen one land: the estimate, the checklist behind it, and the verdict.
     *
     * @return array<string, mixed>
     */
    public function assess(Land $land): array
    {
        $land->loadMissing('analyses');

        $analyses = $this->completedAnalyses($land->analyses);
        $latest = $analyses->last();
        $cover = round((float) ($latest->vegetation_percentage ?? 0), 1);
        $areaHa = $land->area_ha;
        $vegetationClass = $this->vegetationClass($cover);
        $rate = self::RATES_TCO2E_PER_HA_YEAR[$vegetationClass];
        $sequesteringArea = $areaHa === null ? null : round($areaHa * $cover / 100, 4);
        $perYear = $sequesteringArea === null ? null : round($sequesteringArea * $rate, 2);

        $checklist = $this->checklist($land, $analyses, $latest, $cover, $areaHa);
        $passed = array_values(array_filter($checklist, fn (array $item): bool => $item['passed']));
        $failed = array_values(array_filter($checklist, fn (array $item): bool => ! $item['passed']));
        $verdict = $this->verdict($latest, $failed);

        return [
            'calculated_at' => now()->toIso8601String(),
            'area_ha' => $areaHa,
            'vegetation_cover_pct' => $cover,
            'vegetation_class' => $vegetationClass,
            'vegetation_class_label' => self::VEGETATION_LABELS[$vegetationClass],
            'basis' => $sequesteringArea === null
                ? null
                : sprintf('%s ha area bertutup vegetasi x %.1f tCO2e/ha/tahun', $sequesteringArea, $rate),
            'sequestration_tco2e_per_year' => $perYear,
            'sequestration_5yr_tco2e' => $perYear === null ? null : round($perYear * self::PROJECTION_YEARS, 2),
            'projection_years' => self::PROJECTION_YEARS,
            'eligibility' => [
                'status' => $verdict->value,
                'label' => $verdict->label(),
                'color' => $verdict->color(),
                'passed' => $passed,
                'failed' => $failed,
            ],
            'method' => [
                'name' => 'Pre-feasibility screening (bukan sertifikasi)',
                'formula' => 'luas x fraksi tutupan vegetasi x laju serapan per kelas vegetasi',
                'rates_tco2e_per_ha_year' => self::RATES_TCO2E_PER_HA_YEAR,
                'projection_years' => self::PROJECTION_YEARS,
                'notice' => 'Angka ini estimasi indikatif untuk penyaringan awal. Verifikasi dan sertifikasi kredit karbon dilakukan mitra sertifikasi dengan metodologi spesifik lokasi.',
            ],
        ];
    }

    /**
     * Persist the estimate for a land, keeping any application the farmer
     * already filed — only the numbers move.
     */
    public function refresh(Land $land): CarbonAssessment
    {
        $assessment = $this->assess($land);

        return CarbonAssessment::updateOrCreate(
            ['land_id' => $land->id],
            [
                'user_id' => $land->user_id,
                'calculated_at' => now(),
                'area_ha' => $assessment['area_ha'],
                'vegetation_cover_pct' => $assessment['vegetation_cover_pct'],
                'vegetation_class' => $assessment['vegetation_class'],
                'basis' => $assessment['basis'],
                'sequestration_tco2e_per_year' => $assessment['sequestration_tco2e_per_year'],
                'sequestration_5yr_tco2e' => $assessment['sequestration_5yr_tco2e'],
                'eligibility_status' => $assessment['eligibility']['status'],
                'eligibility_checklist' => [
                    'passed' => $assessment['eligibility']['passed'],
                    'failed' => $assessment['eligibility']['failed'],
                ],
                'method' => $assessment['method'],
            ],
        );
    }

    /**
     * Refresh the estimate after an analysis completed.
     *
     * An older photo must never overwrite the estimate the newest evidence
     * produced, so the refresh is skipped unless this analysis is the land's
     * most recent completed one.
     */
    public function refreshFor(?LandAnalysis $analysis): ?CarbonAssessment
    {
        $land = $analysis?->land;

        if ($land === null) {
            return null;
        }

        $newest = $land->analyses()
            ->where('analysis_status', AnalysisStatus::Completed->value)
            ->orderByRaw('COALESCE(captured_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();

        if ($newest !== null && $newest->id !== $analysis->id) {
            return $land->carbonAssessment;
        }

        return $this->refresh($land);
    }

    /**
     * The completed analyses of a land, oldest period first.
     *
     * @param  Collection<int, LandAnalysis>  $analyses
     * @return Collection<int, LandAnalysis>
     */
    private function completedAnalyses(Collection $analyses): Collection
    {
        return $analyses
            ->filter(fn (LandAnalysis $analysis): bool => $analysis->analysis_status === AnalysisStatus::Completed)
            ->sortBy(fn (LandAnalysis $analysis): int => $analysis->monitoringDate()?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Which vegetation class a cover fraction represents. The bands move with
     * how fast the stand is still accumulating biomass.
     */
    private function vegetationClass(float $cover): string
    {
        return match (true) {
            $cover < self::MIN_VEGETATION_COVER => 'sparse',
            $cover < 60.0 => 'regrowth',
            $cover < 80.0 => 'established',
            default => 'mature',
        };
    }

    /**
     * The checklist a land has to clear before it is worth an application.
     *
     * @param  Collection<int, LandAnalysis>  $analyses
     * @return list<array<string, mixed>>
     */
    private function checklist(Land $land, Collection $analyses, ?LandAnalysis $latest, float $cover, ?float $areaHa): array
    {
        $ageDays = $latest?->monitoringDate()?->diffInDays(now());

        return [
            [
                'key' => 'area',
                'label' => 'Luas lahan minimal '.self::MIN_AREA_HA.' ha',
                'passed' => $areaHa !== null && $areaHa >= self::MIN_AREA_HA,
                'detail' => $areaHa === null ? 'Luas lahan belum diisi.' : sprintf('Luas tercatat %.2f ha.', $areaHa),
                'remedy' => 'Isi luas lahan pada halaman lahan.',
            ],
            [
                'key' => 'analysis',
                'label' => 'Sudah ada foto yang dianalisis',
                'passed' => $latest instanceof LandAnalysis,
                'detail' => $latest instanceof LandAnalysis
                    ? 'Analisis terakhir: '.($latest->monitoringDate()?->toDateString() ?? '-')
                    : 'Belum ada foto lahan yang selesai dianalisis.',
                'remedy' => 'Unggah foto lahan pertama untuk dianalisis model.',
            ],
            [
                'key' => 'analysis_freshness',
                'label' => 'Analisis terakhir maksimal 12 bulan',
                'passed' => $ageDays !== null && $ageDays <= self::MAX_ANALYSIS_AGE_DAYS,
                'detail' => $ageDays === null ? 'Belum ada analisis.' : sprintf('Analisis terakhir %.0f hari lalu.', $ageDays),
                'remedy' => 'Unggah foto terbaru supaya bukti kondisi lahan masih berlaku.',
            ],
            [
                'key' => 'vegetation_cover',
                'label' => 'Tutupan vegetasi minimal '.self::MIN_VEGETATION_COVER.'%',
                'passed' => $cover >= self::MIN_VEGETATION_COVER,
                'detail' => sprintf('Tutupan vegetasi terdeteksi %.1f%%.', $cover),
                'remedy' => 'Tanam penutup tanah lebih dulu; serapan karbon baru terukur setelah vegetasi tumbuh.',
            ],
            [
                'key' => 'monitoring_history',
                'label' => 'Minimal '.self::MIN_ANALYSES.' periode monitoring (bukti sebelum-sesudah)',
                'passed' => $analyses->count() >= self::MIN_ANALYSES,
                'detail' => sprintf('%d periode analisis tersimpan.', $analyses->count()),
                'remedy' => 'Unggah foto lanjutan tiap periode supaya perubahan vegetasi terbukti.',
            ],
            [
                'key' => 'location',
                'label' => 'Koordinat lahan tersedia',
                'passed' => $land->hasCoordinates(),
                'detail' => $land->hasCoordinates()
                    ? sprintf('Titik lahan %.5f, %.5f.', $land->latitude, $land->longitude)
                    : 'Lahan belum punya koordinat.',
                'remedy' => 'Lengkapi titik koordinat lahan (mis. dari GPS ponsel).',
            ],
            [
                'key' => 'model_provenance',
                'label' => 'Hasil analisis menyimpan asal-usul model',
                'passed' => $latest?->ai_model !== null && $latest?->ai_model_version !== null,
                'detail' => $latest?->ai_model === null
                    ? 'Nama dan versi model tidak tercatat.'
                    : sprintf('Model %s v%s.', $latest->ai_model, $latest->ai_model_version),
                'remedy' => 'Jalankan ulang analisis supaya versi model tercatat sebagai bukti.',
            ],
        ];
    }

    /**
     * The screening verdict: a land with nothing to screen is out, a land with
     * open checklist items is not ready yet, and a land that clears every item
     * is worth an application.
     *
     * @param  list<array<string, mixed>>  $failed
     */
    private function verdict(?LandAnalysis $latest, array $failed): CarbonEligibility
    {
        if ($latest === null) {
            return CarbonEligibility::Ineligible;
        }

        return $failed === [] ? CarbonEligibility::Eligible : CarbonEligibility::NotYet;
    }
}
