/**
 * Regional breakdown table for the aggregate (B2G/B2B) views.
 *
 * The same table serves two payloads: the dashboard's `by_region` (condition per
 * region) and the carbon portfolio's `by_region` (pipeline per region). Both
 * carry the numbers the server computed, so nothing is recomputed here — the
 * only client-side work is the tone of a row, which is a reading aid, not a
 * claim: a region where every land is eligible reads green, a region with a mix
 * reads amber, and a region with no eligible land reads red.
 */
import React from 'react';
import { formatHectares, formatNumber, formatPercent } from '@/react/lib/format';
import EmptyState from '@/react/components/shared/EmptyState';

const TONES = {
    success: { text: 'text-success', bar: '#1B9E4B', chip: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
    warning: { text: 'text-warning', bar: '#F9C74F', chip: 'bg-amber-50 text-amber-800 border-amber-200' },
    critical: { text: 'text-critical', bar: '#D7263D', chip: 'bg-rose-50 text-rose-800 border-rose-200' },
    neutral: { text: 'text-on-surface-variant', bar: '#94a3b8', chip: 'bg-surface-container text-on-surface-variant border-border-subtle' },
};

/** Health score bands, matching the scale the analysis engine reports. */
function healthTone(score) {
    if (score == null) {
        return 'neutral';
    }

    return score >= 70 ? 'success' : score >= 45 ? 'warning' : 'critical';
}

/** How much of a region's portfolio already clears the screening. */
function carbonTone(eligible, lands) {
    if (!lands) {
        return 'neutral';
    }
    if (eligible >= lands) {
        return 'success';
    }

    return eligible > 0 ? 'warning' : 'critical';
}

function ToneBar({ value, max, tone }) {
    const width = max > 0 ? Math.max(2, Math.round((value / max) * 100)) : 0;

    return (
        <div className="flex items-center gap-2">
            <span className="h-1.5 w-20 overflow-hidden rounded-full bg-surface-container" aria-hidden="true">
                <span
                    className="block h-full rounded-full"
                    style={{ width: `${width}%`, backgroundColor: TONES[tone].bar }}
                />
            </span>
            <span className="tabular-nums">{formatNumber(value, 2)}</span>
        </div>
    );
}

/**
 * @param {object} props
 * @param {Array<object>} props.rows - dashboard `by_region` or portfolio `by_region`
 * @param {'health'|'carbon'} [props.mode]
 */
export default function RegionTable({ rows = [], mode = 'health', className = '' }) {
    if (rows.length === 0) {
        return (
            <EmptyState
                icon="location_on"
                title="Belum ada wilayah terdata"
                description="Wilayah muncul setelah lahan terpantau diisi lokasinya."
                className={className}
            />
        );
    }

    const maxPipeline = Math.max(
        0.01,
        ...rows.map((row) => Number(row.pipeline_tco2e_per_year ?? 0)),
    );

    return (
        <div className={`overflow-x-auto ${className}`}>
            <table className="w-full min-w-[680px] border-collapse text-left font-body-sm text-body-sm">
                <thead>
                    <tr className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">
                        <th scope="col" className="py-2 pr-4">Wilayah</th>
                        <th scope="col" className="py-2 pr-4">Lahan</th>
                        {mode === 'carbon' && (
                            <th scope="col" className="py-2 pr-4">Layak diajukan</th>
                        )}
                        <th scope="col" className="py-2 pr-4">Luas</th>
                        {mode === 'health' ? (
                            <>
                                <th scope="col" className="py-2 pr-4">Skor kesehatan rata-rata</th>
                                <th scope="col" className="py-2">Tutupan vegetasi</th>
                            </>
                        ) : (
                            <th scope="col" className="py-2">Pipeline (tCO2e/tahun)</th>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        const region = row.region ?? row.location ?? 'Tanpa lokasi';
                        const lands = Number(row.lands ?? 0);
                        const tone =
                            mode === 'health'
                                ? healthTone(row.average_health_score)
                                : carbonTone(Number(row.eligible ?? 0), lands);

                        return (
                            <tr key={region} className="border-t border-border-subtle align-top">
                                <th scope="row" className="py-3 pr-4 font-medium text-on-surface">
                                    <span className="flex items-center gap-2">
                                        <span
                                            className="h-2 w-2 shrink-0 rounded-full"
                                            style={{ backgroundColor: TONES[tone].bar }}
                                            aria-hidden="true"
                                        />
                                        {region}
                                    </span>
                                </th>
                                <td className="py-3 pr-4 tabular-nums text-on-surface-variant">{formatNumber(lands)}</td>
                                {mode === 'carbon' && (
                                    <td className="py-3 pr-4">
                                        <span
                                            className={`inline-flex items-center rounded-full border px-2 py-0.5 font-label-md text-label-md ${TONES[tone].chip}`}
                                        >
                                            {formatNumber(Number(row.eligible ?? 0))} / {formatNumber(lands)}
                                        </span>
                                    </td>
                                )}
                                <td className="py-3 pr-4 tabular-nums text-on-surface-variant">
                                    {formatHectares(row.area_ha)}
                                </td>
                                {mode === 'health' ? (
                                    <>
                                        <td className={`py-3 pr-4 tabular-nums font-medium ${TONES[tone].text}`}>
                                            {row.average_health_score == null ? '—' : formatNumber(row.average_health_score)}
                                        </td>
                                        <td className="py-3 tabular-nums text-on-surface-variant">
                                            {formatPercent(row.vegetation_percentage, 1)}
                                        </td>
                                    </>
                                ) : (
                                    <td className="py-3 tabular-nums text-on-surface">
                                        <ToneBar
                                            value={Number(row.pipeline_tco2e_per_year ?? 0)}
                                            max={maxPipeline}
                                            tone={tone}
                                        />
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
