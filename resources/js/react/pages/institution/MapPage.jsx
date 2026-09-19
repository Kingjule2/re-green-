/**
 * The spatial view of the portfolio.
 *
 * `GET /api/v1/map/lands` returns one point per land that actually has
 * coordinates, plus `meta.without_coordinates` for the lands it had to leave
 * out. The page shows both: the pins a stakeholder can click, and the honest
 * count of lands that cannot be mapped yet because nobody filled in a GPS
 * point — a number that would otherwise quietly disappear from a "6 lahan"
 * headline.
 */
import React, { useCallback, useMemo, useState } from 'react';
import { Link } from '@/react/lib/router';
import { overview } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatDateTime, formatHectares, formatNumber, formatPercent } from '@/react/lib/format';
import EmptyState from '@/react/components/shared/EmptyState';
import StatusBadge from '@/react/components/shared/StatusBadge';
import FieldMap from '@/react/components/map/FieldMap';
import Panel from '@/react/components/institution/Panel';

/** Pin colours by land status, mirroring LandStatus::color(). */
const STATUS_COLORS = {
    planned: '#94a3b8',
    monitoring: '#0ea5e9',
    restored: '#1B9E4B',
};

export default function MapPage() {
    const { data, error, loading, reload } = useResource((options) => overview.map(options));
    const [selectedId, setSelectedId] = useState(null);
    const [focus, setFocus] = useState(null);

    const points = useMemo(
        () =>
            (data ?? []).map((row) => ({
                id: row.id,
                latitude: row.latitude,
                longitude: row.longitude,
                label: `${row.name} — ${row.location_name ?? 'tanpa lokasi'}`,
                tone: STATUS_COLORS[row.status] ?? null,
            })),
        [data],
    );

    const handleSelect = useCallback((id) => {
        setSelectedId(id);
    }, []);

    if (loading) {
        return (
            <p className="font-body-sm text-body-sm text-on-surface-variant" role="status">
                Memuat titik lahan…
            </p>
        );
    }

    if (error) {
        return (
            <EmptyState
                icon="cloud_off"
                title="Peta tidak bisa dimuat"
                description={error.message}
                action={
                    <button type="button" onClick={reload} className="mt-2 rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary">
                        Coba lagi
                    </button>
                }
            />
        );
    }

    const rows = data ?? [];
    const withoutCoordinates = rows.meta?.without_coordinates ?? 0;
    const totalArea = rows.meta?.area_ha ?? null;

    return (
        <div className="space-y-4">
            <header className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="font-headline-md text-headline-md text-on-surface">Peta sebaran lahan</h1>
                    <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                        {formatNumber(rows.length)} lahan berkoordinat
                        {totalArea == null ? '' : ` seluas ${formatHectares(totalArea)}`} siap dipetakan dan disampling
                        datanya.
                    </p>
                </div>
                <ul className="flex flex-wrap items-center gap-3 font-body-sm text-body-sm text-on-surface-variant">
                    {Object.entries(STATUS_COLORS).map(([status, color]) => (
                        <li key={status} className="flex items-center gap-1.5">
                            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} aria-hidden="true" />
                            {rows.find((row) => row.status === status)?.status_label ?? status}
                        </li>
                    ))}
                </ul>
            </header>

            {withoutCoordinates > 0 && (
                <p
                    className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 font-body-sm text-body-sm text-amber-900"
                    role="status"
                >
                    <span className="material-symbols-outlined text-[20px]" aria-hidden="true">warning</span>
                    <span>
                        <strong className="font-semibold">{formatNumber(withoutCoordinates)} lahan belum punya koordinat</strong>{' '}
                        dan tidak bisa dipetakan. Lengkapi titik lahan agar ikut terpantau secara spasial — tanpa koordinat,
                        lahan juga tidak bisa disampling data wilayahnya.
                    </span>
                </p>
            )}

            <div className="grid gap-4 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    {rows.length === 0 ? (
                        <EmptyState
                            icon="location_off"
                            title="Belum ada lahan berkoordinat"
                            description="Titik peta muncul setelah pemilik lahan mengisi koordinat saat mendaftarkan atau menganalisis lahan."
                        />
                    ) : (
                        <FieldMap
                            points={points}
                            selectedId={selectedId}
                            onSelect={handleSelect}
                            focus={focus}
                            className="h-[560px]"
                        />
                    )}
                    <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                        Klik penanda untuk memilih lahan, atau klik peta untuk membaca elevasi titik tersebut.
                    </p>
                </div>

                <Panel
                    title="Lahan berkoordinat"
                    description="Pilih lahan untuk menyorot penandanya, lalu fokuskan peta ke titik itu."
                    bodyClassName="max-h-[560px] space-y-3 overflow-y-auto pr-1"
                >
                    {rows.length === 0 ? (
                        <p className="font-body-sm text-body-sm text-on-surface-variant">
                            Belum ada baris untuk ditampilkan.
                        </p>
                    ) : (
                        rows.map((row) => {
                            const isSelected = row.id === selectedId;

                            return (
                                <article
                                    key={row.id}
                                    className={`rounded-xl border p-3 transition-colors ${
                                        isSelected
                                            ? 'border-primary bg-surface-container-low'
                                            : 'border-border-subtle bg-surface-container-lowest'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Link
                                                to={`/app/lahan/${row.id}`}
                                                className="font-headline-sm text-headline-sm text-primary underline-offset-2 hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                            <p className="font-body-sm text-body-sm text-on-surface-variant">
                                                {row.owner_name ?? 'Pemilik tidak tercatat'}
                                                {row.organization ? ` · ${row.organization}` : ''}
                                            </p>
                                        </div>
                                        <StatusBadge status={row.status} label={row.status_label} color={row.status_color ?? null} />
                                    </div>

                                    <dl className="mt-2 grid grid-cols-3 gap-2 font-body-sm text-body-sm">
                                        <div>
                                            <dt className="text-on-surface-variant">Skor</dt>
                                            <dd className="font-semibold tabular-nums text-on-surface">
                                                {row.health_score == null ? '—' : formatNumber(row.health_score)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-on-surface-variant">Vegetasi</dt>
                                            <dd className="tabular-nums text-on-surface">
                                                {formatPercent(row.vegetation_percentage, 1)}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-on-surface-variant">Luas</dt>
                                            <dd className="tabular-nums text-on-surface">{formatHectares(row.area_ha)}</dd>
                                        </div>
                                    </dl>

                                    <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">
                                        Analisis terakhir {formatDateTime(row.latest_analysis_at)} ·{' '}
                                        {formatNumber(row.analyses_count ?? 0)} periode
                                    </p>

                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        <span className="font-body-sm text-body-sm text-on-surface-variant">
                                            {row.location_name ?? 'Lokasi belum diisi'}
                                        </span>
                                        <span className="ml-auto flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => setSelectedId(row.id)}
                                                className="rounded-lg border border-border-subtle px-2.5 py-1 font-label-md text-label-md text-on-surface-variant transition-colors hover:bg-surface-container"
                                            >
                                                Pilih
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setSelectedId(row.id);
                                                    setFocus([row.latitude, row.longitude]);
                                                }}
                                                className="inline-flex items-center gap-1 rounded-lg bg-primary px-2.5 py-1 font-label-md text-label-md text-on-primary"
                                            >
                                                <span className="material-symbols-outlined text-[16px]" aria-hidden="true">my_location</span>
                                                Fokus
                                            </button>
                                        </span>
                                    </div>
                                </article>
                            );
                        })
                    )}
                </Panel>
            </div>
        </div>
    );
}
