/**
 * The land list: one card per land, and the form that registers a new one.
 *
 * The endpoint already scopes the list — a farmer sees their own lands, a
 * pemda/NGO or corporate account sees every monitored land — so the only thing
 * the page changes by role is whether the owner is named on the card and whether
 * the registration form is offered at all.
 */
import React, { useState } from 'react';
import { lands } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatDate, formatHectares, formatNumber } from '@/react/lib/format';
import { Link, navigate } from '@/react/lib/router';
import { useAuth } from '@/react/contexts/AuthContext';
import EmptyState from '@/react/components/shared/EmptyState';
import { Button, Chip, ErrorPanel, LoadingBlock, Panel } from '@/react/components/farmer/primitives';
import { describeError } from '@/react/components/farmer/labels';
import LandForm from '@/react/components/farmer/LandForm';

function LandCard({ land, showOwner }) {
    const health = land.health;
    const severity = land.burn_severity;

    return (
        <li className="rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow transition-colors hover:border-primary/30">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link to={`/app/lahan/${land.id}`} className="font-headline-sm text-headline-sm text-on-surface hover:text-primary">
                        {land.name}
                    </Link>
                    <p className="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">
                        {land.location_name ?? 'Lokasi belum diisi'}
                    </p>
                </div>
                <Chip label={land.status_label} color={land.status_color} />
            </div>

            <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 font-body-sm text-body-sm">
                <div>
                    <dt className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Luas</dt>
                    <dd className="text-on-surface">{formatHectares(land.area_ha)}</dd>
                </div>
                <div>
                    <dt className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Periode monitoring</dt>
                    <dd className="text-on-surface">{formatNumber(land.analyses_count)}</dd>
                </div>
                <div>
                    <dt className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Skor kesehatan</dt>
                    <dd className="text-on-surface">
                        {health?.score == null ? '—' : `${formatNumber(health.score)}/100`}
                        {health?.label && <span className="ml-1 text-on-surface-variant">{health.label}</span>}
                    </dd>
                </div>
                <div>
                    <dt className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Vegetasi</dt>
                    <dd className="text-on-surface">{formatNumber(land.vegetation_percentage, 1)}%</dd>
                </div>
            </dl>

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border-subtle pt-3">
                <div className="flex flex-wrap items-center gap-2">
                    {severity && <Chip label={severity.label} color={severity.color} />}
                    {land.selected_crop && (
                        <span className="inline-flex items-center gap-1 font-body-sm text-xs text-on-surface-variant">
                            <span aria-hidden="true">{land.selected_crop.icon}</span>
                            {land.selected_crop.name}
                        </span>
                    )}
                </div>
                <span className="font-body-sm text-xs text-on-surface-variant">
                    {land.fire_event_date ? `Terbakar ${formatDate(land.fire_event_date)}` : 'Tanggal kebakaran belum diisi'}
                </span>
            </div>

            {showOwner && (
                <p className="mt-2 font-body-sm text-xs text-on-surface-variant">
                    Pemilik: <span className="font-medium text-on-surface">{land.owner?.name ?? '—'}</span>
                    {land.owner?.organization ? ` · ${land.owner.organization}` : ''}
                </p>
            )}

            <div className="mt-4">
                <Link
                    to={`/app/lahan/${land.id}`}
                    className="inline-flex items-center gap-1 font-body-sm text-body-sm font-semibold text-primary hover:underline"
                >
                    Buka detail lahan
                    <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                        arrow_forward
                    </span>
                </Link>
            </div>
        </li>
    );
}

export default function LandsPage() {
    const { viewsAllLands, canManageLands } = useAuth();
    const { data, error, loading, reload } = useResource((options) => lands.list(options), []);
    const [formOpen, setFormOpen] = useState(false);

    const rows = Array.isArray(data) ? data : [];

    const create = async (payload) => {
        const created = await lands.create(payload);

        if (created?.id) {
            navigate(`/app/lahan/${created.id}`);

            return;
        }

        setFormOpen(false);
        reload();
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="font-headline-md text-headline-md text-on-surface">
                        {viewsAllLands ? 'Lahan terpantau' : 'Lahan saya'}
                    </h2>
                    <p className="font-body-sm text-body-sm text-on-surface-variant">
                        {loading ? 'Memuat daftar lahan…' : `${formatNumber(rows.length)} lahan terdaftar.`}
                    </p>
                </div>

                {canManageLands && (
                    <Button icon={formOpen ? 'close' : 'add'} variant={formOpen ? 'secondary' : 'primary'} onClick={() => setFormOpen((open) => !open)}>
                        {formOpen ? 'Tutup formulir' : 'Tambah lahan'}
                    </Button>
                )}
            </div>

            {formOpen && canManageLands && (
                <Panel title="Daftarkan lahan baru" subtitle="Isi data dasar lahan; foto pertama diunggah setelah lahan tersimpan." icon="landscape">
                    <LandForm submitLabel="Simpan lahan" onSubmit={create} onCancel={() => setFormOpen(false)} />
                </Panel>
            )}

            {loading && <LoadingBlock label="Memuat daftar lahan…" />}

            {!loading && error && <ErrorPanel message={describeError(error)} onRetry={reload} />}

            {!loading && !error && rows.length === 0 && (
                <EmptyState
                    icon="landscape"
                    title="Belum ada lahan"
                    description={
                        canManageLands
                            ? 'Daftarkan lahan bekas kebakaran yang akan dipantau, lengkap dengan tekstur tanah dan curah hujannya.'
                            : 'Belum ada lahan yang bisa dibaca akun ini.'
                    }
                    action={
                        canManageLands && !formOpen ? (
                            <Button className="mt-2" icon="add" onClick={() => setFormOpen(true)}>
                                Daftarkan lahan
                            </Button>
                        ) : null
                    }
                />
            )}

            {rows.length > 0 && (
                <ul className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    {rows.map((land) => (
                        <LandCard key={land.id} land={land} showOwner={viewsAllLands} />
                    ))}
                </ul>
            )}
        </div>
    );
}
