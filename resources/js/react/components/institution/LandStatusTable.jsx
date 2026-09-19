/**
 * The land tables of the aggregate views: `top_risk_lands` on the dashboard and
 * the carbon screening rows on the portfolio.
 *
 * Both variants read the numbers the server already computed. The eligibility
 * and submission colours are the same palette the enums use
 * (CarbonEligibility::color / CarbonSubmissionStatus::color), because the
 * portfolio payload sends labels but not swatches.
 */
import React from 'react';
import { Link } from '@/react/lib/router';
import { formatDate, formatHectares, formatNumber, formatPercent } from '@/react/lib/format';
import { tSeverity } from '@/react/lib/i18n';
import StatusBadge from '@/react/components/shared/StatusBadge';
import EmptyState from '@/react/components/shared/EmptyState';

/** Mirrors CarbonEligibility::color(). */
const ELIGIBILITY_COLORS = {
    eligible: '#1B9E4B',
    not_yet: '#F9C74F',
    ineligible: '#E76F51',
};

/** Mirrors CarbonSubmissionStatus::color() and ::label(). */
const SUBMISSION = {
    not_submitted: { label: 'Belum diajukan', color: '#94a3b8' },
    submitted: { label: 'Diajukan', color: '#0ea5e9' },
    forwarded: { label: 'Diteruskan ke partner', color: '#1B9E4B' },
};

/** Mirrors CarbonCreditAssessor::VEGETATION_LABELS. */
const VEGETATION_CLASSES = {
    sparse: 'Tutupan jarang (belum layak dihitung)',
    regrowth: 'Regrowth / agroforestri muda',
    established: 'Tanaman berkayu mapan',
    mature: 'Tajuk rapat / hutan muda',
};

const HEAD = 'py-2 pr-4 font-label-md text-label-md uppercase tracking-wider text-on-surface-variant';
const CELL = 'py-3 pr-4 align-top';

function Score({ score }) {
    if (score == null) {
        return <span className="text-on-surface-variant">—</span>;
    }

    const tone = score >= 70 ? 'text-success' : score >= 45 ? 'text-warning' : 'text-critical';

    return <span className={`font-semibold tabular-nums ${tone}`}>{formatNumber(score)}</span>;
}

function LandLink({ id, name }) {
    return (
        <Link to={`/app/lahan/${id}`} className="font-medium text-primary underline-offset-2 hover:underline">
            {name}
        </Link>
    );
}

function Owner({ owner, organization, fallbackOwner, fallbackOrganization }) {
    const name = owner ?? fallbackOwner;
    const org = organization ?? fallbackOrganization;

    if (!name && !org) {
        return <span className="text-on-surface-variant">—</span>;
    }

    return (
        <span className="flex flex-col">
            <span className="text-on-surface">{name ?? '—'}</span>
            <span className="text-on-surface-variant">{org ?? 'Perorangan'}</span>
        </span>
    );
}

/**
 * @param {object} props
 * @param {'risk'|'carbon'} [props.mode]
 * @param {Array<object>} props.rows
 */
export default function LandStatusTable({ rows = [], mode = 'risk', className = '' }) {
    if (rows.length === 0) {
        return (
            <EmptyState
                icon={mode === 'carbon' ? 'eco' : 'verified_user'}
                title={mode === 'carbon' ? 'Belum ada lahan tersaring' : 'Tidak ada lahan berisiko'}
                description={
                    mode === 'carbon'
                        ? 'Portofolio muncul setelah lahan terpantau punya hasil analisis.'
                        : 'Semua lahan beranalisis berada pada kondisi sehat.'
                }
                className={className}
            />
        );
    }

    return (
        <div className={`overflow-x-auto ${className}`}>
            <table className="w-full min-w-[860px] border-collapse text-left font-body-sm text-body-sm">
                <thead>
                    <tr>
                        <th scope="col" className={HEAD}>Lahan</th>
                        <th scope="col" className={HEAD}>Lokasi</th>
                        {mode === 'risk' ? (
                            <>
                                <th scope="col" className={HEAD}>Pemilik / organisasi</th>
                                <th scope="col" className={HEAD}>Status</th>
                                <th scope="col" className={HEAD}>Skor</th>
                                <th scope="col" className={HEAD}>Vegetasi</th>
                                <th scope="col" className={HEAD}>Keparahan</th>
                                <th scope="col" className={HEAD}>Analisis</th>
                            </>
                        ) : (
                            <>
                                <th scope="col" className={HEAD}>Luas</th>
                                <th scope="col" className={HEAD}>Kelayakan</th>
                                <th scope="col" className={HEAD}>Kelas vegetasi</th>
                                <th scope="col" className={HEAD}>Estimasi</th>
                                <th scope="col" className={HEAD}>Pengajuan</th>
                                <th scope="col" className={HEAD}>Perbaikan yang dibutuhkan</th>
                            </>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => {
                        if (mode === 'carbon') {
                            const submission = SUBMISSION[row.submission_status] ?? SUBMISSION.not_submitted;
                            const failed = row.failed_checks ?? [];

                            return (
                                <tr key={row.land_id} className="border-t border-border-subtle">
                                    <th scope="row" className={`${CELL} font-normal`}>
                                        <LandLink id={row.land_id} name={row.name} />
                                    </th>
                                    <td className={`${CELL} text-on-surface-variant`}>{row.location_name ?? '—'}</td>
                                    <td className={`${CELL} tabular-nums text-on-surface-variant`}>
                                        {formatHectares(row.area_ha)}
                                    </td>
                                    <td className={CELL}>
                                        <StatusBadge
                                            status={row.eligibility_status}
                                            label={row.eligibility_label}
                                            color={ELIGIBILITY_COLORS[row.eligibility_status] ?? null}
                                        />
                                    </td>
                                    <td className={`${CELL} text-on-surface-variant`}>
                                        <span className="flex flex-col">
                                            <span>{VEGETATION_CLASSES[row.vegetation_class] ?? row.vegetation_class ?? '—'}</span>
                                            <span className="text-xs tabular-nums">
                                                Tutupan {formatPercent(row.vegetation_cover_pct, 1)}
                                            </span>
                                        </span>
                                    </td>
                                    <td className={`${CELL} tabular-nums text-on-surface`}>
                                        <span className="flex flex-col">
                                            <span className="font-semibold">
                                                {row.sequestration_tco2e_per_year == null
                                                    ? '—'
                                                    : `${formatNumber(row.sequestration_tco2e_per_year, 2)} tCO2e/tahun`}
                                            </span>
                                            <span className="text-xs text-on-surface-variant">
                                                {row.sequestration_5yr_tco2e == null
                                                    ? 'proyeksi 5 tahun belum bisa dihitung'
                                                    : `${formatNumber(row.sequestration_5yr_tco2e, 2)} tCO2e / 5 tahun`}
                                            </span>
                                        </span>
                                    </td>
                                    <td className={CELL}>
                                        <StatusBadge
                                            status={row.submission_status}
                                            label={submission.label}
                                            color={submission.color}
                                        />
                                    </td>
                                    <td className={`${CELL} max-w-[320px]`}>
                                        {failed.length === 0 ? (
                                            <span className="text-success">Semua syarat terpenuhi</span>
                                        ) : (
                                            <ul className="list-disc space-y-1 pl-4 text-on-surface-variant">
                                                {failed.map((item) => (
                                                    <li key={item}>{item}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </td>
                                </tr>
                            );
                        }

                        return (
                            <tr key={row.id} className="border-t border-border-subtle">
                                <th scope="row" className={`${CELL} font-normal`}>
                                    <LandLink id={row.id} name={row.name} />
                                </th>
                                <td className={`${CELL} text-on-surface-variant`}>{row.location_name ?? '—'}</td>
                                <td className={CELL}>
                                    <Owner
                                        owner={row.owner?.name}
                                        organization={row.owner?.organization}
                                    />
                                </td>
                                <td className={CELL}>
                                    <StatusBadge
                                        status={row.status}
                                        label={row.status_label}
                                        color={row.status_color ?? null}
                                    />
                                </td>
                                <td className={CELL}>
                                    <Score score={row.health?.score ?? row.latest_analysis?.health_score ?? null} />
                                </td>
                                <td className={`${CELL} tabular-nums text-on-surface-variant`}>
                                    {formatPercent(row.vegetation_percentage, 1)}
                                </td>
                                <td className={CELL}>
                                    {row.burn_severity == null ? (
                                        <span className="text-on-surface-variant">—</span>
                                    ) : (
                                        <StatusBadge
                                            status={row.burn_severity.level}
                                            label={tSeverity(row.burn_severity.level, row.burn_severity.label)}
                                            color={row.burn_severity.color ?? null}
                                        />
                                    )}
                                </td>
                                <td className={`${CELL} text-on-surface-variant`}>
                                    <span className="flex flex-col">
                                        <span>{formatDate(row.latest_analysis?.captured_at)}</span>
                                        <span className="text-xs">
                                            {formatNumber(row.analyses_count ?? 0)} periode
                                        </span>
                                    </span>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
