/**
 * The farmer's dashboard: their own lands, what changed since the last photo,
 * and what still needs a photo.
 *
 * Everything here comes from `GET /dashboard` with `scope: "own"` — the server
 * already decided what a farmer may see, so the page never filters land data
 * itself. An account with no land yet gets the empty state that leads to the
 * registration form instead of a screen full of zeroes.
 */
import React from 'react';
import { overview } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatNumber, formatPeriod } from '@/react/lib/format';
import { tSeverity } from '@/react/lib/i18n';
import { Link } from '@/react/lib/router';
import { useAuth } from '@/react/contexts/AuthContext';
import KpiCard from '@/react/components/shared/KpiCard';
import EmptyState from '@/react/components/shared/EmptyState';
import TrendChart from '@/react/components/charts/TrendChart';
import { Button, Chip, DetailRow, ErrorPanel, LoadingBlock, MetricTile, Panel, ShareBar } from '@/react/components/farmer/primitives';
import { describeError, formatPeriodKey } from '@/react/components/farmer/labels';

const VEGETATION_COLOR = '#1B9E4B';
const HEALTH_COLOR = '#0288D1';

function NeedsMonitoring({ rows }) {
    if (rows.length === 0) {
        return <p className="font-body-sm text-body-sm text-on-surface-variant">Semua lahan sudah punya foto terbaru.</p>;
    }

    return (
        <ul className="divide-y divide-border-subtle">
            {rows.map((row) => (
                <li key={row.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div className="min-w-0">
                        <Link to={`/app/lahan/${row.id}`} className="font-body-sm text-body-sm font-semibold text-on-surface hover:text-primary">
                            {row.name}
                        </Link>
                        <p className="font-body-sm text-xs text-on-surface-variant">
                            {row.last_analysis_at === null
                                ? 'Belum pernah difoto'
                                : `Foto terakhir ${formatNumber(row.days_since_last_analysis)} hari lalu`}
                            {row.location_name ? ` · ${row.location_name}` : ''}
                        </p>
                    </div>
                    <Link
                        to={`/app/lahan/${row.id}`}
                        className="inline-flex items-center gap-1 rounded-lg border border-border-subtle px-3 py-1.5 font-body-sm text-xs font-semibold text-primary hover:bg-surface-container-low"
                    >
                        <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                            add_a_photo
                        </span>
                        Unggah foto
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function LatestAnalyses({ rows }) {
    if (rows.length === 0) {
        return (
            <p className="font-body-sm text-body-sm text-on-surface-variant">
                Belum ada hasil analisis. Unggah foto lahan untuk memulai.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border-subtle">
            {rows.map((analysis) => (
                <li key={analysis.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div className="min-w-0">
                        <Link
                            to={`/app/analisis/${analysis.id}`}
                            className="font-body-sm text-body-sm font-semibold text-on-surface hover:text-primary"
                        >
                            Periode {formatPeriod(analysis.captured_at)}
                        </Link>
                        <p className="font-body-sm text-xs text-on-surface-variant">
                            Skor {formatNumber(analysis.metrics?.health_score)} · vegetasi {formatNumber(analysis.metrics?.vegetation_percentage, 1)}%
                        </p>
                    </div>
                    <Chip
                        label={analysis.burn_severity?.label ?? tSeverity(analysis.burn_severity?.level)}
                        color={analysis.burn_severity?.color}
                    />
                </li>
            ))}
        </ul>
    );
}

export default function FarmerDashboardPage() {
    const { user } = useAuth();
    const { data, error, loading, reload } = useResource((options) => overview.dashboard(options), []);

    if (loading) {
        return <LoadingBlock label="Memuat dasbor…" />;
    }

    if (error) {
        return <ErrorPanel message={describeError(error)} onRetry={reload} />;
    }

    if (!data) {
        return null;
    }

    if (data.lands === 0) {
        return (
            <EmptyState
                icon="landscape"
                title="Belum ada lahan terdaftar"
                description="Daftarkan lahan bekas kebakaran Anda, lalu unggah foto pertamanya untuk melihat kondisi dan rekomendasi pemulihan."
                action={
                    <Link
                        to="/app/lahan"
                        className="mt-2 inline-flex items-center gap-2 rounded-lg bg-primary px-3.5 py-2 font-body-sm text-body-sm font-semibold text-on-primary"
                    >
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                            add
                        </span>
                        Daftarkan lahan
                    </Link>
                }
            />
        );
    }

    const trend = data.vegetation_trend ?? [];
    const severityMix = data.severity_mix ?? [];
    const severityTotal = severityMix.reduce((total, row) => total + (row.count ?? 0), 0);
    const carbon = data.carbon ?? {};

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="font-headline-md text-headline-md text-on-surface">Selamat datang, {user?.name?.split(' ')[0]}</h2>
                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                        Ringkasan kondisi {formatNumber(data.lands)} lahan yang Anda kelola.
                    </p>
                </div>
                <Link
                    to="/app/lahan"
                    className="inline-flex items-center gap-1.5 rounded-lg border border-border-subtle bg-surface-container-lowest px-3 py-2 font-body-sm text-body-sm font-semibold text-primary hover:bg-surface-container-low"
                >
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        landscape
                    </span>
                    Kelola lahan
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard title="Lahan dikelola" value={data.lands} unit="lahan" icon="landscape" delay={0} />
                <KpiCard title="Luas total" value={data.area_ha} unit="ha" icon="straighten" delay={0.05} />
                <KpiCard title="Periode monitoring" value={data.analyses} unit="foto" icon="photo_camera" delay={0.1} />
                <KpiCard
                    title="Skor kesehatan rata-rata"
                    value={data.average_health_score ?? '—'}
                    unit="/100"
                    icon="monitor_heart"
                    delay={0.15}
                />
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <Panel
                    title="Perkembangan vegetasi"
                    subtitle={trend.length > 0 ? `${trend.length} periode monitoring` : null}
                    icon="show_chart"
                    className="xl:col-span-2"
                >
                    <TrendChart
                        labels={trend.map((point) => formatPeriodKey(point.period))}
                        series={[
                            {
                                key: 'vegetation',
                                label: 'Tutupan vegetasi (%)',
                                color: VEGETATION_COLOR,
                                values: trend.map((point) => point.vegetation_percentage),
                            },
                            {
                                key: 'health',
                                label: 'Skor kesehatan (/100)',
                                color: HEALTH_COLOR,
                                values: trend.map((point) => point.health_score),
                                dashed: true,
                            },
                        ]}
                        valueSuffix=""
                    />
                </Panel>

                <Panel title="Sebaran keparahan" subtitle="Dari analisis terakhir tiap lahan" icon="local_fire_department">
                    <ul className="space-y-4">
                        {severityMix.map((row) => (
                            <li key={row.level} className="space-y-1.5">
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="font-body-sm text-body-sm text-on-surface">{row.label ?? tSeverity(row.level)}</span>
                                    <span className="font-body-sm text-body-sm font-semibold text-on-surface">
                                        {formatNumber(row.count)}
                                        <span className="ml-1 font-normal text-on-surface-variant">
                                            lahan{severityTotal > 0 ? ` · ${formatNumber((row.count / severityTotal) * 100)}%` : ''}
                                        </span>
                                    </span>
                                </div>
                                <ShareBar percentage={severityTotal > 0 ? (row.count / severityTotal) * 100 : 0} color={row.color} />
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Panel title="Perlu foto baru" subtitle="Periode monitoring paling lama tidak terbarui" icon="schedule">
                    <NeedsMonitoring rows={data.needs_monitoring ?? []} />
                </Panel>

                <Panel title="Analisis terbaru" icon="photo_library">
                    <LatestAnalyses rows={data.latest_analyses ?? []} />
                </Panel>
            </div>

            <Panel title="Ringkasan karbon" subtitle="Estimasi indikatif dari hasil analisis terakhir" icon="eco">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricTile label="Lahan layak diajukan" value={formatNumber(carbon.eligible)} unit="lahan" icon="verified" />
                    <MetricTile label="Sudah diajukan" value={formatNumber(carbon.submitted)} unit="lahan" icon="send" />
                    <MetricTile
                        label="Potensi per tahun"
                        value={formatNumber(carbon.pipeline_tco2e_per_year, 2)}
                        unit="tCO₂e/tahun"
                        icon="co2"
                    />
                    <MetricTile label="Potensi 5 tahun" value={formatNumber(carbon.pipeline_5yr_tco2e, 2)} unit="tCO₂e" icon="timeline" />
                </div>
                <dl className="mt-4">
                    <DetailRow label="Lahan dengan syarat terpenuhi" value={formatNumber(carbon.eligible)} />
                    <DetailRow label="Pengajuan yang sudah masuk" value={formatNumber(carbon.submitted)} />
                </dl>
                <div className="mt-4">
                    <Button variant="secondary" icon="eco" onClick={reload}>
                        Perbarui ringkasan
                    </Button>
                </div>
            </Panel>
        </div>
    );
}
