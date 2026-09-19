import React, { useId, useMemo, useState } from 'react';
import { ConfidenceBadge } from './badges';

const MODES = [
    { id: 'original', label: 'Foto Asli' },
    { id: 'ai', label: 'Overlay AI' },
    { id: 'comparison', label: 'Bandingkan' },
];

const MODE_CAPTIONS = {
    original: 'Foto asli',
    ai: 'Interpretasi AI',
    comparison: 'Bandingkan',
};

/**
 * Renders the land photo with a toggleable AI segmentation overlay.
 *
 * The overlay is a coarse colour grid returned by the model (honest, low-res
 * interpretation), drawn as CSS grid cells so it scales with the image. Demo
 * lands store no photo, so `imageUrl` may be null: the overlay still renders and
 * the panel says the photo is not stored yet.
 */
export default function SegmentationViewer({ imageUrl, segmentation, landCover = [], confidence, altText = 'Foto lahan' }) {
    const [mode, setMode] = useState('ai');
    const [opacity, setOpacity] = useState(70);
    const [comparePos, setComparePos] = useState(50);
    const opacityId = useId();

    const hasOverlay = Boolean(segmentation?.available && segmentation?.grid?.length);
    const flatCells = useMemo(() => (hasOverlay ? segmentation.grid.flat() : []), [hasOverlay, segmentation]);
    const legend = segmentation?.legend ?? {};

    const overlayOpacity = mode === 'original' ? 0 : opacity / 100;
    const overlayClip = mode === 'comparison' ? `inset(0 0 0 ${comparePos}%)` : 'none';

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="inline-flex rounded-lg border border-border-subtle bg-surface-container-low p-1" role="tablist" aria-label="Mode tampilan">
                    {MODES.map((option) => (
                        <button
                            key={option.id}
                            type="button"
                            role="tab"
                            aria-selected={mode === option.id}
                            onClick={() => setMode(option.id)}
                            disabled={option.id !== 'original' && !hasOverlay}
                            className={`rounded-md px-3 py-1.5 font-label-md text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
                                mode === option.id
                                    ? 'bg-surface-container-lowest text-primary shadow-xs dark:text-primary-fixed'
                                    : 'text-on-surface-variant hover:text-primary'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
                {confidence != null && <ConfidenceBadge value={confidence} />}
            </div>

            <div className="relative overflow-hidden rounded-xl border border-border-subtle bg-surface-container-low">
                <div className="relative aspect-video w-full">
                    {imageUrl ? (
                        <img src={imageUrl} alt={altText} className="absolute inset-0 h-full w-full object-cover" />
                    ) : (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-outline">
                            <span className="material-symbols-outlined text-[40px]" aria-hidden="true">
                                hide_image
                            </span>
                            <span className="font-body-sm text-xs">Foto lahan belum tersimpan</span>
                        </div>
                    )}

                    {hasOverlay && (
                        <div
                            className="absolute inset-0 grid transition-opacity duration-300"
                            style={{
                                gridTemplateColumns: `repeat(${segmentation.cols}, 1fr)`,
                                gridTemplateRows: `repeat(${segmentation.rows}, 1fr)`,
                                opacity: overlayOpacity,
                                clipPath: overlayClip,
                            }}
                            aria-hidden="true"
                        >
                            {flatCells.map((key, index) => (
                                <div key={index} style={{ backgroundColor: legend[key] ?? 'transparent' }} />
                            ))}
                        </div>
                    )}

                    {mode === 'comparison' && hasOverlay && (
                        <div className="pointer-events-none absolute inset-y-0 w-0.5 bg-white/80 shadow" style={{ left: `${comparePos}%` }} />
                    )}

                    <span className="absolute left-3 top-3 rounded-full bg-charcoal/80 px-2.5 py-1 font-label-md text-[10px] font-semibold uppercase tracking-wider text-white backdrop-blur">
                        {MODE_CAPTIONS[mode]}
                    </span>
                </div>
            </div>

            {/* Controls */}
            {hasOverlay ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {mode !== 'original' && (
                        <label htmlFor={opacityId} className="block">
                            <span className="mb-1 flex items-center justify-between font-label-md text-[11px] uppercase tracking-wider text-outline">
                                Transparansi overlay <span className="text-on-surface-variant">{opacity}%</span>
                            </span>
                            <input
                                id={opacityId}
                                type="range"
                                min="0"
                                max="100"
                                value={opacity}
                                onChange={(event) => setOpacity(Number(event.target.value))}
                                className="w-full accent-[var(--color-primary-container)]"
                            />
                        </label>
                    )}
                    {mode === 'comparison' && (
                        <label className="block">
                            <span className="mb-1 flex items-center justify-between font-label-md text-[11px] uppercase tracking-wider text-outline">
                                Posisi pembanding <span className="text-on-surface-variant">Geser untuk membandingkan</span>
                            </span>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value={comparePos}
                                onChange={(event) => setComparePos(Number(event.target.value))}
                                className="w-full accent-[var(--color-primary-container)]"
                            />
                        </label>
                    )}
                </div>
            ) : (
                <p className="flex items-start gap-2 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-body-sm text-xs text-outline">
                    <span className="material-symbols-outlined text-[15px]" aria-hidden="true">
                        info
                    </span>
                    Overlay segmentasi belum tersedia untuk gambar ini. Sebaran tutupan lahan dirangkum di bawah.
                </p>
            )}

            {/* Legend from land cover */}
            {landCover.length > 0 && (
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {landCover.map((entry) => (
                        <span key={entry.key} className="inline-flex items-center gap-1.5 font-body-sm text-xs text-on-surface-variant">
                            <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: entry.color }} aria-hidden="true" />
                            {entry.label}
                            <span className="font-semibold text-on-surface">{entry.percentage}%</span>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}
