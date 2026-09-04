import React from 'react';
import { formatDate, formatPercent, titleCase } from '../../lib/format';
import { ConfidenceBadge, HealthStatusPill, PriorityPill, SeverityBadge } from './badges';
import SegmentationViewer from './SegmentationViewer';
import PriorityMap from './PriorityMap';

/**
 * The full analysis dashboard: health score, metrics, AI segmentation, detected
 * issues, restoration recommendations, and a relative priority map. Follows the
 * product visual hierarchy (health -> metrics -> imagery -> issues -> actions).
 */
export default function AnalysisResults({ analysis, onNewAnalysis, onViewHistory }) {
    const result = analysis?.analysis;

    if (!result) {
        return (
            <div className="rounded-xl border border-border-subtle bg-surface-container-lowest p-8 text-center text-on-surface-variant">
                No analysis data is available for this record.
            </div>
        );
    }

    const { land_health: health, metrics, land_cover: cover = [], issues = [], confidence, segmentation } = result;
    const recommendations = analysis.recommendation?.recommendations ?? [];
    const overallPriority = analysis.recommendation?.priority;
    const meta = analysis.metadata ?? {};
    const imageUrl = analysis.image?.url;

    return (
        <div className="space-y-6">
            {/* Context strip */}
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-border-subtle bg-surface-container-lowest px-5 py-3 card-shadow">
                <span className="inline-flex items-center gap-1.5 font-semibold text-on-surface">
                    <span className="material-symbols-outlined text-[18px] text-primary dark:text-primary-fixed" aria-hidden="true">
                        landscape
                    </span>
                    {meta.area_name || 'Untitled area'}
                </span>
                <span className="font-body-sm text-xs text-outline">{formatDate(meta.survey_date || analysis.created_at)}</span>
                <span className="hidden h-4 w-px bg-border-subtle sm:block" />
                <span className="font-body-sm text-xs text-outline">
                    Model: {analysis.ai_model || 'n/a'} {analysis.ai_model_version ? `v${analysis.ai_model_version}` : ''}
                </span>
                <span className="ml-auto">{confidence?.overall != null && <ConfidenceBadge value={confidence.overall} />}</span>
            </div>

            {/* Health + metrics */}
            <section className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <div className="lg:col-span-4 flex flex-col justify-between rounded-xl border border-border-subtle bg-surface-container-lowest p-6 card-shadow">
                    <div className="flex items-center justify-between">
                        <h3 className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Land Health</h3>
                        <HealthStatusPill status={health?.status} />
                    </div>
                    <div className="my-4 flex items-baseline gap-2">
                        <span className="font-stats-lg text-[56px] leading-none text-primary dark:text-primary-fixed">{health?.score ?? '—'}</span>
                        <span className="font-body-md text-on-surface-variant">/ 100</span>
                    </div>
                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-surface-container-highest">
                        <div className="h-full rounded-full bg-primary-container" style={{ width: `${health?.score ?? 0}%` }} />
                    </div>
                    <p className="mt-3 font-body-sm text-xs text-outline">
                        AI-derived assessment, not a scientific ground-truth measurement. Validate through field observation
                        when required.
                    </p>
                </div>

                <div className="lg:col-span-8 grid grid-cols-2 gap-4 xl:grid-cols-3">
                    <MetricStat icon="forest" label="Vegetation Coverage" value={formatPercent(metrics?.vegetation_coverage)} description="Estimated share of the image covered by vegetation." />
                    <MetricStat icon="terrain" label="Bare Soil" value={formatPercent(metrics?.bare_soil)} description="Estimated exposed soil detected from imagery." />
                    <MetricStat icon="warning" label="Degraded Area" value={formatPercent(metrics?.degraded_area)} description="Area showing visual indicators of degradation." />
                    <MetricStat icon="water_drop" label="Water Presence" value={formatPercent(metrics?.water_presence)} description="Estimated water coverage in the imagery." />
                    <MetricStat
                        icon="eco"
                        label="Restoration Potential"
                        value={<span className="uppercase">{metrics?.restoration_potential || '—'}</span>}
                        description="AI-estimated potential for ecological restoration."
                    />
                    <MetricStat
                        icon="straighten"
                        label="Estimated Area"
                        value={metrics?.estimated_area_hectares != null ? `${metrics.estimated_area_hectares} ha` : 'Unavailable'}
                        description={
                            metrics?.estimated_area_hectares != null
                                ? 'Approximate analyzed area.'
                                : 'Area estimation requires reliable geospatial data.'
                        }
                        muted={metrics?.estimated_area_hectares == null}
                    />
                </div>
            </section>

            {/* Land cover breakdown */}
            {cover.length > 0 && (
                <section className="rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow">
                    <h3 className="mb-4 font-headline-md text-lg font-bold text-on-surface">Land Cover</h3>
                    <StackedBar cover={cover} />
                </section>
            )}

            {/* AI segmentation */}
            <section className="rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow">
                <h3 className="mb-4 font-headline-md text-lg font-bold text-on-surface">AI Land Analysis</h3>
                <SegmentationViewer
                    imageUrl={imageUrl}
                    segmentation={segmentation}
                    landCover={cover}
                    confidence={confidence?.vegetation_detection ?? confidence?.overall}
                    altText={`Drone imagery of ${meta.area_name || 'analyzed land'}`}
                />
            </section>

            {/* Detected issues */}
            <section className="rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow">
                <h3 className="mb-4 font-headline-md text-lg font-bold text-on-surface">Detected Land Issues</h3>
                {issues.length === 0 ? (
                    <p className="font-body-sm text-sm text-on-surface-variant">No significant land issues were detected.</p>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        {issues.map((issue) => (
                            <div key={issue.type} className="rounded-lg border border-border-subtle bg-surface-container-low p-4">
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <h4 className="font-semibold text-on-surface">{issue.title || titleCase(issue.type)}</h4>
                                    <SeverityBadge severity={issue.severity} />
                                </div>
                                {issue.affected_area != null && (
                                    <p className="font-label-md text-[11px] uppercase tracking-wider text-outline">
                                        Affected area · {formatPercent(issue.affected_area)}
                                    </p>
                                )}
                                <p className="mt-2 font-body-sm text-xs text-on-surface-variant">{issue.description}</p>
                                {issue.confidence != null && <ConfidenceBadge value={issue.confidence} className="mt-3" />}
                            </div>
                        ))}
                    </div>
                )}
            </section>

            {/* Recommendations */}
            <section className="rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h3 className="font-headline-md text-lg font-bold text-on-surface">AI Restoration Recommendation</h3>
                    {overallPriority && (
                        <span className="inline-flex items-center gap-2 font-label-md text-[11px] uppercase tracking-wider text-outline">
                            Priority <PriorityPill priority={overallPriority} />
                        </span>
                    )}
                </div>
                <ol className="space-y-3">
                    {recommendations.map((rec, index) => (
                        <li key={rec.action} className="rounded-lg border border-border-subtle bg-surface-container-low p-4">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h4 className="flex items-center gap-2 font-semibold text-on-surface">
                                    <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary-container text-xs font-bold text-white">
                                        {index + 1}
                                    </span>
                                    {rec.title}
                                </h4>
                                <PriorityPill priority={rec.priority} />
                            </div>
                            {rec.reason && <p className="mt-2 font-body-sm text-xs text-on-surface-variant">{rec.reason}</p>}
                            {rec.recommended_actions?.length > 0 && (
                                <ul className="mt-3 flex flex-wrap gap-2">
                                    {rec.recommended_actions.map((action) => (
                                        <li key={action} className="inline-flex items-center gap-1 rounded-full bg-surface-container px-2.5 py-1 font-label-md text-[11px] text-on-surface-variant">
                                            <span className="material-symbols-outlined text-[13px] text-success" aria-hidden="true">
                                                check
                                            </span>
                                            {action}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ol>
            </section>

            {/* Priority map */}
            <PriorityMap imageUrl={imageUrl} segmentation={segmentation} overallPriority={overallPriority} />

            {/* Actions */}
            <div className="flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    onClick={onViewHistory}
                    className="inline-flex items-center gap-2 rounded-lg border border-border-subtle px-4 py-2.5 font-label-md text-xs font-semibold text-on-surface-variant transition-colors hover:bg-surface-container"
                >
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        history
                    </span>
                    View History
                </button>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-2 rounded-lg border border-primary-container px-4 py-2.5 font-label-md text-xs font-semibold text-primary-container transition-colors hover:bg-surface-container dark:text-primary-fixed"
                >
                    <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                        description
                    </span>
                    Generate Report
                </button>
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
        </div>
    );
}

function MetricStat({ icon, label, value, description, muted = false }) {
    return (
        <div className="flex flex-col rounded-xl border border-border-subtle bg-surface-container-lowest p-4">
            <div className="mb-2 flex items-center justify-between">
                <span className="font-label-md text-[11px] uppercase tracking-wider text-outline">{label}</span>
                <span className="material-symbols-outlined text-[18px] text-outline" aria-hidden="true">
                    {icon}
                </span>
            </div>
            <span className={`font-stats-lg text-2xl ${muted ? 'text-on-surface-variant' : 'text-primary dark:text-primary-fixed'}`}>
                {value}
            </span>
            <span className="mt-1 font-body-sm text-[11px] leading-snug text-outline">{description}</span>
        </div>
    );
}

function StackedBar({ cover }) {
    const visible = cover.filter((entry) => entry.percentage > 0);
    return (
        <div>
            <div className="flex h-6 w-full overflow-hidden rounded-full border border-border-subtle" role="img" aria-label="Land cover distribution">
                {visible.map((entry) => (
                    <div
                        key={entry.key}
                        style={{ width: `${entry.percentage}%`, backgroundColor: entry.color }}
                        title={`${entry.label}: ${entry.percentage}%`}
                    />
                ))}
            </div>
            <div className="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                {cover.map((entry) => (
                    <div key={entry.key} className="flex items-center justify-between gap-2 text-sm">
                        <span className="inline-flex items-center gap-2 text-on-surface-variant">
                            <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: entry.color }} aria-hidden="true" />
                            {entry.label}
                        </span>
                        <span className="font-semibold text-on-surface">{entry.percentage}%</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
