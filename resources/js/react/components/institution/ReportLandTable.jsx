/**
 * The per-land rows of one frozen report.
 *
 * The shape is the one `ReportSnapshotBuilder::landRow()` writes into the
 * snapshot: identity, where the land is, the latest measured condition, the
 * before/after progress, and the carbon screening. Nothing here is recalculated
 * — a report a regulator already read must show exactly the numbers it was
 * issued with, which is why the table renders the snapshot as stored.
 */
import React from 'react';
import { Link } from '@/react/lib/router';
import { formatDate, formatHectares, formatNumber, formatPercent } from '@/react/lib/format';
import { tDirection, tSeverity } from '@/react/lib/i18n';
import StatusBadge from '@/react/components/shared/StatusBadge';
import EmptyState from '@/react/components/shared/EmptyState';

const HEAD = 'py-2 pr-4 font-label-md text-label-md uppercase tracking-wider text-on-surface-variant';
const CELL = 'py-3 pr-4 align-top';

function Progress({ progress }) {
    if (progress?.direction == null) {
        return <span className="text-on-surface-variant">—</span>;
    }

    const tone =
        progress.direction === 'improving'
            ? 'text-success'
            : progress.direction === 'declining'
              ? 'text-critical'
              : 'text-on-surface-variant';
    const icon =
        progress.direction === 'improving'
            ? 'trending_up'
            : progress.direction === 'declining'
              ? 'trending_down'
              : 'trending_flat';

    return (
        <span className={`flex items-center gap-1 ${tone}`}>
            <span className="material-symbols-outlined text-[16px]" aria-hidden="true">{icon}</span>
            {tDirection(progress.direction)}
            <span className="text-xs tabular-nums text-on-surface-variant">
                {progress.vegetation_delta == null ? '' : `${progress.vegetation_delta > 0 ? '+' : ''}${formatNumber(progress.vegetation_delta, 1)} pp`}
            </span>
        </span>
    );
}

/**
 * @param {object} props
 * @param {Array<object>} props.rows - `snapshot.lands` of a detailed report
 */
export default function ReportLandTable({ rows = [], className = '' }) {
    if (rows.length === 0) {
        return (
            <EmptyState
                icon="description"
                title="Laporan tidak memuat lahan"
                description="Snapshot ini dibuat saat belum ada lahan yang bisa dilaporkan."
                className={className}
            />
        );
    }

    return (
        <div className={`overflow-x-auto ${className}`}>
            <table className="w-full min-w-[920px] border-collapse text-left font-body-sm text-body-sm">
                <thead>
                    <tr>
                        <th scope="col" className={HEAD}>Lahan</th>
                        <th scope="col" className={HEAD}>Lokasi</th>
                        <th scope="col" className={HEAD}>Luas</th>
                        <th scope="col" className={HEAD}>Periode</th>
                        <th scope="col" className={HEAD}>Skor</th>
                        <th scope="col" className={HEAD}>Vegetasi</th>
                        <th scope="col" className={HEAD}>Keparahan</th>
                        <th scope="col" className={HEAD}>Arah progres</th>
                        <th scope="col" className={HEAD}>tCO2e/tahun</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-t border-border-subtle">
                            <th scope="row" className={`${CELL} font-normal`}>
                                <Link
                                    to={`/app/lahan/${row.id}`}
                                    className="font-medium text-primary underline-offset-2 hover:underline"
                                >
                                    {row.name}
                                </Link>
                                {row.coordinates === null && (
                                    <span className="mt-1 block text-xs text-on-surface-variant">
                                        tanpa koordinat
                                    </span>
                                )}
                            </th>
                            <td className={`${CELL} text-on-surface-variant`}>
                                <span className="flex flex-col">
                                    <span>{row.location_name ?? '—'}</span>
                                    <span className="text-xs">{row.status_label ?? row.status}</span>
                                </span>
                            </td>
                            <td className={`${CELL} tabular-nums text-on-surface-variant`}>
                                {formatHectares(row.area_ha)}
                            </td>
                            <td className={`${CELL} text-on-surface-variant`}>
                                <span className="flex flex-col">
                                    <span>{formatNumber(row.periods ?? 0)} periode</span>
                                    <span className="text-xs">
                                        {formatDate(row.progress?.first_captured_at)} – {formatDate(row.progress?.latest_captured_at)}
                                    </span>
                                </span>
                            </td>
                            <td className={CELL}>
                                {row.latest_analysis?.health_score == null ? (
                                    <span className="text-on-surface-variant">—</span>
                                ) : (
                                    <span className="font-semibold tabular-nums text-on-surface">
                                        {formatNumber(row.latest_analysis.health_score)}
                                    </span>
                                )}
                            </td>
                            <td className={`${CELL} tabular-nums text-on-surface-variant`}>
                                {formatPercent(row.latest_analysis?.vegetation_percentage, 1)}
                            </td>
                            <td className={CELL}>
                                {row.latest_analysis?.burn_severity == null ? (
                                    <span className="text-on-surface-variant">—</span>
                                ) : (
                                    <StatusBadge
                                        status={row.latest_analysis.burn_severity}
                                        label={tSeverity(
                                            row.latest_analysis.burn_severity,
                                            row.latest_analysis.burn_severity_label,
                                        )}
                                    />
                                )}
                            </td>
                            <td className={CELL}>
                                <Progress progress={row.progress} />
                            </td>
                            <td className={`${CELL} tabular-nums text-on-surface`}>
                                <span className="flex flex-col">
                                    <span>
                                        {row.carbon?.sequestration_tco2e_per_year == null
                                            ? '—'
                                            : formatNumber(row.carbon.sequestration_tco2e_per_year, 2)}
                                    </span>
                                    <span className="text-xs text-on-surface-variant">
                                        {row.carbon?.eligibility_label ?? '—'}
                                    </span>
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <p className="mt-3 font-body-sm text-body-sm text-on-surface-variant">
                Estimasi karbon pada kolom terakhir adalah hasil penyaringan awal, bukan kredit tersertifikasi.
            </p>
        </div>
    );
}
