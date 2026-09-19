import React from 'react';
import { tPriority, tSeverity } from '@/react/lib/i18n';

const PILL_BASE = 'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold';

const TONES = {
    emerald: 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    amber: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    rose: 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    sky: 'bg-sky-50 text-sky-800 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40',
    slate: 'bg-surface-container text-on-surface-variant border-border-subtle',
};

const CONFIDENCE_TIERS = {
    high: { tone: 'emerald', label: 'Keyakinan tinggi' },
    medium: { tone: 'amber', label: 'Keyakinan sedang' },
    low: { tone: 'rose', label: 'Keyakinan rendah' },
};

/**
 * AI confidence indicator. Frames the result as an estimate, per product rules.
 * value: 0-1.
 */
export function ConfidenceBadge({ value, className = '' }) {
    const pct = Math.round((Number(value) || 0) * 100);
    const tier = pct >= 80 ? 'high' : pct >= 60 ? 'medium' : 'low';
    const { tone, label } = CONFIDENCE_TIERS[tier];

    return (
        <span className={`${PILL_BASE} ${TONES[tone]} ${className}`} title={`Keyakinan model: ${pct}%`}>
            <span className="material-symbols-outlined text-[14px]" aria-hidden="true">
                verified
            </span>
            {pct}% · {label}
        </span>
    );
}

const SEVERITY_TONE = { high: 'rose', moderate: 'amber', low: 'sky', unburned: 'emerald' };

/** Severity chip for a detected land issue. */
export function SeverityBadge({ severity, className = '' }) {
    const key = String(severity || '').toLowerCase();
    const tone = SEVERITY_TONE[key] ?? 'slate';

    return (
        <span className={`${PILL_BASE} ${TONES[tone]} ${className}`}>
            {key ? tSeverity(key) : 'Tidak diketahui'}
        </span>
    );
}

const PRIORITY_TONE = { high: 'rose', medium: 'amber', low: 'emerald', critical: 'rose' };
const PRIORITY_EXTRA = { critical: 'Kritis' };

/** Priority chip for a restoration recommendation. */
export function PriorityPill({ priority, className = '' }) {
    const key = String(priority || '').toLowerCase();
    const tone = PRIORITY_TONE[key] ?? 'slate';

    return (
        <span className={`${PILL_BASE} ${TONES[tone]} ${className}`}>
            {key ? tPriority(key, PRIORITY_EXTRA[key] ?? key) : '—'}
        </span>
    );
}

const HEALTH_TONE = { healthy: 'emerald', moderate: 'amber', degraded: 'rose', critical: 'rose' };
const HEALTH_LABELS = {
    healthy: 'Sehat',
    moderate: 'Sedang',
    degraded: 'Terdegradasi',
    critical: 'Kritis',
};

/** Land-health classification pill (Sehat / Sedang / Terdegradasi / Kritis). */
export function HealthStatusPill({ status, className = '' }) {
    const key = String(status || '').toLowerCase();
    const tone = HEALTH_TONE[key] ?? 'slate';

    return (
        <span className={`${PILL_BASE} ${TONES[tone]} ${className}`}>
            {HEALTH_LABELS[key] ?? (key || 'Tidak diketahui')}
        </span>
    );
}
