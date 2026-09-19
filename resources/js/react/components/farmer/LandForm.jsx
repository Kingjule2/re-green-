/**
 * The land form, used both to register a new land and to edit an existing one.
 *
 * The declared fields are not decoration: soil texture and annual rainfall are
 * exactly the inputs the crop engine falls back to when no dataset could be
 * sampled for the land's coordinates, so the form says so where it matters. The
 * server's validation messages are shown under the field they belong to.
 */
import React, { useState } from 'react';
import { ApiError } from '@/react/lib/api';
import { SOIL_TEXTURES } from '@/react/lib/i18n';
import { Button, Field, SelectInput, TextArea, TextInput } from '@/react/components/farmer/primitives';

const EMPTY = {
    name: '',
    location_name: '',
    latitude: '',
    longitude: '',
    area_ha: '',
    fire_event_date: '',
    soil_texture: '',
    rainfall_mm: '',
    notes: '',
};

function toFormState(initial) {
    if (!initial) {
        return EMPTY;
    }

    return {
        name: initial.name ?? '',
        location_name: initial.location_name ?? '',
        latitude: initial.latitude ?? '',
        longitude: initial.longitude ?? '',
        area_ha: initial.area_ha ?? '',
        fire_event_date: initial.fire_event_date ?? '',
        soil_texture: initial.soil_texture ?? '',
        rainfall_mm: initial.rainfall_mm ?? '',
        notes: initial.notes ?? '',
    };
}

const numberOrNull = (value) => (value === '' || value === null ? null : Number(value));
const textOrNull = (value) => (value === '' ? null : value);

const SOIL_OPTIONS = SOIL_TEXTURES.map((texture) => ({ value: texture, label: texture }));

export default function LandForm({ initial = null, submitLabel = 'Simpan lahan', onSubmit, onCancel = null, className = '' }) {
    const [values, setValues] = useState(() => toFormState(initial));
    const [errors, setErrors] = useState({});
    const [message, setMessage] = useState(null);
    const [saving, setSaving] = useState(false);

    const update = (field) => (event) => setValues((current) => ({ ...current, [field]: event.target.value }));

    const handleSubmit = async (event) => {
        event.preventDefault();
        setErrors({});
        setMessage(null);
        setSaving(true);

        try {
            await onSubmit({
                name: values.name.trim(),
                location_name: textOrNull(values.location_name.trim()),
                latitude: numberOrNull(values.latitude),
                longitude: numberOrNull(values.longitude),
                area_ha: numberOrNull(values.area_ha),
                fire_event_date: textOrNull(values.fire_event_date),
                soil_texture: textOrNull(values.soil_texture),
                rainfall_mm: numberOrNull(values.rainfall_mm),
                notes: textOrNull(values.notes.trim()),
            });
        } catch (cause) {
            if (cause instanceof ApiError) {
                setErrors({
                    name: cause.fieldError('name'),
                    location_name: cause.fieldError('location_name'),
                    latitude: cause.fieldError('latitude'),
                    longitude: cause.fieldError('longitude'),
                    area_ha: cause.fieldError('area_ha'),
                    fire_event_date: cause.fieldError('fire_event_date'),
                    soil_texture: cause.fieldError('soil_texture'),
                    rainfall_mm: cause.fieldError('rainfall_mm'),
                    notes: cause.fieldError('notes'),
                });
                setMessage(cause.errors ? 'Periksa kembali kolom yang ditandai.' : 'Data lahan gagal disimpan. Coba lagi.');
            } else {
                setMessage('Tidak bisa menghubungi server. Coba lagi.');
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <form className={`space-y-4 ${className}`} onSubmit={handleSubmit}>
            {message && (
                <p className="rounded-lg border border-critical/40 bg-critical/5 px-3 py-2 font-body-sm text-body-sm text-critical">{message}</p>
            )}

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Field label="Nama lahan" htmlFor="land-name" required error={errors.name} className="sm:col-span-2">
                    <TextInput id="land-name" value={values.name} onChange={update('name')} placeholder="mis. Lahan Sungai Keruh" />
                </Field>

                <Field label="Lokasi (kabupaten/kota, provinsi)" htmlFor="land-location" error={errors.location_name} className="sm:col-span-2">
                    <TextInput id="land-location" value={values.location_name} onChange={update('location_name')} />
                </Field>

                <Field label="Latitude" htmlFor="land-lat" error={errors.latitude} hint="Boleh dikosongkan bila belum ada titik pasti.">
                    <TextInput id="land-lat" type="number" step="0.000001" min="-90" max="90" value={values.latitude} onChange={update('latitude')} />
                </Field>

                <Field label="Longitude" htmlFor="land-lng" error={errors.longitude}>
                    <TextInput
                        id="land-lng"
                        type="number"
                        step="0.000001"
                        min="-180"
                        max="180"
                        value={values.longitude}
                        onChange={update('longitude')}
                    />
                </Field>

                <Field label="Luas lahan (ha)" htmlFor="land-area" error={errors.area_ha}>
                    <TextInput id="land-area" type="number" step="0.01" min="0" value={values.area_ha} onChange={update('area_ha')} />
                </Field>

                <Field label="Tanggal kebakaran" htmlFor="land-fire" error={errors.fire_event_date}>
                    <TextInput id="land-fire" type="date" value={values.fire_event_date} onChange={update('fire_event_date')} />
                </Field>

                <Field
                    label="Tekstur tanah"
                    htmlFor="land-soil"
                    error={errors.soil_texture}
                    hint="Dipakai mesin rekomendasi saat data tanah tidak bisa disampel."
                >
                    <SelectInput
                        id="land-soil"
                        options={SOIL_OPTIONS}
                        placeholder="Belum diisi"
                        value={values.soil_texture}
                        onChange={update('soil_texture')}
                    />
                </Field>

                <Field
                    label="Curah hujan (mm/tahun)"
                    htmlFor="land-rainfall"
                    error={errors.rainfall_mm}
                    hint="Dipakai mesin rekomendasi saat data iklim tidak bisa disampel."
                >
                    <TextInput
                        id="land-rainfall"
                        type="number"
                        step="1"
                        min="0"
                        max="10000"
                        value={values.rainfall_mm}
                        onChange={update('rainfall_mm')}
                    />
                </Field>

                <Field label="Catatan" htmlFor="land-notes" error={errors.notes} className="sm:col-span-2">
                    <TextArea id="land-notes" value={values.notes} onChange={update('notes')} placeholder="Kondisi awal, akses, atau riwayat kebakaran." />
                </Field>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Button type="submit" icon="save" disabled={saving || values.name.trim() === ''}>
                    {saving ? 'Menyimpan…' : submitLabel}
                </Button>
                {onCancel && (
                    <Button variant="ghost" onClick={onCancel} disabled={saving}>
                        Batal
                    </Button>
                )}
            </div>
        </form>
    );
}
