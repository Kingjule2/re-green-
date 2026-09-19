/**
 * Upload one land photo and follow its analysis to the end.
 *
 * The upload returns as soon as the photo is stored; the model runs in the
 * background, so the component polls the analysis every `ANALYSIS_POLL_MS`
 * until the server says the job is finished — and shows the server's own status
 * message while it waits, rather than a fake progress bar.
 *
 * The file is kept in state after a failure so the retry button resends the same
 * photo instead of asking the farmer to pick it again.
 */
import React, { useEffect, useRef, useState } from 'react';
import { ANALYSIS_POLL_MS, analyses } from '@/react/lib/api';
import { formatBytes, formatDate } from '@/react/lib/format';
import { CAPTURE_SOURCE_LABELS } from '@/react/lib/i18n';
import { Link } from '@/react/lib/router';
import { describeError } from '@/react/components/farmer/labels';
import { Button, Chip, Field, Panel, SelectInput, TextArea, TextInput } from '@/react/components/farmer/primitives';

const CAPTURE_OPTIONS = Object.entries(CAPTURE_SOURCE_LABELS).map(([value, label]) => ({ value, label }));

const todayIso = () => new Date().toISOString().slice(0, 10);

export default function PhotoUploader({ landId, onFinished = null, onCancel = null, className = '' }) {
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [capturedAt, setCapturedAt] = useState(todayIso);
    const [captureSource, setCaptureSource] = useState('phone');
    const [notes, setNotes] = useState('');

    const [analysis, setAnalysis] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [failure, setFailure] = useState(null);
    const [pollKey, setPollKey] = useState(0);

    const finishedRef = useRef(onFinished);
    finishedRef.current = onFinished;

    const analysisId = analysis?.id ?? null;
    const isFinished = analysis?.is_finished === true;

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return undefined;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    useEffect(() => {
        if (analysisId === null) {
            return undefined;
        }

        let cancelled = false;
        let timer = null;

        const poll = async () => {
            try {
                const result = await analyses.get(analysisId);

                if (cancelled) {
                    return;
                }

                setAnalysis(result);

                if (result.is_finished) {
                    setFailure(null);

                    if (result.status === 'completed') {
                        finishedRef.current?.();
                    }

                    return;
                }

                timer = setTimeout(poll, ANALYSIS_POLL_MS);
            } catch (cause) {
                if (!cancelled) {
                    setFailure(describeError(cause));
                }
            }
        };

        timer = setTimeout(poll, ANALYSIS_POLL_MS);

        return () => {
            cancelled = true;
            if (timer !== null) {
                clearTimeout(timer);
            }
        };
    }, [analysisId, pollKey]);

    const upload = async () => {
        if (!file) {
            setFailure('Pilih foto lahan terlebih dahulu.');

            return;
        }

        setUploading(true);
        setFailure(null);
        setAnalysis(null);

        const formData = new FormData();
        formData.append('image', file);
        if (capturedAt) {
            formData.append('captured_at', capturedAt);
        }
        formData.append('capture_source', captureSource);
        if (notes.trim() !== '') {
            formData.append('notes', notes.trim());
        }

        try {
            const created = await analyses.upload(landId, formData);
            setAnalysis(created);
            setPollKey((key) => key + 1);
        } catch (cause) {
            setFailure(describeError(cause));
        } finally {
            setUploading(false);
        }
    };

    const reset = () => {
        setFile(null);
        setAnalysis(null);
        setFailure(null);
        setNotes('');
        setCapturedAt(todayIso());
    };

    const busy = uploading || (analysisId !== null && !isFinished && failure === null);
    const failed = isFinished && analysis.status === 'failed';
    const stalled = failure !== null && analysisId !== null && !isFinished;

    return (
        <Panel title="Unggah foto lahan" subtitle="Satu foto mewakili satu periode pemantauan" icon="add_a_photo" className={className}>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div className="space-y-3">
                    <Field label="Foto lahan" htmlFor="analysis-image" required hint="JPG, PNG, atau WEBP, maksimal 50 MB.">
                        <TextInput
                            id="analysis-image"
                            type="file"
                            accept="image/*"
                            disabled={busy}
                            onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                            className="file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:font-body-sm file:text-on-primary"
                        />
                    </Field>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Field label="Tanggal foto" htmlFor="analysis-date" hint="Periode yang diwakili foto ini.">
                            <TextInput
                                id="analysis-date"
                                type="date"
                                max={todayIso()}
                                value={capturedAt}
                                disabled={busy}
                                onChange={(event) => setCapturedAt(event.target.value)}
                            />
                        </Field>
                        <Field label="Sumber foto" htmlFor="analysis-source">
                            <SelectInput
                                id="analysis-source"
                                options={CAPTURE_OPTIONS}
                                value={captureSource}
                                disabled={busy}
                                onChange={(event) => setCaptureSource(event.target.value)}
                            />
                        </Field>
                    </div>

                    <Field label="Catatan" htmlFor="analysis-notes">
                        <TextArea
                            id="analysis-notes"
                            value={notes}
                            disabled={busy}
                            onChange={(event) => setNotes(event.target.value)}
                            placeholder="Kondisi yang terlihat di lapangan, mis. bagian barat masih gundul."
                        />
                    </Field>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button icon="cloud_upload" onClick={upload} disabled={busy || !file}>
                            {uploading ? 'Mengunggah…' : failed ? 'Coba unggah lagi' : 'Unggah & analisis'}
                        </Button>
                        {stalled && (
                            <Button
                                variant="secondary"
                                icon="refresh"
                                onClick={() => {
                                    setFailure(null);
                                    setPollKey((key) => key + 1);
                                }}
                            >
                                Lanjutkan pemantauan
                            </Button>
                        )}
                        {analysisId !== null && (
                            <Button variant="ghost" onClick={reset} disabled={busy}>
                                Pilih foto lain
                            </Button>
                        )}
                        {onCancel && (
                            <Button variant="ghost" onClick={onCancel} disabled={busy}>
                                Tutup
                            </Button>
                        )}
                    </div>
                </div>

                <div className="space-y-3">
                    <div className="overflow-hidden rounded-xl border border-border-subtle bg-surface-container-low">
                        {preview ? (
                            <img src={preview} alt="Pratinjau foto yang akan diunggah" className="aspect-[4/3] w-full object-cover" />
                        ) : (
                            <div className="flex aspect-[4/3] w-full items-center justify-center text-outline">
                                <span className="material-symbols-outlined text-[32px]" aria-hidden="true">
                                    image
                                </span>
                            </div>
                        )}
                    </div>

                    {file && (
                        <p className="font-body-sm text-xs text-on-surface-variant">
                            {file.name} · {formatBytes(file.size)}
                        </p>
                    )}

                    {analysis && (
                        <div className="rounded-xl border border-border-subtle bg-surface-container-low p-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Chip
                                    label={analysis.status_label}
                                    color={
                                        analysis.status === 'completed' ? '#1B9E4B' : analysis.status === 'failed' ? '#D7263D' : '#0ea5e9'
                                    }
                                />
                                <span className="font-body-sm text-xs text-on-surface-variant">
                                    Periode {formatDate(analysis.captured_at)}
                                </span>
                            </div>
                            <p className="mt-2 font-body-sm text-body-sm text-on-surface-variant">{analysis.status_message}</p>

                            {analysis.status === 'completed' && (
                                <Link
                                    to={`/app/analisis/${analysis.id}`}
                                    className="mt-2 inline-flex items-center gap-1 font-body-sm text-body-sm font-semibold text-primary hover:underline"
                                >
                                    Buka hasil analisis
                                    <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                                        arrow_forward
                                    </span>
                                </Link>
                            )}

                            {failed && (
                                <div className="mt-2 space-y-1">
                                    <p className="font-body-sm text-body-sm text-critical">
                                        Foto ini tidak bisa dianalisis. Coba unggah foto lain yang lebih jelas.
                                    </p>
                                    {analysis.error && (
                                        <details className="font-body-sm text-xs text-on-surface-variant">
                                            <summary className="cursor-pointer font-semibold">Detail teknis layanan analisis</summary>
                                            <pre className="mt-1 whitespace-pre-wrap break-words font-mono text-[11px]">{analysis.error}</pre>
                                        </details>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {failure && (
                        <p className="rounded-lg border border-critical/40 bg-critical/5 px-3 py-2 font-body-sm text-body-sm text-critical">
                            {failure}
                        </p>
                    )}
                </div>
            </div>
        </Panel>
    );
}
