<?php

namespace Database\Factories;

use App\Enums\CarbonEligibility;
use App\Enums\CarbonSubmissionStatus;
use App\Models\CarbonAssessment;
use App\Models\Land;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarbonAssessment>
 */
class CarbonAssessmentFactory extends Factory
{
    protected $model = CarbonAssessment::class;

    /**
     * The pre-feasibility checklist, in the order the assessment screens a
     * land. `passed` is filled in from the verdict; `remedy` explains what
     * would flip a failed item.
     *
     * @var list<array{key: string, label: string, detail: string, remedy: string}>
     */
    private const CHECKLIST_ITEMS = [
        [
            'key' => 'minimum_area',
            'label' => 'Luas lahan minimal 0,5 ha',
            'detail' => 'Lahan memenuhi ambang luas minimum untuk pengajuan kredit karbon.',
            'remedy' => 'Gabungkan dengan bidang lain atau ajukan lahan yang lebih luas.',
        ],
        [
            'key' => 'analysis_recency',
            'label' => 'Analisis terakhir selesai dan berumur ≤ 12 bulan',
            'detail' => 'Ada analisis pemantauan yang selesai dalam dua belas bulan terakhir.',
            'remedy' => 'Unggah dan selesaikan analisis foto terbaru.',
        ],
        [
            'key' => 'vegetation_cover',
            'label' => 'Tutupan vegetasi minimal 30%',
            'detail' => 'Tutupan vegetasi terukur sudah melewati ambang kelayakan.',
            'remedy' => 'Tunggu pemulihan tutupan atau lakukan penanaman tambahan.',
        ],
        [
            'key' => 'monitoring_history',
            'label' => 'Riwayat pemantauan minimal 2 analisis',
            'detail' => 'Bukti sebelum/sesudah tersedia dari setidaknya dua analisis.',
            'remedy' => 'Tambahkan satu analisis pembanding di periode berikutnya.',
        ],
        [
            'key' => 'geolocation',
            'label' => 'Titik koordinat tersedia',
            'detail' => 'Lokasi dapat dipetakan dan diverifikasi.',
            'remedy' => 'Lengkapi latitude dan longitude lahan.',
        ],
        [
            'key' => 'model_provenance',
            'label' => 'Asal-usul model tercatat',
            'detail' => 'Nama dan versi model pada analisis terakhir tercatat.',
            'remedy' => 'Jalankan ulang analisis dengan pipeline yang mencatat provenance model.',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(CarbonEligibility::cases());

        $area = fake()->randomFloat(2, 0.5, 40);
        $vegetationCover = fake()->randomFloat(1, 30, 85);
        $class = fake()->randomElement(['regrowth', 'established', 'mature']);
        $perYear = round($area * ($vegetationCover / 100) * $this->growthFactor($class), 2);

        return [
            'land_id' => Land::factory(),
            'user_id' => User::factory(),
            'calculated_at' => now(),
            'area_ha' => $area,
            'vegetation_cover_pct' => $vegetationCover,
            'vegetation_class' => $class,
            'basis' => 'latest_completed_analysis',
            'sequestration_tco2e_per_year' => $perYear,
            'sequestration_5yr_tco2e' => round($perYear * 5, 2),
            'eligibility_status' => $status,
            'eligibility_checklist' => $this->checklist($status),
            'method' => [
                'standard' => 'IPCC 2006 AFOLU',
                'equation' => 'area_ha × vegetation_cover_fraction × factor(vegetation_class)',
                'conversion_factor' => '44/12',
                'note' => 'Estimasi indikatif, bukan sertifikasi kredit karbon.',
            ],
            'submission_status' => CarbonSubmissionStatus::NotSubmitted,
            'partner' => null,
            'contact_name' => null,
            'contact_email' => null,
            'offered_area_ha' => null,
            'notes' => null,
            'submitted_at' => null,
        ];
    }

    /**
     * An assessment whose application was filled in and forwarded.
     */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'submission_status' => CarbonSubmissionStatus::Submitted,
            'partner' => fake()->randomElement(['Yayasan Konservasi Karbon', 'PT Verifikasi Hijau', 'Karbon Nusantara']),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'offered_area_ha' => fake()->randomFloat(2, 0.5, 20),
            'notes' => fake()->sentence(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * The checklist behind a verdict: every item passes when the land is
     * eligible, and the failed items carry the remedy that would fix them.
     *
     * @return list<array{key: string, label: string, passed: bool, detail: string, remedy: string|null}>
     */
    private function checklist(CarbonEligibility $status): array
    {
        $failed = match ($status) {
            CarbonEligibility::Eligible => [],
            CarbonEligibility::NotYet => ['vegetation_cover', 'model_provenance'],
            CarbonEligibility::Ineligible => ['minimum_area', 'analysis_recency', 'vegetation_cover', 'monitoring_history', 'model_provenance'],
        };

        $checklist = [];

        foreach (self::CHECKLIST_ITEMS as $item) {
            $passed = ! in_array($item['key'], $failed, true);

            $checklist[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'passed' => $passed,
                'detail' => $passed ? $item['detail'] : $item['remedy'],
                'remedy' => $passed ? null : $item['remedy'],
            ];
        }

        return $checklist;
    }

    /**
     * The biomass-growth factor, in tCO2e per hectare per year, the estimate
     * multiplies the cover fraction with — higher for a mature stand.
     */
    private function growthFactor(string $vegetationClass): float
    {
        return match ($vegetationClass) {
            'regrowth' => fake()->randomFloat(2, 4.0, 6.5),
            'established' => fake()->randomFloat(2, 6.5, 9.5),
            default => fake()->randomFloat(2, 9.5, 13.0),
        };
    }
}
