/**
 * Carbon portfolio: the pre-feasibility screening across every land the account
 * may read.
 *
 * `GET /api/v1/carbon/portfolio` is institution/corporate only and answers 403
 * for a farmer account — which this page treats as a role boundary, not an
 * error. The wording of the honesty note comes from the assessor itself
 * (`method.notice` on the per-land endpoint), so the platform's disclaimer is
 * quoted from its source rather than paraphrased here.
 */
import React, { useMemo } from 'react';
import { Link } from '@/react/lib/router';
import { carbon } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatNumber } from '@/react/lib/format';
import EmptyState from '@/react/components/shared/EmptyState';
import KpiCard from '@/react/components/shared/KpiCard';
import LandStatusTable from '@/react/components/institution/LandStatusTable';
import Panel from '@/react/components/institution/Panel';
import RegionTable from '@/react/components/institution/RegionTable';

/** Used when the per-land method payload cannot be read (yet). */
const FALLBACK_NOTICE =
    'Angka pada halaman ini adalah estimasi indikatif untuk penyaringan awal. Verifikasi dan sertifikasi kredit karbon dilakukan mitra sertifikasi dengan metodologi spesifik lokasi.';

const VEGETATION_CLASS_LABELS = {
    sparse: 'Tutupan jarang',
    regrowth: 'Regrowth / agroforestri muda',
    established: 'Tanaman berkayu mapan',
    mature: 'Tajuk rapat / hutan muda',
};

export default function CarbonPortfolioPage() {
    const portfolio = useResource((options) => carbon.portfolio(options));
    const rows = portfolio.data?.lands ?? [];
    const sampleLandId = rows.length > 0 ? rows[0].land_id : null;

    // The portfolio payload carries no `method` block, so the assessor's own
    // note and rates are read from one land — the screening rules are the same
    // for every land.
    const method = useResource(
        (options) => (sampleLandId === null ? Promise.resolve(null) : carbon.show(sampleLandId, options)),
        [sampleLandId],
        { enabled: sampleLandId !== null },
    );

    const notice = method.data?.method?.notice ?? FALLBACK_NOTICE;
    const rates = useMemo(() => {
        const table = method.data?.method?.rates_tco2e_per_ha_year ?? null;

        return table === null
            ? []
            : Object.entries(table).map(([key, rate]) => ({
                  key,
                  label: VEGETATION_CLASS_LABELS[key] ?? key,
                  rate: Number(rate),
              }));
    }, [method.data]);

    if (portfolio.loading) {
        return (
            <p className="font-body-sm text-body-sm text-on-surface-variant" role="status">
                Memuat portofolio karbon…
            </p>
        );
    }

    if (portfolio.error?.isForbidden) {
        return (
            <EmptyState
                icon="lock"
                title="Portofolio karbon hanya untuk akun pemerintah/NGO dan korporasi"
                description="Akun petani mengikuti status karbon lahannya sendiri dari halaman detail lahan. Portofolio lintas lahan disediakan untuk pemda, NGO, dan korporasi."
                action={
                    <Link to="/app/lahan" className="mt-2 font-label-md text-label-md text-primary hover:underline">
                        Buka daftar lahan
                    </Link>
                }
            />
        );
    }

    if (portfolio.error) {
        return (
            <EmptyState
                icon="cloud_off"
                title="Portofolio tidak bisa dimuat"
                description={portfolio.error.message}
                action={
                    <button
                        type="button"
                        onClick={portfolio.reload}
                        className="mt-2 rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary"
                    >
                        Coba lagi
                    </button>
                }
            />
        );
    }

    const data = portfolio.data ?? {};

    if ((data.lands_total ?? 0) === 0) {
        return (
            <EmptyState
                icon="eco"
                title="Belum ada lahan tersaring"
                description="Penyaringan karbon muncul setelah ada lahan terpantau dengan hasil analisis foto."
            />
        );
    }

    return (
        <div className="space-y-6">
            <header>
                <h1 className="font-headline-md text-headline-md text-on-surface">Portofolio karbon</h1>
                <p className="mt-1 max-w-3xl font-body-sm text-body-sm text-on-surface-variant">
                    Penyaringan awal kelayakan kredit karbon atas {formatNumber(data.lands_total)} lahan terpantau,
                    beserta estimasi serapan yang bisa dipakai untuk membuka pembicaraan dengan mitra sertifikasi.
                </p>
            </header>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard title="Lahan disaring" value={data.lands_total ?? 0} unit="lahan" icon="fact_check" />
                <KpiCard title="Layak diajukan" value={data.eligible ?? 0} unit="lahan" icon="verified" delay={0.05} />
                <KpiCard title="Belum memenuhi syarat" value={data.not_yet ?? 0} unit="lahan" icon="pending_actions" delay={0.1} />
                <KpiCard title="Tidak layak" value={data.ineligible ?? 0} unit="lahan" icon="block" delay={0.15} />
                <KpiCard title="Sudah diajukan" value={data.submitted ?? 0} unit="lahan" icon="send" delay={0.2} />
                <KpiCard title="Diteruskan ke partner" value={data.forwarded ?? 0} unit="lahan" icon="handshake" delay={0.25} />
                <KpiCard
                    title="Pipeline per tahun"
                    value={data.pipeline_tco2e_per_year ?? 0}
                    unit="tCO2e"
                    icon="co2"
                    delay={0.3}
                />
                <KpiCard
                    title="Pipeline 5 tahun"
                    value={data.pipeline_5yr_tco2e ?? 0}
                    unit="tCO2e"
                    icon="timeline"
                    delay={0.35}
                />
            </div>

            <Panel
                title="Dasar perhitungan dan batasannya"
                description="Baca ini sebelum angka pipeline dipakai dalam dokumen apa pun."
            >
                <p className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 font-body-sm text-body-sm text-amber-900">
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">info</span>
                    <span>{notice}</span>
                </p>

                {method.data?.method && (
                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">
                                Rumus
                            </h3>
                            <p className="mt-1 font-body-sm text-body-sm text-on-surface">
                                {method.data.method.formula}
                            </p>
                            <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                                Proyeksi {formatNumber(method.data.method.projection_years ?? 0)} tahun.
                                {method.data.method.name ? ` Metode: ${method.data.method.name}.` : ''}
                            </p>
                        </div>
                        <div>
                            <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">
                                Laju serapan per kelas vegetasi (tCO2e/ha/tahun)
                            </h3>
                            <ul className="mt-1 space-y-1 font-body-sm text-body-sm text-on-surface">
                                {rates.map((entry) => (
                                    <li key={entry.key} className="flex items-baseline justify-between gap-3">
                                        <span>{entry.label}</span>
                                        <span className="tabular-nums text-on-surface-variant">
                                            {formatNumber(entry.rate, 1)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
            </Panel>

            <Panel
                title="Potensi per wilayah"
                description="Wilayah dengan pipeline terbesar lebih dulu; warna baris mengikuti seberapa banyak lahan di wilayah itu yang sudah layak."
            >
                <RegionTable rows={data.by_region ?? []} mode="carbon" />
            </Panel>

            <Panel
                title="Kelayakan per lahan"
                description="Daftar perbaikan pada kolom terakhir adalah item checklist yang belum terpenuhi — perbaikan itulah yang menaikkan status lahan."
            >
                <LandStatusTable rows={rows} mode="carbon" />
            </Panel>
        </div>
    );
}
