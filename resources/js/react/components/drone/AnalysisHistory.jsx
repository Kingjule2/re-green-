import React from 'react';
import { formatDate } from '../../lib/format';
import { HealthStatusPill } from './badges';

/** Map a raw processing/health state to a short status pill. */
function StatusCell({ item }) {
    if (item.status === 'completed') {
        return <HealthStatusPill status={item.analysis?.land_health?.status ?? 'moderate'} />;
    }
    const tone =
        item.status === 'failed'
            ? 'bg-rose-50 text-rose-700 border-rose-200'
            : 'bg-sky-50 text-sky-700 border-sky-200';
    return (
        <span className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold ${tone}`}>
            {item.status === 'failed' ? 'Failed' : 'Processing'}
        </span>
    );
}

/**
 * Table of previous drone analyses with view/delete actions.
 */
export default function AnalysisHistory({ items = [], loading = false, deletingId = null, onView, onDelete, onNewAnalysis }) {
    return (
        <div className="rounded-xl border border-border-subtle bg-surface-container-lowest card-shadow">
            <div className="flex items-center justify-between border-b border-border-subtle p-5">
                <div>
                    <h3 className="font-headline-md text-lg font-bold text-on-surface">Analysis History</h3>
                    <p className="font-body-sm text-xs text-outline">Your previous drone land analyses</p>
                </div>
                <button
                    type="button"
                    onClick={onNewAnalysis}
                    className="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2.5 font-label-md text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary"
                >
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        add
                    </span>
                    New Analysis
                </button>
            </div>

            {loading ? (
                <div className="flex items-center justify-center gap-2 p-10 text-on-surface-variant">
                    <span className="material-symbols-outlined animate-spin text-[20px]" aria-hidden="true">
                        progress_activity
                    </span>
                    Loading history…
                </div>
            ) : items.length === 0 ? (
                <div className="flex flex-col items-center gap-3 p-12 text-center">
                    <span className="material-symbols-outlined text-[40px] text-outline" aria-hidden="true">
                        inbox
                    </span>
                    <p className="font-body-md text-sm text-on-surface-variant">No analyses yet. Upload a drone image to get started.</p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border-subtle font-label-md text-[11px] uppercase tracking-wider text-outline">
                                <th scope="col" className="px-5 py-3 font-semibold">Date</th>
                                <th scope="col" className="px-5 py-3 font-semibold">Land</th>
                                <th scope="col" className="px-5 py-3 font-semibold">Health Score</th>
                                <th scope="col" className="px-5 py-3 font-semibold">Vegetation</th>
                                <th scope="col" className="px-5 py-3 font-semibold">Status</th>
                                <th scope="col" className="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item) => (
                                <tr key={item.id} className="border-b border-border-subtle last:border-0 hover:bg-surface-container-low">
                                    <td className="whitespace-nowrap px-5 py-3 text-on-surface-variant">{formatDate(item.created_at)}</td>
                                    <td className="px-5 py-3 font-semibold text-on-surface">{item.metadata?.area_name || 'Untitled area'}</td>
                                    <td className="px-5 py-3 font-semibold text-on-surface">{item.land_health_score ?? '—'}</td>
                                    <td className="px-5 py-3 text-on-surface-variant">
                                        {item.vegetation_percentage != null ? `${Math.round(item.vegetation_percentage)}%` : '—'}
                                    </td>
                                    <td className="px-5 py-3">
                                        <StatusCell item={item} />
                                    </td>
                                    <td className="px-5 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                onClick={() => onView(item.id)}
                                                disabled={item.status !== 'completed'}
                                                className="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 font-label-md text-xs font-semibold text-primary transition-colors hover:bg-surface-container disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                                                    visibility
                                                </span>
                                                View
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => onDelete(item.id)}
                                                disabled={deletingId === item.id}
                                                className="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 font-label-md text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 disabled:opacity-40"
                                                aria-label={`Delete analysis from ${formatDate(item.created_at)}`}
                                            >
                                                <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                                                    {deletingId === item.id ? 'progress_activity' : 'delete'}
                                                </span>
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
