/**
 * The aggregate dashboard: what a pemda/NGO or corporate account can see that a
 * farmer cannot.
 *
 * `GET /api/v1/dashboard` answers with `scope: "all"` for these accounts and
 * adds `by_region`, `top_risk_lands`, `farmers` and `organizations` on top of the
 * shared numbers. Every figure on this page is the server's: the page ranks
 * nothing, averages nothing and infers nothing, because a report generated from
 * the same data has to agree with what is shown here.
 */
import React from 'react';
import { Link } from '@/react/lib/router';
import { overview } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatNumber, formatPeriod } from '@/react/lib/format';
import EmptyState from '@/react/components/shared/EmptyState';
import KpiCard from '@/react/components/shared/KpiCard';
import TrendChart from '@/react/components/charts/TrendChart';
import LandStatusTable from '@/react/components/institution/LandStatusTable';
import Panel from '@/react/components/institution/Panel';
import RegionTable from '@/react/components/institution/RegionTable';
import SeverityMixBar from '@/react/components/institution/SeverityMixBar';

const QUICK_LINK =
    'inline-flex items-center gap-1.5 rounded-lg border border-border-subtle px-3 py-2 font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-low';

export default function InstitutionDashboardPage() {
    const { data, error, loading, reload } = useResource((options) => overview.dashboard(options));

    if (loading) {
        return (
            <p className="font-body-sm text-body-sm text-on-surface-variant" role="status">
                Memuat agregat lintas lahan…
            </p>
        );
    }

    if (error) {
        return (
            <EmptyState
                icon="cloud_off"
                title="Agregat tidak bisa dimuat"
                description={error.message}
                action={
                    <button type="button" onClick={reload} className="mt-2 rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary">
                        Coba lagi
                    </button>
                }
            />
        );
    }

    if (data === null || data.lands === 0) {
        return (
            <EmptyState
                icon="landscape"
                title="Belum ada lahan terpantau"
                description="Agregat muncul setelah ada lahan terdaftar dengan foto yang dianalisis."
                action={
                    <Link to="/app/laporan" className="mt-2 font-label-md text-label-md text-primary hover:underline">
                        Lihat laporan
                    </Link>
                }
            />
        );
    }

    const trend = data.vegetation_trend ?? [];
    const organizations = data.organizations ?? [];
    const carbon = data.carbon ?? {};

    return (
        <div className="space-y-6">
            <header className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Dasbor agregat lintas lahan</h1>
                    <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                        Ringkasan {formatNumber(data.lands)} lahan terpantau milik {formatNumber(data.farmers ?? 0)} petani
                        {' '}di {formatNumber((data.by_region ?? []).length)} wilayah.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link to="/app/peta" className={QUICK_LINK}>
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">map</span>
                        Peta sebaran
                    </Link>
                    <Link to="/app/laporan" className={QUICK_LINK}>
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">description</span>
                        Laporan &amp; ekspor
                    </Link>
                    <Link to="/app/karbon" className={QUICK_LINK}>
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">eco</span>
                        Portofolio karbon
                    </Link>
                </div>
            </header>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <KpiCard title="Lahan terpantau" value={data.lands} unit="lahan" icon="landscape" />
                <KpiCard title="Luas terpantau" value={data.area_ha} unit="ha" icon="square_foot" delay={0.05} />
                <KpiCard title="Periode monitoring" value={data.analyses} unit="analisis" icon="photo_camera" delay={0.1} />
                <KpiCard
                    title="Skor kesehatan rata-rata"
                    value={data.average_health_score ?? '—'}
                    unit={data.average_health_score == null ? '' : '/ 100'}
                    icon="monitor_heart"
                    delay={0.15}
                />
                <KpiCard title="Petani terlibat" value={data.farmers ?? 0} unit="akun" icon="person" delay={0.2} />
                <KpiCard
                    title="Organisasi terlibat"
                    value={organizations.length}
                    unit="organisasi"
                    icon="corporate_fare"
                    delay={0.25}
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <Panel
                    title="Rekap per wilayah"
                    description="Wilayah diambil dari lokasi yang diisi pemilik lahan; rata-rata dihitung dari analisis terakhir tiap lahan."
                    className="xl:col-span-2"
                >
                    <RegionTable rows={data.by_region ?? []} mode="health" />
                </Panel>

                <div className="space-y-6">
                    <Panel
                        title="Sebaran keparahan"
                        description="Jumlah lahan per kelas keparahan pada analisis terakhir."
                    >
                        <SeverityMixBar mix={data.severity_mix ?? []} />
                    </Panel>

                    <Panel
                        title="Ringkasan karbon"
                        description="Penyaringan awal, bukan kredit tersertifikasi."
                    >
                        <dl className="grid grid-cols-2 gap-3 font-body-sm text-body-sm">
                            <div>
                                <dt className="text-on-surface-variant">Lahan layak diajukan</dt>
                                <dd className="font-headline-sm text-headline-sm text-on-surface">
                                    {formatNumber(carbon.eligible ?? 0)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-on-surface-variant">Sudah diajukan</dt>
                                <dd className="font-headline-sm text-headline-sm text-on-surface">
                                    {formatNumber(carbon.submitted ?? 0)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-on-surface-variant">Pipeline per tahun</dt>
                                <dd className="font-headline-sm text-headline-sm text-on-surface">
                                    {formatNumber(carbon.pipeline_tco2e_per_year, 2)}
                                    <span className="ml-1 font-body-sm text-body-sm font-normal text-on-surface-variant">tCO2e</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-on-surface-variant">Pipeline 5 tahun</dt>
                                <dd className="font-headline-sm text-headline-sm text-on-surface">
                                    {formatNumber(carbon.pipeline_5yr_tco2e ?? 0, 2)}
                                    <span className="ml-1 font-body-sm text-body-sm font-normal text-on-surface-variant">tCO2e</span>
                                </dd>
                            </div>
                        </dl>
                        <Link
                            to="/app/karbon"
                            className="mt-4 inline-flex font-label-md text-label-md text-primary hover:underline"
                        >
                            Buka portofolio karbon
                        </Link>
                    </Panel>
                </div>
            </div>

            <Panel
                title="Perkembangan vegetasi dan kesehatan"
                description="Rata-rata hasil analisis per bulan monitoring, dari periode terlama ke terbaru."
            >
                <TrendChart
                    labels={trend.map((point) => formatPeriod(point.period))}
                    series={[
                        {
                            key: 'vegetation',
                            label: 'Tutupan vegetasi (%)',
                            color: '#1B9E4B',
                            values: trend.map((point) => point.vegetation_percentage),
                        },
                        {
                            key: 'health',
                            label: 'Skor kesehatan lahan',
                            color: '#0ea5e9',
                            dashed: true,
                            values: trend.map((point) => point.health_score),
                        },
                    ]}
                    valueSuffix=""
                    height={280}
                />
                {trend.length > 0 && (
                    <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                        {formatNumber(trend.length)} periode tercatat; periode terakhir {formatPeriod(trend[trend.length - 1].period)}
                        {' '}dari {formatNumber(trend[trend.length - 1].analyses)} analisis.
                    </p>
                )}
            </Panel>

            <Panel
                title="Lahan prioritas"
                description="Lima lahan dengan skor kesehatan terendah pada analisis terakhir — tempat intervensi paling mendesak."
            >
                <LandStatusTable rows={data.top_risk_lands ?? []} mode="risk" />
            </Panel>
        </div>
    );
}
