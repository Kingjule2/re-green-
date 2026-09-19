/**
 * One monitoring photo, or — when the photo cannot be served — the analysis
 * model's own segmentation grid standing in for it.
 *
 * The grid is the honest fallback: the demo workspace and the local storage
 * sometimes have no image file behind a row, and an empty frame would hide the
 * fact that the numbers on the page came from a real interpretation. The cells
 * are drawn in the colours the model reported, with the Indonesian land-cover
 * vocabulary from `lib/i18n.js`.
 */
import React from 'react';
import { tCover } from '@/react/lib/i18n';

/** The legend entries, from the land-cover breakdown or from the grid itself. */
function legendEntries(landCover, legend) {
    if (landCover?.length) {
        return landCover.map((entry) => ({
            key: entry.key,
            label: tCover(entry.key),
            color: entry.color,
            percentage: entry.percentage,
        }));
    }

    return Object.entries(legend ?? {}).map(([key, color]) => ({
        key,
        label: tCover(key),
        color,
        percentage: null,
    }));
}

export default function SegmentationGrid({ imageUrl = null, segmentation = null, landCover = [], alt = 'Foto lahan', className = '' }) {
    const hasGrid = Boolean(segmentation?.available && segmentation?.grid?.length);
    const legend = segmentation?.legend ?? {};
    const entries = legendEntries(landCover, legend);

    return (
        <figure className={`space-y-3 ${className}`}>
            <div className="relative overflow-hidden rounded-xl border border-border-subtle bg-surface-container-low">
                <div className="relative aspect-[4/3] w-full">
                    {imageUrl ? (
                        <img src={imageUrl} alt={alt} className="absolute inset-0 h-full w-full object-cover" loading="lazy" />
                    ) : hasGrid ? (
                        <div
                            className="absolute inset-0 grid"
                            style={{
                                gridTemplateColumns: `repeat(${segmentation.cols}, 1fr)`,
                                gridTemplateRows: `repeat(${segmentation.rows}, 1fr)`,
                            }}
                            role="img"
                            aria-label={`Peta segmentasi otomatis: ${alt}`}
                        >
                            {segmentation.grid.flat().map((key, index) => (
                                <span key={index} style={{ backgroundColor: legend[key] ?? 'var(--color-surface-container-highest)' }} />
                            ))}
                        </div>
                    ) : (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-outline">
                            <span className="material-symbols-outlined text-[32px]" aria-hidden="true">
                                hide_image
                            </span>
                            <span className="font-body-sm text-xs">Foto periode ini tidak tersimpan</span>
                        </div>
                    )}

                    {!imageUrl && hasGrid && (
                        <span className="absolute left-3 top-3 rounded-full bg-charcoal/80 px-2.5 py-1 font-label-md text-[10px] uppercase tracking-wider text-white">
                            Hasil segmentasi model
                        </span>
                    )}
                </div>
            </div>

            {entries.length > 0 && (
                <figcaption className="flex flex-wrap gap-x-4 gap-y-1.5">
                    {entries.map((entry) => (
                        <span key={entry.key} className="inline-flex items-center gap-1.5 font-body-sm text-xs text-on-surface-variant">
                            <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: entry.color }} aria-hidden="true" />
                            {entry.label}
                            {entry.percentage != null && <span className="font-semibold text-on-surface">{entry.percentage}%</span>}
                        </span>
                    ))}
                </figcaption>
            )}
        </figure>
    );
}
