import React, { useEffect, useRef, useState } from 'react';
import { formatBytes, formatResolution } from '../../lib/format';

const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'tif', 'tiff'];
const MAX_BYTES = 50 * 1024 * 1024;

const EMPTY_METADATA = {
    latitude: '',
    longitude: '',
    area_name: '',
    survey_date: '',
    drone_model: '',
    flight_altitude: '',
    image_type: '',
};

function extensionOf(name) {
    return String(name).split('.').pop()?.toLowerCase() ?? '';
}

/**
 * Drone image upload surface: drag-and-drop / browse, client-side validation,
 * a preview with file details, and optional survey metadata.
 */
export default function DroneUploader({ onAnalyze, submitting = false, error = null, onViewHistory }) {
    const [file, setFile] = useState(null);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [dimensions, setDimensions] = useState({ width: null, height: null });
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState(null);
    const [showMetadata, setShowMetadata] = useState(false);
    const [metadata, setMetadata] = useState(EMPTY_METADATA);
    const inputRef = useRef(null);

    useEffect(() => {
        // Release the object URL when it changes or the component unmounts.
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const acceptSelection = (selected) => {
        if (!selected) {
            return;
        }
        if (!ACCEPTED_EXTENSIONS.includes(extensionOf(selected.name))) {
            setLocalError('This image format is not supported. Use JPG, PNG, or TIFF.');
            return;
        }
        if (selected.size > MAX_BYTES) {
            setLocalError('Maximum file size is 50 MB.');
            return;
        }

        setLocalError(null);
        setDimensions({ width: null, height: null });

        const url = URL.createObjectURL(selected);
        setPreviewUrl(url);
        setFile(selected);

        const probe = new Image();
        probe.onload = () => setDimensions({ width: probe.naturalWidth, height: probe.naturalHeight });
        probe.src = url;
    };

    const handleDrop = (event) => {
        event.preventDefault();
        setDragging(false);
        acceptSelection(event.dataTransfer.files?.[0]);
    };

    const replaceImage = () => {
        setFile(null);
        setPreviewUrl(null);
        setDimensions({ width: null, height: null });
        inputRef.current?.click();
    };

    const updateMetadata = (field) => (event) => {
        setMetadata((prev) => ({ ...prev, [field]: event.target.value }));
    };

    const submit = () => {
        if (!file) {
            return;
        }
        const payload = Object.fromEntries(
            Object.entries(metadata).filter(([, value]) => String(value).trim() !== ''),
        );
        onAnalyze(file, payload);
    };

    const message = error || localError;

    return (
        <div className="space-y-6">
            {!file ? (
                <label
                    htmlFor="drone-file-input"
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={handleDrop}
                    className={`flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed p-10 md:p-16 text-center transition-colors cursor-pointer ${
                        dragging
                            ? 'border-primary bg-primary/5'
                            : 'border-outline-variant bg-surface-container-lowest hover:border-primary/50 hover:bg-surface-container-low'
                    }`}
                >
                    <span className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-container/10 text-primary dark:text-primary-fixed">
                        <span className="material-symbols-outlined text-[34px]" aria-hidden="true">
                            flight_takeoff
                        </span>
                    </span>
                    <div>
                        <h3 className="font-headline-md text-headline-sm font-bold text-on-surface">Upload Drone Image</h3>
                        <p className="mt-1 max-w-md font-body-sm text-sm text-on-surface-variant">
                            Drag and drop your aerial image here or browse from your device.
                        </p>
                    </div>
                    <span className="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2.5 font-label-md text-xs font-semibold text-white shadow-sm">
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                            folder_open
                        </span>
                        Browse Files
                    </span>
                    <p className="font-label-md text-[11px] uppercase tracking-wider text-outline">
                        JPG · PNG · TIFF · up to 50 MB · nadir imagery recommended
                    </p>
                    <input
                        id="drone-file-input"
                        ref={inputRef}
                        type="file"
                        accept=".jpg,.jpeg,.png,.tif,.tiff,image/*"
                        className="sr-only"
                        onChange={(event) => acceptSelection(event.target.files?.[0])}
                    />
                </label>
            ) : (
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                    {/* Preview */}
                    <div className="lg:col-span-3 overflow-hidden rounded-xl border border-border-subtle bg-surface-container-lowest card-shadow">
                        <div className="aspect-video w-full overflow-hidden bg-surface-container-low">
                            {previewUrl && (
                                <img src={previewUrl} alt={`Preview of ${file.name}`} className="h-full w-full object-cover" />
                            )}
                        </div>
                    </div>

                    {/* Details + actions */}
                    <div className="lg:col-span-2 flex flex-col rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow">
                        <h3 className="truncate font-headline-md text-headline-sm font-bold text-on-surface" title={file.name}>
                            {file.name}
                        </h3>
                        <dl className="mt-4 space-y-3 text-sm">
                            <DetailRow label="Resolution" value={formatResolution(dimensions.width, dimensions.height)} />
                            <DetailRow label="File Size" value={formatBytes(file.size)} />
                            <DetailRow
                                label="Status"
                                value={
                                    <span className="inline-flex items-center gap-1.5 font-semibold text-success">
                                        <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                                            check_circle
                                        </span>
                                        Ready for Analysis
                                    </span>
                                }
                            />
                        </dl>

                        <div className="mt-auto flex flex-col gap-2 pt-6">
                            <button
                                type="button"
                                onClick={submit}
                                disabled={submitting}
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-container px-4 py-2.5 font-label-md text-xs font-bold text-white shadow-sm transition-colors hover:bg-primary disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                                    {submitting ? 'progress_activity' : 'neurology'}
                                </span>
                                {submitting ? 'Starting…' : 'Start AI Analysis'}
                            </button>
                            <button
                                type="button"
                                onClick={replaceImage}
                                disabled={submitting}
                                className="inline-flex items-center justify-center gap-2 rounded-lg border border-border-subtle px-4 py-2.5 font-label-md text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container disabled:opacity-60"
                            >
                                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                                    swap_horiz
                                </span>
                                Replace Image
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {message && (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800/40 dark:bg-rose-950/40 dark:text-rose-300"
                >
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        error
                    </span>
                    <span>{message}</span>
                </div>
            )}

            {/* Optional metadata */}
            {file && (
                <div className="rounded-xl border border-border-subtle bg-surface-container-lowest">
                    <button
                        type="button"
                        onClick={() => setShowMetadata((open) => !open)}
                        aria-expanded={showMetadata}
                        className="flex w-full items-center justify-between px-5 py-4 text-left"
                    >
                        <span className="flex items-center gap-2 font-headline-md text-sm font-bold text-on-surface">
                            <span className="material-symbols-outlined text-[18px] text-outline" aria-hidden="true">
                                tune
                            </span>
                            Survey Metadata
                            <span className="font-label-md text-[10px] uppercase tracking-wider text-outline">Optional</span>
                        </span>
                        <span className="material-symbols-outlined text-outline" aria-hidden="true">
                            {showMetadata ? 'expand_less' : 'expand_more'}
                        </span>
                    </button>

                    {showMetadata && (
                        <div className="space-y-5 border-t border-border-subtle px-5 py-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <Field label="Latitude">
                                    <input type="number" step="any" value={metadata.latitude} onChange={updateMetadata('latitude')} placeholder="-6.4025" className={inputClass} />
                                </Field>
                                <Field label="Longitude">
                                    <input type="number" step="any" value={metadata.longitude} onChange={updateMetadata('longitude')} placeholder="106.7942" className={inputClass} />
                                </Field>
                                <Field label="Area Name">
                                    <input type="text" value={metadata.area_name} onChange={updateMetadata('area_name')} placeholder="Sector Alpha" className={inputClass} />
                                </Field>
                            </div>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Field label="Survey Date">
                                    <input type="date" value={metadata.survey_date} onChange={updateMetadata('survey_date')} className={inputClass} />
                                </Field>
                                <Field label="Drone Model">
                                    <input type="text" value={metadata.drone_model} onChange={updateMetadata('drone_model')} placeholder="DJI Mavic 3M" className={inputClass} />
                                </Field>
                                <Field label="Flight Altitude">
                                    <input type="text" value={metadata.flight_altitude} onChange={updateMetadata('flight_altitude')} placeholder="120 m" className={inputClass} />
                                </Field>
                                <Field label="Image Type">
                                    <input type="text" value={metadata.image_type} onChange={updateMetadata('image_type')} placeholder="RGB / Nadir" className={inputClass} />
                                </Field>
                            </div>
                            <p className="flex items-start gap-2 font-body-sm text-xs text-outline">
                                <span className="material-symbols-outlined text-[15px]" aria-hidden="true">
                                    info
                                </span>
                                Without reliable GPS metadata, analysis still runs but results are not presented as accurate
                                geographic measurements.
                            </p>
                        </div>
                    )}
                </div>
            )}

            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={onViewHistory}
                    className="inline-flex items-center gap-1.5 font-label-md text-xs font-semibold text-primary hover:underline"
                >
                    <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                        history
                    </span>
                    View Analysis History
                </button>
            </div>
        </div>
    );
}

const inputClass =
    'w-full rounded-lg border border-border-subtle bg-surface-container-lowest px-3 py-2 text-sm text-on-surface outline-none transition-colors focus:border-primary';

function Field({ label, children }) {
    return (
        <label className="block">
            <span className="mb-1 block font-label-md text-[11px] uppercase tracking-wider text-outline">{label}</span>
            {children}
        </label>
    );
}

function DetailRow({ label, value }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-border-subtle pb-2 last:border-0">
            <dt className="font-label-md text-[11px] uppercase tracking-wider text-outline">{label}</dt>
            <dd className="text-right font-semibold text-on-surface">{value}</dd>
        </div>
    );
}
