/**
 * One land: its identity, its monitoring history, what the analysis recommends
 * for it, and where it stands on carbon.
 *
 * The page composes three payloads: the land detail (identity, progress series,
 * timeline, carbon screening, planting plan), the newest analysis in detail (the
 * recommendation and the ranked crops live only on the detailed analysis
 * payload), and — through the before/after panel — the older period when its
 * photo is missing. Editing, deleting and crop selection are offered to the land
 * owner only, which is exactly what the server's policy allows.
 */
import React, { useState } from 'react';
import { analyses as analysesApi, lands } from '@/react/lib/api';
import { useResource } from '@/react/lib/useResource';
import { formatDate, formatHectares, formatNumber, formatPeriod } from '@/react/lib/format';
import { tCaptureSource, tDirection } from '@/react/lib/i18n';
import { navigate, useParams } from '@/react/lib/router';
import { useAuth } from '@/react/contexts/AuthContext';
import TrendChart from '@/react/components/charts/TrendChart';
import EmptyState from '@/react/components/shared/EmptyState';
import AnalysisTimeline from '@/react/components/farmer/AnalysisTimeline';
import BeforeAfterPanel from '@/react/components/farmer/BeforeAfterPanel';
import CarbonPanel from '@/react/components/farmer/CarbonPanel';
import CropRanking from '@/react/components/farmer/CropRanking';
import LandForm from '@/react/components/farmer/LandForm';
import PhotoUploader from '@/react/components/farmer/PhotoUploader';
import PlantingGuideCard from '@/react/components/farmer/PlantingGuideCard';
import RecommendationList from '@/react/components/farmer/RecommendationList';
import { Button, Chip, DetailRow, ErrorPanel, LoadingBlock, Panel } from '@/react/components/farmer/primitives';
import { describeError, tDetector, tMethod } from '@/react/components/farmer/labels';

const VEGETATION_COLOR = '#1B9E4B';
const HEALTH_COLOR = '#0288D1';

function LandHeader({ land, isOwner, onUpload, onEdit, onDelete, busy, uploadOpen, editOpen }) {
    return (
        <Panel
            title={land.name}
            subtitle={land.location_name ?? 'Lokasi belum diisi'}
            icon="landscape"
            action={
                <div className="flex flex-wrap items-center gap-2">
                    <Chip label={land.status_label} color={land.status_color} />
                    {land.data_status === 'demo' && <Chip label="Data demo" />}
                </div>
            }
        >
            <div className="grid grid-cols-1 gap-x-8 lg:grid-cols-2">
                <dl>
                    <DetailRow label="Luas lahan" value={formatHectares(land.area_ha)} />
                    <DetailRow
                        label="Pemilik"
                        value={land.owner?.name ?? '—'}
                        hint={land.owner?.organization ? `· ${land.owner.organization}` : null}
                    />
                    <DetailRow label="Tanggal kebakaran" value={land.fire_event_date ? formatDate(land.fire_event_date) : '—'} />
                    <DetailRow label="Status data" value={land.data_status === 'demo' ? 'Data demo' : 'Terukur'} />
                </dl>
                <dl>
                    <DetailRow label="Tekstur tanah" value={land.soil_texture ?? '—'} />
                    <DetailRow label="Curah hujan" value={land.rainfall_mm == null ? '—' : `${formatNumber(land.rainfall_mm)} mm/tahun`} />
                    <DetailRow
                        label="Koordinat"
                        value={land.has_coordinates ? `${formatNumber(land.latitude, 5)}, ${formatNumber(land.longitude, 5)}` : '—'}
                    />
                    <DetailRow
                        label="Tanaman terpilih"
                        value={land.selected_crop ? `${land.selected_crop.icon ?? ''} ${land.selected_crop.name}` : 'Belum dipilih'}
                    />
                </dl>
            </div>

            {land.notes && (
                <p className="mt-3 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">
                    {land.notes}
                </p>
            )}

            {isOwner && (
                <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-border-subtle pt-4">
                    <Button icon={uploadOpen ? 'close' : 'add_a_photo'} variant={uploadOpen ? 'secondary' : 'primary'} onClick={onUpload}>
                        {uploadOpen ? 'Tutup unggahan' : 'Unggah foto baru'}
                    </Button>
                    <Button icon="edit" variant="secondary" onClick={onEdit}>
                        {editOpen ? 'Tutup perubahan' : 'Ubah lahan'}
                    </Button>
                    <Button icon="delete" variant="danger" onClick={onDelete} disabled={busy}>
                        Hapus lahan
                    </Button>
                </div>
            )}
        </Panel>
    );
}

export default function LandDetailPage() {
    const { id } = useParams('/app/lahan/:id');
    const { user } = useAuth();
    const { data: land, error, loading, reload } = useResource((options) => lands.get(id, options), [id]);

    const latestId = land?.analyses?.[0]?.id ?? null;
    const { data: latest } = useResource((options) => analysesApi.get(latestId, options), [latestId], { enabled: latestId !== null });

    const [uploadOpen, setUploadOpen] = useState(false);
    const [editOpen, setEditOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [actionError, setActionError] = useState(null);
    const [selectingCropId, setSelectingCropId] = useState(null);
    const [selectedMessage, setSelectedMessage] = useState(null);

    if (loading) {
        return <LoadingBlock label="Memuat detail lahan…" />;
    }

    if (error || !land) {
        return <ErrorPanel message={describeError(error)} onRetry={reload} />;
    }

    const isOwner = user?.role === 'farmer' && land.owner?.id === user?.id;
    const progress = land.progress ?? {};
    const points = progress.points ?? [];
    const recommendation = latest?.recommendation ?? null;
    const agriculture = latest?.land_intelligence?.agriculture ?? null;

    const update = async (payload) => {
        setActionError(null);

        try {
            await lands.update(id, payload);
            setEditOpen(false);
            reload();
        } catch (cause) {
            setActionError(describeError(cause));
            throw cause;
        }
    };

    const remove = async () => {
        const confirmed = window.confirm(
            `Hapus lahan "${land.name}" beserta seluruh riwayat analisis dan fotonya? Tindakan ini tidak bisa dibatalkan.`,
        );

        if (!confirmed) {
            return;
        }

        setBusy(true);
        setActionError(null);

        try {
            await lands.remove(id);
            navigate('/app/lahan');
        } catch (cause) {
            setActionError(describeError(cause));
            setBusy(false);
        }
    };

    const selectCrop = async (crop) => {
        setSelectingCropId(crop.id);
        setActionError(null);
        setSelectedMessage(null);

        try {
            await lands.selectCrop(id, crop.id);
            setSelectedMessage(`${crop.name} dipilih. Panduan tanam di bawah memakai tanaman ini.`);
            reload();
        } catch (cause) {
            setActionError(describeError(cause));
        } finally {
            setSelectingCropId(null);
        }
    };

    return (
        <div className="space-y-6">
            <LandHeader
                land={land}
                isOwner={isOwner}
                busy={busy}
                uploadOpen={uploadOpen}
                editOpen={editOpen}
                onUpload={() => setUploadOpen((open) => !open)}
                onEdit={() => setEditOpen((open) => !open)}
                onDelete={remove}
            />

            {actionError && (
                <p className="rounded-lg border border-critical/40 bg-critical/5 px-3 py-2 font-body-sm text-body-sm text-critical">{actionError}</p>
            )}

            {isOwner && editOpen && (
                <Panel title="Ubah data lahan" subtitle="Perubahan disimpan langsung ke lahan ini" icon="edit">
                    <LandForm
                        initial={{
                            name: land.name,
                            location_name: land.location_name,
                            latitude: land.latitude,
                            longitude: land.longitude,
                            area_ha: land.area_ha,
                            fire_event_date: land.fire_event_date,
                            soil_texture: land.soil_texture,
                            rainfall_mm: land.rainfall_mm,
                            notes: land.notes,
                        }}
                        submitLabel="Simpan perubahan"
                        onSubmit={update}
                        onCancel={() => setEditOpen(false)}
                    />
                </Panel>
            )}

            {isOwner && uploadOpen && (
                <PhotoUploader landId={id} onFinished={reload} onCancel={() => setUploadOpen(false)} />
            )}

            <BeforeAfterPanel comparison={progress.comparison} />

            <Panel
                title="Grafik progres"
                subtitle={points.length > 0 ? `${formatNumber(progress.periods ?? points.length)} periode monitoring` : null}
                icon="show_chart"
            >
                <TrendChart
                    labels={points.map((point) => formatPeriod(point.captured_at))}
                    series={[
                        {
                            key: 'vegetation',
                            label: 'Tutupan vegetasi (%)',
                            color: VEGETATION_COLOR,
                            values: points.map((point) => point.vegetation_percentage),
                        },
                        {
                            key: 'health',
                            label: 'Skor kesehatan (/100)',
                            color: HEALTH_COLOR,
                            values: points.map((point) => point.land_health_score),
                            dashed: true,
                        },
                    ]}
                />
                {progress.latest?.captured_at && (
                    <p className="mt-3 font-body-sm text-xs text-on-surface-variant">
                        Kondisi terakhir {formatDate(progress.latest.captured_at)}: skor {formatNumber(progress.latest.land_health_score)}, vegetasi{' '}
                        {formatNumber(progress.latest.vegetation_percentage, 1)}%.
                        {progress.comparison?.direction && ` Arah perubahan ${tDirection(progress.comparison.direction)}.`}
                    </p>
                )}
            </Panel>

            <Panel title="Riwayat analisis" subtitle="Terbaru lebih dahulu" icon="timeline">
                <AnalysisTimeline analyses={land.analyses ?? []} />
            </Panel>

            {latest && (
                <>
                    <RecommendationList recommendation={recommendation} />

                    {selectedMessage && (
                        <p className="rounded-lg border border-success/40 bg-success/5 px-3 py-2 font-body-sm text-body-sm text-success">
                            {selectedMessage}
                        </p>
                    )}

                    <CropRanking
                        agriculture={agriculture}
                        selectedCropId={land.selected_crop?.id ?? null}
                        onSelect={isOwner ? selectCrop : null}
                        busyCropId={selectingCropId}
                    />
                </>
            )}

            {!latest && (
                <EmptyState
                    icon="insights"
                    title="Belum ada analisis"
                    description={
                        isOwner
                            ? 'Unggah foto lahan untuk mendapatkan tingkat keparahan, rekomendasi pemulihan, dan daftar tanaman yang cocok.'
                            : 'Lahan ini belum punya foto yang selesai dianalisis.'
                    }
                    action={
                        isOwner ? (
                            <Button className="mt-2" icon="add_a_photo" onClick={() => setUploadOpen(true)}>
                                Unggah foto
                            </Button>
                        ) : null
                    }
                />
            )}

            <PlantingGuideCard plantingPlan={land.planting_plan} />

            <CarbonPanel landId={id} carbon={land.carbon} canApply={isOwner} onSubmitted={reload} />

            {latest?.detections?.length > 0 && (
                <Panel title="Deteksi model pada periode terakhir" icon="visibility">
                    <ul className="flex flex-wrap gap-2">
                        {latest.detections.map((detection, index) => (
                            <li key={index}>
                                <Chip label={`${tDetector(detection.label)} · ${formatNumber(detection.confidence * 100)}%`} />
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 font-body-sm text-xs text-on-surface-variant">
                        Cara pembacaan: {tMethod(latest.burn_severity?.method)} · sumber foto {tCaptureSource(latest.capture_source)}
                    </p>
                </Panel>
            )}
        </div>
    );
}
