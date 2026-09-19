import React, { useCallback, useEffect, useRef, useState } from 'react';
import { gsap } from '@/gsap';
import DroneUploader from '../components/drone/DroneUploader';
import AnalysisProcessing from '../components/drone/AnalysisProcessing';
import AnalysisResults from '../components/drone/AnalysisResults';
import AnalysisHistory from '../components/drone/AnalysisHistory';
import { ApiError, createAnalysis, deleteAnalysis, getAnalysis, listAnalyses } from '../lib/api';

const POLL_INTERVAL = 1500;

/**
 * Drone Land Analysis feature page.
 *
 * Drives the upload -> understand -> decide journey via a small view state
 * machine and polls the backend until an analysis reaches a terminal state.
 */
export default function DroneAnalysisPage() {
    const [view, setView] = useState('upload');
    const [currentId, setCurrentId] = useState(null);
    const [analysis, setAnalysis] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(null);
    const [statusMessage, setStatusMessage] = useState(null);
    const [failedInfo, setFailedInfo] = useState(null);
    const [history, setHistory] = useState([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const contentRef = useRef(null);

    const loadHistory = useCallback(async () => {
        setHistoryLoading(true);
        try {
            setHistory(await listAnalyses());
        } catch {
            // History is non-critical for the page; leave it empty on failure.
        } finally {
            setHistoryLoading(false);
        }
    }, []);

    useEffect(() => {
        loadHistory();
    }, [loadHistory]);

    // Poll the active analysis while it is processing.
    useEffect(() => {
        if (view !== 'processing' || !currentId) {
            return undefined;
        }

        let cancelled = false;
        const controller = new AbortController();

        const poll = async () => {
            try {
                const data = await getAnalysis(currentId, { signal: controller.signal });
                if (cancelled) {
                    return;
                }
                setStatusMessage(data.status_message);
                if (data.is_finished) {
                    if (data.status === 'completed') {
                        setAnalysis(data);
                        setView('results');
                        loadHistory();
                    } else {
                        setFailedInfo(data);
                        setView('failed');
                    }
                }
            } catch (error) {
                // Ignore transient/aborted polls; the next tick retries.
                if (error?.name !== 'AbortError') {
                    // keep polling
                }
            }
        };

        poll();
        const timer = setInterval(poll, POLL_INTERVAL);

        return () => {
            cancelled = true;
            controller.abort();
            clearInterval(timer);
        };
    }, [view, currentId, loadHistory]);

    // Subtle transition whenever the view changes.
    useEffect(() => {
        if (contentRef.current) {
            gsap.fromTo(contentRef.current, { opacity: 0, y: 8 }, { opacity: 1, y: 0, duration: 0.3, ease: 'power2.out' });
        }
    }, [view]);

    const goToHistory = () => {
        setView('history');
        loadHistory();
    };

    const startNew = () => {
        setView('upload');
        setCurrentId(null);
        setAnalysis(null);
        setSubmitError(null);
        setFailedInfo(null);
        setStatusMessage(null);
    };

    const handleAnalyze = async (file, metadata) => {
        setSubmitting(true);
        setSubmitError(null);
        try {
            const form = new FormData();
            form.append('image', file);
            Object.entries(metadata).forEach(([key, value]) => form.append(key, value));

            const created = await createAnalysis(form);
            setCurrentId(created.id);
            setAnalysis(created);
            setStatusMessage(created.status_message);
            setView('processing');
        } catch (error) {
            if (error instanceof ApiError) {
                setSubmitError(error.fieldError('image') || error.message);
            } else {
                setSubmitError('Something went wrong while uploading. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    const handleViewAnalysis = async (id) => {
        try {
            const data = await getAnalysis(id);
            setAnalysis(data);
            setCurrentId(id);
            setView('results');
        } catch {
            // Ignore; the history row remains available.
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this analysis? This cannot be undone.')) {
            return;
        }
        setDeletingId(id);
        try {
            await deleteAnalysis(id);
            await loadHistory();
        } catch {
            // Ignore; the row stays.
        } finally {
            setDeletingId(null);
        }
    };

    return (
        <div className="space-y-8">
            <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p className="font-label-md text-xs uppercase tracking-wider text-outline">Drone Analysis</p>
                    <h2 className="font-headline-xl text-3xl font-bold text-primary dark:text-primary-fixed md:text-4xl">
                        Drone Land Analysis
                    </h2>
                    <p className="mt-1 max-w-2xl font-body-md text-sm text-on-surface-variant md:text-base">
                        Upload aerial imagery to analyze vegetation health, land degradation, and restoration potential.
                    </p>
                </div>
                <div className="flex gap-3">
                    <button
                        type="button"
                        onClick={goToHistory}
                        className="inline-flex items-center gap-2 rounded-lg border border-border-subtle bg-surface-container-lowest px-4 py-2.5 font-label-md text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                    >
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                            history
                        </span>
                        Analysis History
                    </button>
                    <button
                        type="button"
                        onClick={startNew}
                        className="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2.5 font-label-md text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary"
                    >
                        <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                            add
                        </span>
                        New Analysis
                    </button>
                </div>
            </header>

            <div ref={contentRef}>
                {view === 'upload' && (
                    <DroneUploader onAnalyze={handleAnalyze} submitting={submitting} error={submitError} onViewHistory={goToHistory} />
                )}
                {view === 'processing' && <AnalysisProcessing statusMessage={statusMessage} />}
                {view === 'results' && (
                    <AnalysisResults analysis={analysis} onNewAnalysis={startNew} onViewHistory={goToHistory} />
                )}
                {view === 'failed' && <FailedState info={failedInfo} onRetry={startNew} onViewHistory={goToHistory} />}
                {view === 'history' && (
                    <AnalysisHistory
                        items={history}
                        loading={historyLoading}
                        deletingId={deletingId}
                        onView={handleViewAnalysis}
                        onDelete={handleDelete}
                        onNewAnalysis={startNew}
                    />
                )}
            </div>
        </div>
    );
}

function FailedState({ info, onRetry, onViewHistory }) {
    return (
        <div className="mx-auto max-w-lg rounded-2xl border border-rose-200 bg-rose-50/60 p-8 text-center dark:border-rose-800/40 dark:bg-rose-950/20">
            <span className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600 dark:bg-rose-900/40">
                <span className="material-symbols-outlined text-[30px]" aria-hidden="true">
                    error
                </span>
            </span>
            <h3 className="mt-4 font-headline-md text-headline-sm font-bold text-on-surface">Analysis Failed</h3>
            <p className="mt-1 font-body-sm text-sm text-on-surface-variant">
                {info?.error || "We couldn't analyze this image. Please try another image."}
            </p>
            <div className="mt-6 flex justify-center gap-3">
                <button
                    type="button"
                    onClick={onViewHistory}
                    className="inline-flex items-center gap-2 rounded-lg border border-border-subtle px-4 py-2.5 font-label-md text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                >
                    View History
                </button>
                <button
                    type="button"
                    onClick={onRetry}
                    className="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2.5 font-label-md text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary"
                >
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        refresh
                    </span>
                    Try Another Image
                </button>
            </div>
        </div>
    );
}
