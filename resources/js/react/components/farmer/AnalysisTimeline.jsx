/**
 * The monitoring history of one land, newest period first.
 *
 * Every row answers the same three questions: when was this photo taken, how
 * bad did it look, and did the land move since the period before it. A row whose
 * analysis has not finished shows the server's status instead of numbers, so the
 * timeline never mixes measured values with a pending job.
 */
import React from 'react';
import { formatDate, formatNumber } from '@/react/lib/format';
import { tSeverity } from '@/react/lib/i18n';
import { Link } from '@/react/lib/router';
import { Chip, DeltaPill } from '@/react/components/farmer/primitives';
import EmptyState from '@/react/components/shared/EmptyState';

export default function AnalysisTimeline({ analyses = [], className = '' }) {
    if (analyses.length === 0) {
        return (
            <EmptyState
                icon="photo_library"
                title="Belum ada periode monitoring"
                description="Unggah foto lahan pertama untuk memulai rangkaian pemantauan."
                className={className}
            />
        );
    }

    return (
        <ol className={`space-y-3 ${className}`}>
            {analyses.map((analysis) => {
                const severity = analysis.burn_severity;
                const progress = analysis.progress;

                return (
                    <li key={analysis.id} className="rounded-xl border border-border-subtle bg-surface-container-low p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-body-md text-body-md font-semibold text-on-surface">
                                        {formatDate(analysis.captured_at)}
                                    </p>
                                    {severity ? (
                                        <Chip label={severity.label ?? tSeverity(severity.level)} color={severity.color} />
                                    ) : (
                                        <Chip label={analysis.status_label} />
                                    )}
                                </div>
                                <p className="mt-0.5 font-body-sm text-xs text-on-surface-variant">
                                    {analysis.notes ?? `Sumber foto: ${analysis.capture_source ?? '—'}`}
                                </p>
                            </div>

                            <Link
                                to={`/app/analisis/${analysis.id}`}
                                className="inline-flex items-center gap-1 font-body-sm text-body-sm font-semibold text-primary hover:underline"
                            >
                                Lihat analisis
                                <span className="material-symbols-outlined text-[16px]" aria-hidden="true">
                                    arrow_forward
                                </span>
                            </Link>
                        </div>

                        {analysis.is_finished && analysis.status === 'completed' ? (
                            <div className="mt-3 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-border-subtle pt-3">
                                <span className="font-body-sm text-body-sm text-on-surface-variant">
                                    Skor kesehatan <span className="font-semibold text-on-surface">{formatNumber(analysis.metrics?.health_score)}</span>
                                </span>
                                <span className="font-body-sm text-body-sm text-on-surface-variant">
                                    Vegetasi <span className="font-semibold text-on-surface">{formatNumber(analysis.metrics?.vegetation_percentage, 1)}%</span>
                                </span>
                                <span className="font-body-sm text-body-sm text-on-surface-variant">
                                    Tanah terbakar <span className="font-semibold text-on-surface">{formatNumber(analysis.metrics?.charred_percentage, 1)}%</span>
                                </span>
                                {progress ? (
                                    <DeltaPill
                                        delta={progress.vegetation_delta}
                                        direction={progress.direction}
                                        label={`vs ${formatDate(progress.compared_to?.captured_at)}`}
                                    />
                                ) : (
                                    <span className="font-body-sm text-xs text-on-surface-variant">Periode pertama, belum ada pembanding</span>
                                )}
                            </div>
                        ) : (
                            <p className="mt-3 border-t border-border-subtle pt-3 font-body-sm text-body-sm text-on-surface-variant">
                                {analysis.status_message}
                            </p>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
