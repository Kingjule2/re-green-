import React, { useMemo } from 'react';
import { PriorityPill } from './badges';

// Which restoration priority each land-cover class implies.
const CLASS_PRIORITY = {
    bare_soil: 'high',
    sparse_vegetation: 'medium',
    other: 'medium',
    dense_vegetation: 'low',
    water: 'low',
    built_area: 'low',
};

const PRIORITY_COLOR = { low: '#16a34a', medium: '#f59e0b', high: '#ef4444', critical: '#b91c1c' };

const LEGEND = [
    { key: 'low', label: 'Low Priority' },
    { key: 'medium', label: 'Medium Priority' },
    { key: 'high', label: 'High Priority' },
];

/**
 * Relative restoration priority map derived from the coarse segmentation grid.
 *
 * A single drone image is not georeferenced, so this is explicitly labelled a
 * *relative* map, never an accurate GIS layer.
 */
export default function PriorityMap({ imageUrl, segmentation, overallPriority }) {
    const hasGrid = Boolean(segmentation?.available && segmentation?.grid?.length);
    const cells = useMemo(() => (hasGrid ? segmentation.grid.flat() : []), [hasGrid, segmentation]);

    return (
        <section className="rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 className="font-headline-md text-lg font-bold text-on-surface">Relative Priority Map</h3>
                    <p className="font-body-sm text-xs text-outline">Where restoration attention is likely needed first</p>
                </div>
                {overallPriority && (
                    <span className="inline-flex items-center gap-2 font-label-md text-[11px] uppercase tracking-wider text-outline">
                        Overall <PriorityPill priority={overallPriority} />
                    </span>
                )}
            </div>

            {hasGrid ? (
                <div className="relative overflow-hidden rounded-xl border border-border-subtle bg-surface-container-low">
                    <div className="relative aspect-video w-full">
                        {imageUrl && <img src={imageUrl} alt="" className="absolute inset-0 h-full w-full object-cover" />}
                        <div
                            className="absolute inset-0 grid"
                            style={{
                                gridTemplateColumns: `repeat(${segmentation.cols}, 1fr)`,
                                gridTemplateRows: `repeat(${segmentation.rows}, 1fr)`,
                                opacity: 0.62,
                            }}
                            aria-hidden="true"
                        >
                            {cells.map((key, index) => (
                                <div key={index} style={{ backgroundColor: PRIORITY_COLOR[CLASS_PRIORITY[key] ?? 'medium'] }} />
                            ))}
                        </div>
                    </div>
                </div>
            ) : (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border-subtle bg-surface-container-low p-8 text-center">
                    <span className="material-symbols-outlined text-[32px] text-outline" aria-hidden="true">
                        map
                    </span>
                    <p className="font-body-sm text-xs text-on-surface-variant">
                        A spatial priority grid is not available for this image. Overall relative priority is{' '}
                        <strong className="uppercase">{overallPriority || 'unknown'}</strong>.
                    </p>
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {LEGEND.map((entry) => (
                        <span key={entry.key} className="inline-flex items-center gap-1.5 font-body-sm text-xs text-on-surface-variant">
                            <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: PRIORITY_COLOR[entry.key] }} aria-hidden="true" />
                            {entry.label}
                        </span>
                    ))}
                </div>
                <p className="font-body-sm text-[11px] text-outline">Relative estimate — not a georeferenced GIS map.</p>
            </div>
        </section>
    );
}
