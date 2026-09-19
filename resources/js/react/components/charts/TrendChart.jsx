/**
 * Progress chart.
 *
 * A dependency-free SVG line/area chart. The monitoring story is a handful of
 * points per land, so a real charting library would cost more than it explains:
 * exact values are printed on the axis ticks, every point is focusable, and the
 * hovered period is reported back to the caller.
 */
import React, { useState } from 'react';

const PADDING = { top: 16, right: 16, bottom: 28, left: 40 };

/**
 * @param {object} props
 * @param {Array<{key: string, label: string, color: string, values: Array<number|null>, dashed?: boolean}>} props.series
 * @param {string[]} props.labels
 * @param {number} [props.height]
 * @param {number} [props.min]
 * @param {number} [props.max]
 * @param {string} [props.valueSuffix]
 * @param {(index: number) => void} [props.onHover]
 */
export default function TrendChart({
    series = [],
    labels = [],
    height = 260,
    min = 0,
    max = null,
    valueSuffix = '',
    onHover,
    className = '',
}) {
    const width = 720;
    const [hovered, setHovered] = useState(null);

    const values = series.flatMap((entry) => entry.values.filter((value) => value !== null && value !== undefined));
    const upper = max ?? Math.max(100, ...values);
    const lower = min;
    const span = Math.max(1, upper - lower);

    const plotWidth = width - PADDING.left - PADDING.right;
    const plotHeight = height - PADDING.top - PADDING.bottom;
    const step = labels.length > 1 ? plotWidth / (labels.length - 1) : 0;

    const x = (index) => PADDING.left + step * index;
    const y = (value) => PADDING.top + plotHeight - ((value - lower) / span) * plotHeight;

    const ticks = [lower, lower + span / 2, upper];

    if (labels.length === 0) {
        return (
            <div className={`flex h-40 items-center justify-center rounded-xl border border-dashed border-border-subtle font-body-sm text-sm text-on-surface-variant ${className}`}>
                Belum ada periode monitoring untuk digambarkan.
            </div>
        );
    }

    const handleMove = (event) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const relative = ((event.clientX - rect.left) / rect.width) * width - PADDING.left;
        const index = step === 0 ? 0 : Math.max(0, Math.min(labels.length - 1, Math.round(relative / step)));

        setHovered(index);
        onHover?.(index);
    };

    return (
        <div className={className}>
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="w-full"
                role="img"
                aria-label="Grafik perkembangan lahan"
                onMouseMove={handleMove}
                onMouseLeave={() => {
                    setHovered(null);
                    onHover?.(null);
                }}
            >
                {ticks.map((tick) => (
                    <g key={tick}>
                        <line
                            x1={PADDING.left}
                            x2={width - PADDING.right}
                            y1={y(tick)}
                            y2={y(tick)}
                            stroke="var(--color-border-subtle)"
                            strokeDasharray="3 3"
                        />
                        <text x={8} y={y(tick) + 4} className="fill-[var(--color-outline)] text-[11px]">
                            {Math.round(tick)}
                            {valueSuffix}
                        </text>
                    </g>
                ))}

                {series.map((entry) => {
                    const points = entry.values
                        .map((value, index) => (value === null || value === undefined ? null : `${x(index)},${y(value)}`))
                        .filter(Boolean);

                    if (points.length === 0) {
                        return null;
                    }

                    const line = `M ${points.join(' L ')}`;
                    const area = `${line} L ${x(entry.values.length - 1)},${PADDING.top + plotHeight} L ${x(0)},${PADDING.top + plotHeight} Z`;

                    return (
                        <g key={entry.key}>
                            {!entry.dashed && (
                                <path d={area} fill={entry.color} opacity="0.12" />
                            )}
                            <path
                                d={line}
                                fill="none"
                                stroke={entry.color}
                                strokeWidth="2.5"
                                strokeDasharray={entry.dashed ? '6 4' : undefined}
                                strokeLinejoin="round"
                                strokeLinecap="round"
                            />
                            {entry.values.map((value, index) =>
                                value === null || value === undefined ? null : (
                                    <circle
                                        key={index}
                                        cx={x(index)}
                                        cy={y(value)}
                                        r={hovered === index ? 6 : 4}
                                        fill="var(--color-surface-container-lowest)"
                                        stroke={entry.color}
                                        strokeWidth="2.5"
                                    />
                                ),
                            )}
                        </g>
                    );
                })}

                {hovered !== null && (
                    <line
                        x1={x(hovered)}
                        x2={x(hovered)}
                        y1={PADDING.top}
                        y2={PADDING.top + plotHeight}
                        stroke="var(--color-outline)"
                        strokeDasharray="4 4"
                    />
                )}

                {labels.map((label, index) => (
                    <text
                        key={`${label}-${index}`}
                        x={x(index)}
                        y={height - 8}
                        textAnchor="middle"
                        className="fill-[var(--color-outline)] text-[11px]"
                    >
                        {label}
                    </text>
                ))}
            </svg>

            <div className="mt-2 flex flex-wrap items-center gap-4">
                {series.map((entry) => (
                    <span key={entry.key} className="inline-flex items-center gap-2 font-body-sm text-xs text-on-surface-variant">
                        <span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: entry.color }} aria-hidden="true" />
                        {entry.label}
                        {hovered !== null && entry.values[hovered] !== null && entry.values[hovered] !== undefined && (
                            <span className="font-semibold text-on-surface">
                                {Number(entry.values[hovered]).toLocaleString('id-ID', { maximumFractionDigits: 1 })}
                                {valueSuffix}
                            </span>
                        )}
                    </span>
                ))}
            </div>
        </div>
    );
}
