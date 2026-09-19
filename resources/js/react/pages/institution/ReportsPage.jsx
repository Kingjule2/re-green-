/**
 * Reports & export for the B2G/B2B side (and a farmer documenting their own
 * land).
 *
 * A report is generated once and then frozen: `POST /api/v1/reports` copies the
 * evidence into a snapshot, and the PDF/CSV the user downloads render that
 * snapshot — never a fresh query. The page is built around that promise: the
 * create form warns that a new version is a new document, the detail view says
 * which version is on screen, and nothing in the list is recomputed.
 */
import React, { useState } from 'react';
import { lands as landsApi, reports } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatDate, formatDateTime, formatNumber } from '@/react/lib/format';
import EmptyState from '@/react/components/shared/EmptyState';
import ReportLandTable from '@/react/components/institution/ReportLandTable';
import Panel from '@/react/components/institution/Panel';
import SeverityMixBar from '@/react/components/institution/SeverityMixBar';

const INPUT =
    'w-full rounded-lg border border-border-subtle bg-surface-container-lowest px-3 py-2 font-body-sm text-body-sm text-on-surface outline-none focus:border-primary';
const LABEL = 'mb-1 block font-label-md text-label-md text-on-surface-variant';
const PRIMARY_BUTTON =
    'inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 font-label-md text-label-md text-on-primary transition-opacity hover:opacity-90 disabled:opacity-50';
const SECONDARY_BUTTON =
    'inline-flex items-center gap-1.5 rounded-lg border border-border-subtle px-3 py-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:bg-surface-container';
const LINK_BUTTON =
    'inline-flex items-center gap-1.5 rounded-lg border border-border-subtle px-3 py-2 font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-low';

function ScopeChip({ report }) {
    const label = report.is_global ? 'Seluruh lahan terpantau' : report.land?.name ?? 'Satu lahan';

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border-subtle bg-surface-container-low px-2.5 py-0.5 font-label-md text-label-md text-on-surface-variant">
            <span className="material-symbols-outlined text-[14px]" aria-hidden="true">
                {report.is_global ? 'public' : 'landscape'}
            </span>
            {label}
        </span>
    );
}

function SummaryFacts({ summary }) {
    if (summary == null) {
        return null;
    }

    const facts = [
        { label: 'Lahan', value: formatNumber(summary.lands) },
        { label: 'Lahan beranalisis', value: formatNumber(summary.lands_with_analysis) },
        { label: 'Luas', value: `${formatNumber(summary.area_ha, 2)} ha` },
        { label: 'Periode monitoring', value: formatNumber(summary.monitoring_periods) },
        { label: 'Skor kesehatan rata-rata', value: summary.average_health_score == null ? '—' : formatNumber(summary.average_health_score) },
        { label: 'Vegetasi rata-rata', value: summary.average_vegetation_percentage == null ? '—' : `${formatNumber(summary.average_vegetation_percentage, 1)}%` },
        { label: 'Lahan membaik', value: formatNumber(summary.improving_lands) },
        { label: 'Lahan layak karbon', value: formatNumber(summary.carbon?.eligible_lands ?? 0) },
        { label: 'Pipeline karbon', value: `${formatNumber(summary.carbon?.pipeline_tco2e_per_year ?? 0, 2)} tCO2e/tahun` },
        { label: 'Pipeline 5 tahun', value: `${formatNumber(summary.carbon?.pipeline_5yr_tco2e ?? 0, 2)} tCO2e` },
    ];

    return (
        <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {facts.map((fact) => (
                <div key={fact.label}>
                    <dt className="font-body-sm text-body-sm text-on-surface-variant">{fact.label}</dt>
                    <dd className="font-headline-sm text-headline-sm tabular-nums text-on-surface">{fact.value}</dd>
                </div>
            ))}
        </dl>
    );
}

function EvidenceList({ title, rows }) {
    return (
        <div>
            <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">{title}</h3>
            {rows.length === 0 ? (
                <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">Tidak tercatat pada snapshot ini.</p>
            ) : (
                <ul className="mt-1 space-y-1 font-body-sm text-body-sm text-on-surface">
                    {rows.map((row) => (
                        <li key={row.label} className="flex items-baseline justify-between gap-3">
                            <span>{row.label}</span>
                            <span className="tabular-nums text-on-surface-variant">{formatNumber(row.count)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function ReportDetail({ report, onClose }) {
    const evidence = report.evidence ?? {};

    return (
        <Panel
            title={report.title}
            description={`Versi v${report.version} · dibuat ${formatDateTime(report.generated_at)}${report.prepared_by ? ` oleh ${report.prepared_by}` : ''}`}
            actions={
                <>
                    <a href={reports.pdfUrl(report.id)} className={LINK_BUTTON}>
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">picture_as_pdf</span>
                        Unduh PDF
                    </a>
                    <a href={reports.csvUrl(report.id)} className={LINK_BUTTON}>
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">table_view</span>
                        Unduh CSV
                    </a>
                    <button type="button" onClick={onClose} className={SECONDARY_BUTTON}>
                        Tutup
                    </button>
                </>
            }
        >
            <div className="space-y-5">
                <p className="flex items-start gap-2 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                    <span className="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">lock_clock</span>
                    <span>
                        Snapshot ini beku: seluruh angka di bawah adalah salinan saat laporan dibuat dan tidak berubah
                        walau lahan bertambah atau analisis baru masuk. Untuk memperbarui, buat laporan versi baru.
                    </span>
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <ScopeChip report={report} />
                    <span className="font-body-sm text-body-sm text-on-surface-variant">
                        Dibuat {formatDate(report.generated_at)}
                    </span>
                </div>

                <SummaryFacts summary={report.summary} />

                <div className="grid gap-5 lg:grid-cols-2">
                    <div>
                        <h3 className="mb-2 font-headline-sm text-headline-sm text-on-surface">Sebaran keparahan</h3>
                        <SeverityMixBar mix={report.severity_mix ?? []} />
                    </div>
                    <div>
                        <h3 className="mb-2 font-headline-sm text-headline-sm text-on-surface">Keterbatasan data</h3>
                        {(report.gaps ?? []).length === 0 ? (
                            <p className="font-body-sm text-body-sm text-on-surface-variant">
                                Tidak ada keterbatasan yang tercatat pada snapshot ini.
                            </p>
                        ) : (
                            <ul className="list-disc space-y-1 pl-4 font-body-sm text-body-sm text-on-surface-variant">
                                {(report.gaps ?? []).map((gap) => (
                                    <li key={gap.key}>{gap.label}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="grid gap-5 sm:grid-cols-3">
                    <EvidenceList title="Model analisis" rows={evidence.models ?? []} />
                    <EvidenceList title="Sumber dataset" rows={evidence.datasets ?? []} />
                    <EvidenceList title="Status data" rows={evidence.data_status ?? []} />
                </div>

                <div>
                    <h3 className="mb-2 font-headline-sm text-headline-sm text-on-surface">Rincian per lahan</h3>
                    <ReportLandTable rows={report.lands ?? []} />
                </div>

                {report.disclaimer && (
                    <p className="rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                        <strong className="font-semibold text-on-surface">Disclaimer.</strong> {report.disclaimer}
                    </p>
                )}
            </div>
        </Panel>
    );
}

export default function ReportsPage() {
    const list = useResource((options) => reports.list(options));
    const landList = useResource((options) => landsApi.list(options));
    const [selectedId, setSelectedId] = useState(null);
    const detail = useResource(
        (options) => (selectedId === null ? Promise.resolve(null) : reports.get(selectedId, options)),
        [selectedId],
        { enabled: selectedId !== null },
    );
    const [form, setForm] = useState({ title: '', scope: 'all', landId: '' });
    const [creating, setCreating] = useState(false);
    const [formError, setFormError] = useState(null);
    const [deletingId, setDeletingId] = useState(null);
    const [actionError, setActionError] = useState(null);

    const landOptions = landList.data ?? [];

    const handleCreate = async (event) => {
        event.preventDefault();
        setCreating(true);
        setFormError(null);

        try {
            const payload = { scope: form.scope };
            if (form.title.trim() !== '') {
                payload.title = form.title.trim();
            }
            if (form.scope === 'land') {
                payload.land_id = Number(form.landId);
            }

            const created = await reports.create(payload);
            setForm({ title: '', scope: 'all', landId: '' });
            list.reload();
            setSelectedId(created.id);
        } catch (cause) {
            setFormError(cause);
        } finally {
            setCreating(false);
        }
    };

    const handleDelete = async (report) => {
        const confirmed = window.confirm(
            `Hapus laporan "${report.title}" (v${report.version})? Snapshot yang dihapus tidak bisa dikembalikan.`,
        );

        if (!confirmed) {
            return;
        }

        setDeletingId(report.id);
        setActionError(null);

        try {
            await reports.remove(report.id);
            if (selectedId === report.id) {
                setSelectedId(null);
            }
            list.reload();
        } catch (cause) {
            setActionError(cause.message);
        } finally {
            setDeletingId(null);
        }
    };

    const selected = selectedId === null ? null : detail.data;
    const rows = list.data ?? [];

    return (
        <div className="space-y-6">
            <header>
                <h1 className="font-headline-md text-headline-md text-on-surface">Laporan &amp; ekspor</h1>
                <p className="mt-1 max-w-3xl font-body-sm text-body-sm text-on-surface-variant">
                    Laporan dibuat sebagai snapshot: angka dibekukan saat pembuatan, lalu diunduh sebagai PDF untuk
                    keperluan pelaporan atau CSV untuk dilanjutkan di lembar kerja.
                </p>
            </header>

            <Panel
                title="Buat laporan baru"
                description="Judul boleh dikosongkan — platform memberi judul dan nomor versi otomatis."
            >
                <form onSubmit={handleCreate} className="grid gap-4 lg:grid-cols-[2fr_1fr_1fr_auto] lg:items-end">
                    <div>
                        <label className={LABEL} htmlFor="report-title">Judul laporan (opsional)</label>
                        <input
                            id="report-title"
                            type="text"
                            className={INPUT}
                            value={form.title}
                            onChange={(event) => setForm((state) => ({ ...state, title: event.target.value }))}
                            placeholder="Mis. Laporan monitoring semester I 2026"
                            maxLength={255}
                        />
                    </div>

                    <div>
                        <label className={LABEL} htmlFor="report-scope">Cakupan</label>
                        <select
                            id="report-scope"
                            className={INPUT}
                            value={form.scope}
                            onChange={(event) => setForm((state) => ({ ...state, scope: event.target.value }))}
                        >
                            <option value="all">Seluruh lahan terpantau</option>
                            <option value="land">Satu lahan</option>
                        </select>
                    </div>

                    <div>
                        <label className={LABEL} htmlFor="report-land">Lahan</label>
                        <select
                            id="report-land"
                            className={INPUT}
                            value={form.landId}
                            onChange={(event) => setForm((state) => ({ ...state, landId: event.target.value }))}
                            disabled={form.scope !== 'land'}
                            required={form.scope === 'land'}
                        >
                            <option value="">Pilih lahan…</option>
                            {landOptions.map((land) => (
                                <option key={land.id} value={land.id}>
                                    {land.name}
                                    {land.location_name ? ` — ${land.location_name}` : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <button type="submit" className={PRIMARY_BUTTON} disabled={creating}>
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">add_chart</span>
                        {creating ? 'Menyusun laporan…' : 'Buat laporan'}
                    </button>
                </form>

                {formError && (
                    <p className="mt-3 font-body-sm text-body-sm text-critical" role="alert">
                        {formError.fieldError?.('land_id') ?? formError.fieldError?.('scope') ?? formError.message}
                    </p>
                )}

                <p className="mt-3 font-body-sm text-body-sm text-on-surface-variant">
                    Setiap pembuatan menghasilkan versi baru. Versi lama tetap tersimpan apa adanya — berguna ketika
                    sebuah angka pernah diserahkan ke pihak lain.
                </p>
            </Panel>

            {detail.error && (
                <p className="font-body-sm text-body-sm text-critical" role="alert">
                    Laporan tidak bisa dibuka: {detail.error.message}
                </p>
            )}

            {selectedId !== null && selected === null && !detail.error && (
                <p className="font-body-sm text-body-sm text-on-surface-variant" role="status">
                    Memuat isi laporan…
                </p>
            )}

            {selected && <ReportDetail report={selected} onClose={() => setSelectedId(null)} />}

            {actionError && (
                <p className="font-body-sm text-body-sm text-critical" role="alert">{actionError}</p>
            )}

            <Panel
                title="Daftar laporan"
                description={
                    rows.length === 0
                        ? 'Belum ada laporan.'
                        : `${formatNumber(rows.length)} laporan terbaru untuk akun ini.`
                }
            >
                {list.loading ? (
                    <p className="font-body-sm text-body-sm text-on-surface-variant" role="status">Memuat daftar laporan…</p>
                ) : list.error ? (
                    <p className="font-body-sm text-body-sm text-critical" role="alert">
                        Daftar laporan tidak bisa dimuat: {list.error.message}
                    </p>
                ) : rows.length === 0 ? (
                    <EmptyState
                        icon="description"
                        title="Belum ada laporan"
                        description="Buat laporan pertama dari panel di atas; snapshot-nya langsung bisa diunduh sebagai PDF atau CSV."
                    />
                ) : (
                    <ul className="space-y-4">
                        {rows.map((report) => (
                            <li
                                key={report.id}
                                className={`rounded-xl border p-4 ${
                                    selectedId === report.id
                                        ? 'border-primary bg-surface-container-low'
                                        : 'border-border-subtle bg-surface-container-lowest'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 className="font-headline-sm text-headline-sm text-on-surface">{report.title}</h3>
                                        <div className="mt-1 flex flex-wrap items-center gap-2">
                                            <ScopeChip report={report} />
                                            <span className="font-body-sm text-body-sm text-on-surface-variant">
                                                v{report.version} · {formatDateTime(report.generated_at)}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() => setSelectedId(report.id)}
                                            className={SECONDARY_BUTTON}
                                        >
                                            <span className="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                            Buka
                                        </button>
                                        <a href={reports.pdfUrl(report.id)} className={LINK_BUTTON}>
                                            <span className="material-symbols-outlined text-[18px]" aria-hidden="true">picture_as_pdf</span>
                                            PDF
                                        </a>
                                        <a href={reports.csvUrl(report.id)} className={LINK_BUTTON}>
                                            <span className="material-symbols-outlined text-[18px]" aria-hidden="true">table_view</span>
                                            CSV
                                        </a>
                                        <button
                                            type="button"
                                            onClick={() => handleDelete(report)}
                                            disabled={deletingId === report.id}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-2 font-label-md text-label-md text-critical transition-colors hover:bg-rose-50 disabled:opacity-50"
                                        >
                                            <span className="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span>
                                            {deletingId === report.id ? 'Menghapus…' : 'Hapus'}
                                        </button>
                                    </div>
                                </div>

                                <div className="mt-3 grid gap-4 lg:grid-cols-[2fr_1fr]">
                                    <SummaryFacts summary={report.summary} />
                                    <SeverityMixBar mix={report.severity_mix ?? []} />
                                </div>

                                {(report.gaps ?? []).length > 0 && (
                                    <ul className="mt-3 list-disc space-y-1 pl-4 font-body-sm text-body-sm text-on-surface-variant">
                                        {(report.gaps ?? []).map((gap) => (
                                            <li key={gap.key}>{gap.label}</li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}
