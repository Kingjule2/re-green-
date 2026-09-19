/**
 * Burn-severity mix as a single stacked bar.
 *
 * The server sends the four bands with their labels and colours
 * (`severity_mix: [{level, label, color, count}]`); the bar only orders and
 * scales them, so the colour a stakeholder sees here is the same colour the map
 * pin and the report legend use.
 */
import React from 'react';
import { formatNumber } from '@/react/lib/format';
import { tSeverity } from '@/react/lib/i18n';

/**
 * @param {object} props
 * @param {Array<{level: string, label: string, color: string, count: number}>} props.mix
 */
export default function SeverityMixBar({ mix = [], className = '', title = null }) {
    const total = mix.reduce((sum, band) => sum + Number(band.count ?? 0), 0);

    if (mix.length === 0) {
        return null;
    }

    return (
        <div className={className}>
            {title && (
                <h3 className="mb-3 font-headline-sm text-headline-sm text-on-surface">{title}</h3>
            )}

            {total === 0 ? (
                <p className="rounded-lg border border-dashed border-border-subtle px-4 py-3 font-body-sm text-body-sm text-on-surface-variant">
                    Belum ada lahan dengan hasil analisis keparahan.
                </p>
            ) : (
                <>
                    <div
                        className="flex h-4 w-full overflow-hidden rounded-full bg-surface-container"
                        role="img"
                        aria-label={`Komposisi keparahan dari ${formatNumber(total)} lahan beranalisis`}
                    >
                        {mix
                            .filter((band) => Number(band.count ?? 0) > 0)
                            .map((band) => (
                                <span
                                    key={band.level}
                                    className="h-full"
                                    style={{
                                        width: `${(Number(band.count) / total) * 100}%`,
                                        backgroundColor: band.color,
                                    }}
                                    title={`${band.label}: ${band.count}`}
                                />
                            ))}
                    </div>

                    <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                        {mix.map((band) => (
                            <li key={band.level} className="flex items-center justify-between gap-3">
                                <span className="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
                                    <span
                                        className="h-2.5 w-2.5 shrink-0 rounded-sm"
                                        style={{ backgroundColor: band.color }}
                                        aria-hidden="true"
                                    />
                                    {tSeverity(band.level, band.label)}
                                </span>
                                <span className="font-body-sm text-body-sm font-semibold tabular-nums text-on-surface">
                                    {formatNumber(band.count)}
                                    <span className="ml-1 font-normal text-on-surface-variant">
                                        lahan
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
}
