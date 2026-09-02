import React from 'react';

/**
 * Status badge with accessible colors and high contrast.
 * Types: 'healthy' | 'warning' | 'critical' | 'info' | 'low-risk' | string
 */
export default function StatusBadge({ status, text, className = '' }) {
    const s = (status || text || '').toLowerCase();

    let colorStyles = 'bg-surface-container text-on-surface-variant border-border-subtle';
    let label = text || status;

    if (s.includes('healthy') || s.includes('good') || s.includes('low risk') || s.includes('active') || s.includes('nominal')) {
        colorStyles = 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40';
        label = label || 'Healthy';
    } else if (s.includes('attention') || s.includes('warning') || s.includes('medium') || s.includes('deviation')) {
        colorStyles = 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40';
        label = label || 'Needs Attention';
    } else if (s.includes('critical') || s.includes('error') || s.includes('high risk') || s.includes('alert')) {
        colorStyles = 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40';
        label = label || 'Critical';
    } else if (s.includes('planned') || s.includes('info') || s.includes('draft')) {
        colorStyles = 'bg-sky-50 text-sky-800 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40';
        label = label || 'Planned';
    }

    return (
        <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider border ${colorStyles} ${className}`}
            role="status"
        >
            {label}
        </span>
    );
}
