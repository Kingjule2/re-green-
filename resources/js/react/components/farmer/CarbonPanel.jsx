/**
 * Carbon screening for one land, and the application form that follows it.
 *
 * Two things are kept apart on purpose. The top half is the estimate and the
 * eligibility checklist — read-only, labelled as an indicative screening, with
 * the method notice straight from the server. The bottom half is the farmer's
 * application: only the land owner sees the form, and once it is filed the panel
 * shows the filed contact details instead.
 */
import React, { useState } from 'react';
import { ApiError, carbon as carbonApi } from '@/react/lib/api';
import { formatDate, formatHectares, formatNumber } from '@/react/lib/format';
import { Button, Chip, DetailRow, Field, MetricTile, Panel, TextArea, TextInput } from '@/react/components/farmer/primitives';

const EMPTY_FORM = { partner: '', contact_name: '', contact_email: '', offered_area_ha: '', notes: '' };

function ChecklistItem({ item }) {
    const passed = item.passed === true;

    return (
        <li className="flex gap-3 py-2">
            <span className={`material-symbols-outlined text-[18px] ${passed ? 'text-success' : 'text-critical'}`} aria-hidden="true">
                {passed ? 'check_circle' : 'error'}
            </span>
            <div className="min-w-0">
                <p className="font-body-sm text-body-sm font-medium text-on-surface">{item.label}</p>
                {item.detail && <p className="font-body-sm text-xs text-on-surface-variant">{item.detail}</p>}
                {!passed && item.remedy && (
                    <p className="mt-0.5 font-body-sm text-xs text-critical">Langkah perbaikan: {item.remedy}</p>
                )}
            </div>
        </li>
    );
}

export default function CarbonPanel({ landId, carbon, canApply = false, onSubmitted = null, className = '' }) {
    const [form, setForm] = useState(EMPTY_FORM);
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [saving, setSaving] = useState(false);
    const [formOpen, setFormOpen] = useState(false);

    if (!carbon) {
        return null;
    }

    const eligibility = carbon.eligibility ?? {};
    const submission = carbon.submission ?? null;
    const passed = eligibility.passed ?? [];
    const failed = eligibility.failed ?? [];

    const update = (field) => (event) => setForm((current) => ({ ...current, [field]: event.target.value }));

    const handleSubmit = async (event) => {
        event.preventDefault();
        setErrors({});
        setMessage(null);
        setSaving(true);

        try {
            await carbonApi.submit(landId, {
                partner: form.partner,
                contact_name: form.contact_name,
                contact_email: form.contact_email,
                offered_area_ha: form.offered_area_ha === '' ? null : Number(form.offered_area_ha),
                notes: form.notes === '' ? null : form.notes,
            });
            setForm(EMPTY_FORM);
            setFormOpen(false);
            setMessage('Pengajuan tersimpan. Angka estimasi ikut diperbarui dari analisis terakhir.');
            onSubmitted?.();
        } catch (cause) {
            if (cause instanceof ApiError) {
                setErrors({
                    partner: cause.fieldError('partner'),
                    contact_name: cause.fieldError('contact_name'),
                    contact_email: cause.fieldError('contact_email'),
                    offered_area_ha: cause.fieldError('offered_area_ha'),
                    notes: cause.fieldError('notes'),
                });
                setMessage(cause.errors ? 'Periksa kembali kolom yang ditandai.' : 'Pengajuan gagal dikirim. Coba lagi.');
            } else {
                setMessage('Tidak bisa menghubungi server. Coba lagi.');
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <Panel
            title="Karbon"
            subtitle={carbon.vegetation_class_label ?? null}
            icon="eco"
            className={className}
            action={<Chip label={eligibility.label} color={eligibility.color} />}
        >
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MetricTile
                    label="Serapan per tahun"
                    value={formatNumber(carbon.sequestration_tco2e_per_year, 2)}
                    unit="tCO₂e/tahun"
                    icon="co2"
                />
                <MetricTile
                    label={`Proyeksi ${carbon.projection_years ?? 5} tahun`}
                    value={formatNumber(carbon.sequestration_5yr_tco2e, 2)}
                    unit="tCO₂e"
                    icon="timeline"
                />
                <MetricTile label="Tutupan vegetasi" value={formatNumber(carbon.vegetation_cover_pct, 1)} unit="%" icon="forest" />
                <MetricTile label="Luas dinilai" value={formatNumber(carbon.area_ha, 2)} unit="ha" icon="straighten" />
            </div>

            {carbon.basis && (
                <p className="mt-3 font-body-sm text-xs text-on-surface-variant">
                    Dasar perhitungan: {carbon.basis}
                    {carbon.method?.notice ? ` ${carbon.method.notice}` : ''}
                </p>
            )}

            <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div>
                    <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">
                        Syarat kelayakan yang terpenuhi ({passed.length})
                    </h3>
                    <ul className="mt-1 divide-y divide-border-subtle">
                        {passed.map((item) => (
                            <ChecklistItem key={item.key} item={item} />
                        ))}
                        {passed.length === 0 && (
                            <li className="py-2 font-body-sm text-body-sm text-on-surface-variant">Belum ada syarat yang terpenuhi.</li>
                        )}
                    </ul>
                </div>

                <div>
                    <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">
                        Syarat yang belum terpenuhi ({failed.length})
                    </h3>
                    <ul className="mt-1 divide-y divide-border-subtle">
                        {failed.map((item) => (
                            <ChecklistItem key={item.key} item={item} />
                        ))}
                        {failed.length === 0 && (
                            <li className="py-2 font-body-sm text-body-sm text-success">
                                Semua syarat terpenuhi untuk tahap penyaringan ini.
                            </li>
                        )}
                    </ul>
                </div>
            </div>

            <div className="mt-5 border-t border-border-subtle pt-4">
                <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Pengajuan ke mitra</h3>

                {submission ? (
                    <dl className="mt-2">
                        <div className="mb-2 flex items-center gap-2">
                            <Chip label={submission.status_label} color={submission.color} />
                        </div>
                        <DetailRow label="Mitra sertifikasi" value={submission.partner} />
                        <DetailRow label="Nama kontak" value={submission.contact_name} />
                        <DetailRow label="Email kontak" value={submission.contact_email} />
                        <DetailRow label="Luas yang diajukan" value={submission.offered_area_ha == null ? '—' : formatHectares(submission.offered_area_ha)} />
                        <DetailRow label="Waktu pengajuan" value={submission.submitted_at ? formatDate(submission.submitted_at) : '—'} />
                        {submission.notes && <DetailRow label="Catatan" value={submission.notes} />}
                    </dl>
                ) : canApply ? (
                    <>
                        <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                            Data lahan ini belum diajukan ke mitra sertifikasi. Pengajuan hanya menyimpan data di platform; verifikasi dilakukan mitra.
                        </p>
                        {message && <p className="mt-2 font-body-sm text-body-sm text-critical">{message}</p>}
                        {formOpen ? (
                            <form className="mt-4 space-y-3" onSubmit={handleSubmit}>
                                <Field label="Mitra sertifikasi / lembaga tujuan" htmlFor="carbon-partner" required error={errors.partner}>
                                    <TextInput id="carbon-partner" value={form.partner} onChange={update('partner')} placeholder="mis. Yayasan Mitra Karbon Nusantara" />
                                </Field>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <Field label="Nama kontak" htmlFor="carbon-name" required error={errors.contact_name}>
                                        <TextInput id="carbon-name" value={form.contact_name} onChange={update('contact_name')} />
                                    </Field>
                                    <Field label="Email kontak" htmlFor="carbon-email" required error={errors.contact_email}>
                                        <TextInput id="carbon-email" type="email" value={form.contact_email} onChange={update('contact_email')} />
                                    </Field>
                                </div>
                                <Field label="Luas yang diajukan (ha)" htmlFor="carbon-area" error={errors.offered_area_ha} hint="Kosongkan untuk memakai luas lahan.">
                                    <TextInput
                                        id="carbon-area"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={form.offered_area_ha}
                                        onChange={update('offered_area_ha')}
                                    />
                                </Field>
                                <Field label="Catatan" htmlFor="carbon-notes" error={errors.notes}>
                                    <TextArea id="carbon-notes" value={form.notes} onChange={update('notes')} />
                                </Field>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button type="submit" icon="send" disabled={saving}>
                                        {saving ? 'Mengirim…' : 'Ajukan ke mitra'}
                                    </Button>
                                    <Button variant="ghost" onClick={() => setFormOpen(false)}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <Button className="mt-3" icon="send" onClick={() => setFormOpen(true)}>
                                Ajukan ke mitra sertifikasi
                            </Button>
                        )}
                    </>
                ) : (
                    <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                        Pengajuan hanya bisa dilakukan oleh pemilik lahan.
                    </p>
                )}
            </div>
        </Panel>
    );
}
