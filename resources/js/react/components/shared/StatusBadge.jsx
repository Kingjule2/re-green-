import React from 'react';

/**
 * Status badge.
 *
 * The API sends the label and colour with the row (so severity, land status and
 * carbon eligibility all agree with the report that quotes them); when a caller
 * only has a free-form status string, the keyword mapping below still gives it a
 * sensible tone.
 */
export default function StatusBadge({ status, text, label, color = null, tone = null, className = '' }) {
    const key = String(status ?? text ?? '').toLowerCase();

    if (color) {
        return (
            <span
                className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider ${className}`}
                style={{ borderColor: `${color}55`, backgroundColor: `${color}1a`, color }}
                role="status"
            >
                <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} aria-hidden="true" />
                {label ?? text ?? status}
            </span>
        );
    }

    let colorStyles = 'bg-surface-container text-on-surface-variant border-border-subtle';
    let resolved = label ?? text ?? status;

    if (tone === 'positive' || key.includes('healthy') || key.includes('sehat') || key.includes('good') || key.includes('pulih') || key.includes('active')) {
        colorStyles = 'bg-emerald-50 text-emerald-800 border-emerald-200';
        resolved = resolved ?? 'Sehat';
    } else if (tone === 'warning' || key.includes('attention') || key.includes('medium') || key.includes('sedang') || key.includes('warning')) {
        colorStyles = 'bg-amber-50 text-amber-800 border-amber-200';
        resolved = resolved ?? 'Perlu perhatian';
    } else if (tone === 'critical' || key.includes('critical') || key.includes('kritis') || key.includes('error') || key.includes('high risk')) {
        colorStyles = 'bg-rose-50 text-rose-800 border-rose-200';
        resolved = resolved ?? 'Kritis';
    } else if (key.includes('planned') || key.includes('terdaftar') || key.includes('info') || key.includes('draft')) {
        colorStyles = 'bg-sky-50 text-sky-800 border-sky-200';
        resolved = resolved ?? 'Terdaftar';
    }

    return (
        <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider border ${colorStyles} ${className}`}
            role="status"
        >
            {resolved}
        </span>
    );
}
