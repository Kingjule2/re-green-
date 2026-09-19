/**
 * One analysis in full: the photo (or the model's segmentation of it), how
 * severe the burn looked, what the land consists of, what the engine
 * recommends, and what the site can grow.
 *
 * The page is deliberately explicit about provenance. Fire severity says which
 * method produced it; every agriculture input says whether it was sampled from a
 * dataset, declared by the farmer, or is simply not known; and the terrain, soil
 * and climate blocks say so when no dataset could be reached instead of showing
 * an empty number. That is more honest than any sentence the engine could print.
 */
import React, { useEffect } from 'react';
import { analyses as analysesApi } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatDate, formatDateTime, formatNumber, formatResolution } from '@/react/lib/format';
import { tCaptureSource, tCover, tDirection, tIssue, tParameter, tPotential, tSeverity } from '@/react/lib/i18n';
import { Link, useParams } from '@/react/lib/router';
import { Button, Chip, DetailRow, ErrorPanel, LoadingBlock, MetricTile, Panel, ShareBar } from '@/react/components/farmer/primitives';
import CropRanking from '@/react/components/farmer/CropRanking';
import RecommendationList from '@/react/components/farmer/RecommendationList';
import SegmentationGrid from '@/react/components/farmer/SegmentationGrid';
import {
    describeError,
    tAspect,
    tElevationBand,
    tInputSource,
    tLimitingFactor,
    tMethod,
    tRisk,
    tSlopeClass,
    tSuitability,
} from '@/react/components/farmer/labels';

const HEALTH_TONES = { healthy: 'success', moderate: 'default', degraded: 'critical', critical: 'critical' };

/** One dataset block: what was sampled, from where, or why it is not there. */
function ContextPanel({ title, icon, block, rows, unavailableNote }) {
    return (
        <div className="rounded-xl border border-border-subtle bg-surface-container-low p-4">
            <div className="flex items-center gap-2">
                <span className="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">
                    {icon}
                </span>
                <h3 className="font-body-md text-body-md font-semibold text-on-surface">{title}</h3>
            </div>

            {block?.available ? (
                <>
                    <dl className="mt-2">
                        {rows.map((row) => (
                            <DetailRow key={row.label} label={row.label} value={row.value} />
                        ))}
                    </dl>
                    {block.source?.label && (
                        <p className="mt-2 font-body-sm text-xs text-on-surface-variant">Sumber: {block.source.label}</p>
                    )}
                </>
            ) : (
                <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">{unavailableNote}</p>
            )}
        </div>
    );
}

function AgricultureContext({ agriculture }) {
    if (!agriculture) {
        return null;
    }

    const inputs = Object.entries(agriculture.inputs ?? {});
    const sources = agriculture.input_sources ?? {};
    const rainfall = agriculture.rainfall ?? {};
    const missing = agriculture.missing_inputs ?? [];
    const limiting = agriculture.limiting_factors ?? [];

    return (
        <Panel
            title="Konteks lahan untuk rekomendasi tanaman"
            subtitle="Nilai yang dipakai mesin penilaian kesesuaian"
            icon="grass"
        >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MetricTile label="Kelas kemampuan lahan" value={agriculture.suitability ? tSuitability(agriculture.suitability) : '—'} />
                <MetricTile label="Ketinggian" value={agriculture.elevation_band ? tElevationBand(agriculture.elevation_band.key) : '—'} />
                <MetricTile label="Kemiringan" value={agriculture.slope_class ? tSlopeClass(agriculture.slope_class) : '—'} />
                <MetricTile label="Tekstur tanah" value={agriculture.soil_texture ?? '—'} />
            </div>

            <div className="mt-4">
                <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Nilai masukan dan asalnya</h3>
                <ul className="mt-2 divide-y divide-border-subtle">
                    {inputs.map(([parameter, value]) => (
                        <li key={parameter} className="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span className="font-body-sm text-body-sm text-on-surface-variant">{tParameter(parameter)}</span>
                            <span className="flex items-center gap-3">
                                <span className="font-body-sm text-body-sm font-medium text-on-surface">
                                    {value === null || value === undefined || value === '' ? 'belum ada' : formatNumber(value, String(value).includes('.') ? 1 : 0)}
                                </span>
                                <Chip label={tInputSource(sources[parameter])} />
                            </span>
                        </li>
                    ))}
                </ul>
                <p className="mt-2 font-body-sm text-xs text-on-surface-variant">
                    Parameter yang tidak bisa disampel dari dataset memakai nilai yang diisi petani pada data lahan; sisanya ditandai belum diketahui.
                </p>
            </div>

            {missing.length > 0 && (
                <p className="mt-3 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                    Belum diketahui: {missing.map((parameter) => tParameter(parameter)).join(', ')}.
                </p>
            )}

            <div className="mt-4">
                <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Curah hujan dan musim</h3>
                {rainfall.available ? (
                    <dl className="mt-2 grid grid-cols-1 gap-x-8 lg:grid-cols-2">
                        <div>
                            <DetailRow label="Curah hujan tahunan" value={`${formatNumber(rainfall.annual_rainfall_mm)} mm`} />
                            <DetailRow label="Bulan kering" value={`${formatNumber(rainfall.dry_months)} bulan`} />
                            <DetailRow label="Musim kemarau" value={rainfall.dry_season ?? '—'} />
                        </div>
                        <div>
                            <DetailRow label="Risiko drainase" value={rainfall.drainage_risk ? tRisk(rainfall.drainage_risk) : '—'} />
                            <DetailRow label="Erosivitas hujan" value={rainfall.erosivity ? tRisk(rainfall.erosivity) : '—'} />
                            <DetailRow label="Bulan terbasah" value={rainfall.wettest_month ? `${rainfall.wettest_month.month} (${formatNumber(rainfall.wettest_month.rainfall_mm)} mm)` : '—'} />
                        </div>
                    </dl>
                ) : (
                    <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                        Data iklim tidak tersedia untuk titik lahan ini, sehingga pola hujan belum bisa dinilai.
                    </p>
                )}
            </div>

            {limiting.length > 0 && (
                <div className="mt-4">
                    <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Faktor pembatas lahan</h3>
                    <ul className="mt-2 flex flex-wrap gap-2">
                        {limiting.map((factor) => (
                            <li key={factor.key}>
                                <Chip label={tLimitingFactor(factor.key)} />
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Panel>
    );
}

export default function AnalysisDetailPage() {
    const { id } = useParams('/app/analisis/:id');
    const { data: analysis, error, loading, reload } = useResource((options) => analysesApi.get(id, options), [id]);

    const finished = analysis?.is_finished === true;

    // A period that was just uploaded can be opened before the model is done,
    // so the page keeps asking until the job reaches a terminal state.
    useEffect(() => {
        if (!analysis || finished) {
            return undefined;
        }

        const timer = setTimeout(reload, 3000);

        return () => clearTimeout(timer);
    }, [analysis, finished, reload]);

    if (loading) {
        return <LoadingBlock label="Memuat hasil analisis…" />;
    }

    if (error || !analysis) {
        return <ErrorPanel message={describeError(error)} onRetry={reload} />;
    }

    const intelligence = analysis.land_intelligence ?? {};
    const severity = analysis.burn_severity;
    const metrics = analysis.metrics ?? {};
    const landCover = intelligence.land_cover ?? [];
    const issues = intelligence.issues ?? [];
    const confidence = intelligence.confidence?.overall;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                        {analysis.land?.id ? (
                            <Link to={`/app/lahan/${analysis.land.id}`} className="font-semibold text-primary hover:underline">
                                {analysis.land.name ?? `Lahan #${analysis.land.id}`}
                            </Link>
                        ) : (
                            'Lahan tidak diketahui'
                        )}
                    </p>
                    <h2 className="font-headline-md text-headline-md text-on-surface">Analisis periode {formatDate(analysis.captured_at)}</h2>
                    <p className="font-body-sm text-xs text-on-surface-variant">
                        Diunggah {formatDateTime(analysis.created_at)} · sumber foto {tCaptureSource(analysis.capture_source)}
                        {analysis.image?.width ? ` · ${formatResolution(analysis.image.width, analysis.image.height)}` : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Chip
                        label={analysis.status_label}
                        color={analysis.status === 'completed' ? '#1B9E4B' : analysis.status === 'failed' ? '#D7263D' : '#0ea5e9'}
                    />
                    <Button variant="secondary" icon="refresh" onClick={reload}>
                        Muat ulang
                    </Button>
                </div>
            </div>

            {!finished && (
                <p className="rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                    {analysis.status_message} Halaman ini menyegarkan sendiri sampai analisis selesai.
                </p>
            )}

            {analysis.status === 'failed' && (
                <Panel title="Analisis gagal" icon="error">
                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                        Foto ini tidak bisa dibaca model. Unggah foto lain dari halaman lahan untuk mencoba lagi.
                    </p>
                    {analysis.error && (
                        <details className="mt-2 font-body-sm text-xs text-on-surface-variant">
                            <summary className="cursor-pointer font-semibold">Detail teknis layanan analisis</summary>
                            <pre className="mt-1 whitespace-pre-wrap break-words font-mono text-[11px]">{analysis.error}</pre>
                        </details>
                    )}
                </Panel>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <Panel title="Citra dan hasil segmentasi" icon="image" className="xl:col-span-2">
                    <SegmentationGrid
                        imageUrl={analysis.image?.url ?? null}
                        segmentation={intelligence.segmentation ?? null}
                        landCover={landCover}
                        alt={`Foto lahan periode ${formatDate(analysis.captured_at)}`}
                    />
                    {analysis.notes && (
                        <p className="mt-3 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                            {analysis.notes}
                        </p>
                    )}
                </Panel>

                <Panel title="Tingkat keparahan" icon="local_fire_department">
                    <div className="flex items-center gap-3">
                        {severity ? (
                            <Chip label={severity.label ?? tSeverity(severity.level)} color={severity.color} />
                        ) : (
                            <Chip label={tSeverity(intelligence.burn_severity?.level)} />
                        )}
                        <span className="font-headline-md text-headline-md text-on-surface">
                            {formatNumber(severity?.score ?? intelligence.burn_severity?.score)}
                            <span className="font-body-sm text-body-sm font-normal text-on-surface-variant">/100</span>
                        </span>
                    </div>

                    <dl className="mt-3">
                        <DetailRow
                            label="Keyakinan model"
                            value={severity?.confidence == null && confidence == null ? '—' : `${formatNumber((severity?.confidence ?? confidence) * 100)}%`}
                        />
                        <DetailRow label="Metode" value={tMethod(severity?.method ?? intelligence.burn_severity?.method)} />
                        <DetailRow label="Model" value={analysis.model?.name ?? '—'} hint={analysis.model?.version ? `v${analysis.model.version}` : null} />
                        <DetailRow
                            label="Tanah terbakar"
                            value={severity?.evidence?.charred_soil_pct == null ? '—' : `${formatNumber(severity.evidence.charred_soil_pct, 1)}%`}
                        />
                        <DetailRow
                            label="Tanah terbuka"
                            value={severity?.evidence?.bare_soil_pct == null ? '—' : `${formatNumber(severity.evidence.bare_soil_pct, 1)}%`}
                        />
                        <DetailRow
                            label="Vegetasi"
                            value={severity?.evidence?.vegetation_pct == null ? '—' : `${formatNumber(severity.evidence.vegetation_pct, 1)}%`}
                        />
                    </dl>

                    {(severity?.reason || severity?.method === 'colour-heuristic') && (
                        <p className="mt-3 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 font-body-sm text-xs text-on-surface">
                            Model deteksi YOLOv8 belum aktif di layanan analisis, sehingga tingkat keparahan pada foto ini dihitung
                            dari heuristik warna tutupan lahan. Angka ini perkiraan, bukan hasil detektor terlatih.
                        </p>
                    )}
                </Panel>
            </div>

            <Panel title="Metrik kondisi lahan" subtitle="Persentase dari luas yang terlihat di foto" icon="analytics">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <MetricTile
                        label="Skor kesehatan"
                        value={formatNumber(metrics.health_score)}
                        unit="/100"
                        hint={metrics.health_label}
                        tone={HEALTH_TONES[metrics.health_status] ?? 'default'}
                    />
                    <MetricTile label="Vegetasi" value={formatNumber(metrics.vegetation_percentage, 1)} unit="%" />
                    <MetricTile label="Tanah terbuka" value={formatNumber(metrics.bare_soil_percentage, 1)} unit="%" />
                    <MetricTile label="Tanah terbakar" value={formatNumber(metrics.charred_percentage, 1)} unit="%" />
                    <MetricTile label="Air" value={formatNumber(metrics.water_percentage, 1)} unit="%" />
                    <MetricTile label="Area terdegradasi" value={formatNumber(metrics.degraded_percentage, 1)} unit="%" />
                    <MetricTile label="Potensi restorasi" value={tPotential(metrics.restoration_potential)} />
                </div>
            </Panel>

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Panel title="Rincian tutupan lahan" icon="layers">
                    <ul className="space-y-3">
                        {landCover.map((entry) => (
                            <li key={entry.key} className="space-y-1">
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="font-body-sm text-body-sm text-on-surface">{tCover(entry.key)}</span>
                                    <span className="font-body-sm text-body-sm font-semibold text-on-surface">{formatNumber(entry.percentage, 1)}%</span>
                                </div>
                                <ShareBar percentage={entry.percentage} color={entry.color} />
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Masalah yang terdeteksi" icon="warning">
                    {issues.length === 0 ? (
                        <p className="font-body-sm text-body-sm text-on-surface-variant">Tidak ada masalah signifikan yang terdeteksi pada periode ini.</p>
                    ) : (
                        <ul className="space-y-3">
                            {issues.map((issue, index) => (
                                <li key={`${issue.type}-${index}`} className="rounded-lg border border-border-subtle bg-surface-container-low p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-body-sm text-body-sm font-semibold text-on-surface">{tIssue(issue.type)}</span>
                                        <Chip label={tSeverity(issue.severity)} />
                                    </div>
                                    <p className="mt-1 font-body-sm text-xs text-on-surface-variant">
                                        Luas terdampak {formatNumber(issue.affected_area, 1)}%
                                        {issue.confidence != null ? ` · keyakinan ${formatNumber(issue.confidence * 100)}%` : ''}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>

            <RecommendationList recommendation={analysis.recommendation} />

            <AgricultureContext agriculture={intelligence.agriculture} />

            <CropRanking agriculture={intelligence.agriculture} title="Peringkat tanaman untuk lahan ini" />

            <Panel title="Konteks lokasi" subtitle="Disampel untuk titik lahan ini" icon="public">
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <ContextPanel
                        title="Bentuk lahan"
                        icon="terrain"
                        block={intelligence.terrain}
                        unavailableNote="Model ketinggian (DEM) tidak bisa disampel untuk titik ini, sehingga kemiringan dan ketinggian belum dinilai."
                        rows={[
                            {
                                label: 'Ketinggian',
                                value: intelligence.terrain?.profile ? `${formatNumber(intelligence.terrain.profile.elevation_m, 1)} m` : '—',
                            },
                            {
                                label: 'Kemiringan',
                                value: intelligence.terrain?.profile ? `${formatNumber(intelligence.terrain.profile.slope_deg, 1)}°` : '—',
                            },
                            { label: 'Kelas lereng', value: intelligence.terrain?.slope_class ? tSlopeClass(intelligence.terrain.slope_class) : '—' },
                            { label: 'Arah lereng', value: intelligence.terrain?.aspect_label ? tAspect(intelligence.terrain.aspect_label) : '—' },
                        ]}
                    />
                    <ContextPanel
                        title="Tanah"
                        icon="grain"
                        block={intelligence.soil}
                        unavailableNote="Data tanah (SoilGrids) tidak bisa diambil untuk titik ini. Nilai tekstur tanah yang diisi petani tetap dipakai mesin rekomendasi."
                        rows={[
                            { label: 'Tekstur', value: intelligence.soil?.profile?.texture?.class_name ?? '—' },
                            { label: 'pH tanah', value: intelligence.soil?.profile ? formatNumber(intelligence.soil.profile.ph, 1) : '—' },
                            {
                                label: 'Karbon organik',
                                value: intelligence.soil?.profile ? `${formatNumber(intelligence.soil.profile.organic_carbon_g_kg, 1)} g/kg` : '—',
                            },
                            {
                                label: 'Nitrogen',
                                value: intelligence.soil?.profile ? `${formatNumber(intelligence.soil.profile.nitrogen_g_kg, 1)} g/kg` : '—',
                            },
                        ]}
                    />
                    <ContextPanel
                        title="Iklim"
                        icon="thermostat"
                        block={intelligence.climate}
                        unavailableNote="Data iklim (NASA POWER) tidak bisa diambil untuk titik ini. Curah hujan yang diisi petani tetap dipakai mesin rekomendasi."
                        rows={[
                            {
                                label: 'Curah hujan tahunan',
                                value: intelligence.climate?.profile ? `${formatNumber(intelligence.climate.profile.annual_rainfall_mm)} mm` : '—',
                            },
                            {
                                label: 'Suhu rata-rata',
                                value: intelligence.climate?.profile ? `${formatNumber(intelligence.climate.profile.mean_temperature_c, 1)} °C` : '—',
                            },
                            {
                                label: 'Bulan kering',
                                value: intelligence.climate?.profile ? `${formatNumber(intelligence.climate.profile.dry_months)} bulan` : '—',
                            },
                            {
                                label: 'Kelembapan tanah',
                                value:
                                    intelligence.climate?.profile?.topsoil_wetness_pct == null
                                        ? '—'
                                        : `${formatNumber(intelligence.climate.profile.topsoil_wetness_pct, 1)}%`,
                            },
                        ]}
                    />
                </div>
            </Panel>

            <Panel title="Perbandingan dengan periode sebelumnya" icon="compare_arrows">
                {analysis.progress ? (
                    <>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <MetricTile
                                label="Perubahan skor kesehatan"
                                value={formatNumber(analysis.progress.health_delta, 1)}
                                unit="poin"
                                tone={analysis.progress.health_delta >= 0 ? 'success' : 'critical'}
                            />
                            <MetricTile
                                label="Perubahan vegetasi"
                                value={formatNumber(analysis.progress.vegetation_delta, 1)}
                                unit="pp"
                                tone={analysis.progress.vegetation_delta >= 0 ? 'success' : 'critical'}
                            />
                            <MetricTile
                                label="Perubahan tanah terbuka"
                                value={formatNumber(analysis.progress.bare_soil_delta, 1)}
                                unit="pp"
                                tone={analysis.progress.bare_soil_delta <= 0 ? 'success' : 'critical'}
                            />
                        </div>
                        <p className="mt-3 font-body-sm text-body-sm text-on-surface-variant">
                            Dibanding periode{' '}
                            <Link to={`/app/analisis/${analysis.progress.compared_to.id}`} className="font-semibold text-primary hover:underline">
                                {formatDate(analysis.progress.compared_to.captured_at)}
                            </Link>
                            : arah perubahan {tDirection(analysis.progress.direction)}.
                        </p>
                    </>
                ) : (
                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                        Ini periode pertama untuk lahan ini, jadi belum ada pembanding sebelumnya.
                    </p>
                )}
            </Panel>
        </div>
    );
}
