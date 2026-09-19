/**
 * The small surfaces the farmer screens are built from: a section panel, a
 * metric tile, a numeric change pill, a percentage bar, form controls and the
 * two states every page can be in (loading, failed).
 *
 * They exist so a page reads as content instead of repeating border/padding
 * classes, and so the two farmer pages that show the same number — a health
 * score, a vegetation delta — show it the same way.
 */
import React from 'react';
import { formatDelta, formatNumber } from '@/react/lib/format';
import { tDirection } from '@/react/lib/i18n';
import EmptyState from '@/react/components/shared/EmptyState';

const PANEL_BASE = 'rounded-xl border border-border-subtle bg-surface-container-lowest card-shadow';

/** A titled section of a page. */
export function Panel({ title, subtitle = null, icon = null, action = null, children, className = '', bodyClassName = 'p-5' }) {
    return (
        <section className={`${PANEL_BASE} ${className}`}>
            {(title || action) && (
                <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border-subtle px-5 py-4">
                    <div className="flex items-start gap-3">
                        {icon && (
                            <span className="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">
                                {icon}
                            </span>
                        )}
                        <div>
                            <h2 className="font-headline-sm text-headline-sm text-on-surface">{title}</h2>
                            {subtitle && <p className="mt-0.5 font-body-sm text-xs text-on-surface-variant">{subtitle}</p>}
                        </div>
                    </div>
                    {action}
                </header>
            )}
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

/** One measured number with its label. */
export function MetricTile({ label, value, unit = '', hint = null, tone = 'default', icon = null }) {
    const toneClass = { default: 'text-on-surface', success: 'text-success', critical: 'text-critical', muted: 'text-on-surface-variant' }[tone];

    return (
        <div className="rounded-xl border border-border-subtle bg-surface-container-low p-4">
            <div className="flex items-center justify-between gap-2">
                <span className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">{label}</span>
                {icon && (
                    <span className="material-symbols-outlined text-[18px] text-outline" aria-hidden="true">
                        {icon}
                    </span>
                )}
            </div>
            <p className={`mt-1 font-headline-md text-headline-md ${toneClass}`}>
                {value}
                {unit && <span className="ml-1 font-body-sm text-body-sm font-normal text-on-surface-variant">{unit}</span>}
            </p>
            {hint && <p className="mt-0.5 font-body-sm text-xs text-on-surface-variant">{hint}</p>}
        </div>
    );
}

const DIRECTION_TONES = {
    improving: 'text-success',
    declining: 'text-critical',
    stable: 'text-on-surface-variant',
};

const DIRECTION_ICONS = {
    improving: 'trending_up',
    declining: 'trending_down',
    stable: 'trending_flat',
};

/**
 * A change against the previous period: the number, and which way it moved.
 *
 * `unit` is " pp" (percentage points) by default because every delta the
 * analysis payload reports is one.
 */
export function DeltaPill({ delta, direction = null, unit = ' pp', label = null, className = '' }) {
    const tone = DIRECTION_TONES[direction] ?? 'text-on-surface-variant';
    const icon = DIRECTION_ICONS[direction] ?? 'remove';

    return (
        <span className={`inline-flex items-center gap-1 font-body-sm text-xs ${tone} ${className}`}>
            <span className="material-symbols-outlined text-[15px]" aria-hidden="true">
                {icon}
            </span>
            <span className="font-semibold">{formatDelta(delta, unit)}</span>
            <span className="text-on-surface-variant">{label ?? (direction ? tDirection(direction) : '')}</span>
        </span>
    );
}

/** A server-coloured chip: severity, land status, carbon verdict. */
export function Chip({ label, color = null, className = '' }) {
    if (!color) {
        return (
            <span className={`inline-flex items-center rounded-full border border-border-subtle bg-surface-container px-2.5 py-0.5 font-label-md text-xs uppercase tracking-wider text-on-surface-variant ${className}`}>
                {label}
            </span>
        );
    }

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 font-label-md text-xs font-semibold ${className}`}
            style={{ borderColor: `${color}55`, backgroundColor: `${color}1a`, color }}
        >
            <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} aria-hidden="true" />
            {label}
        </span>
    );
}

/** A proportion, on the colour the server sent with it. */
export function ShareBar({ percentage, color = '#01261f', className = '' }) {
    const width = Math.max(0, Math.min(100, Number(percentage) || 0));

    return (
        <span className={`block h-2 w-full overflow-hidden rounded-full bg-surface-container ${className}`}>
            <span className="block h-full rounded-full" style={{ width: `${width}%`, backgroundColor: color }} />
        </span>
    );
}

const BUTTON_VARIANTS = {
    primary: 'bg-primary text-on-primary hover:opacity-90',
    secondary: 'border border-border-subtle bg-surface-container-lowest text-on-surface hover:bg-surface-container-low',
    ghost: 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface',
    danger: 'border border-critical/40 bg-surface-container-lowest text-critical hover:bg-critical/10',
};

export function Button({ variant = 'primary', icon = null, type = 'button', className = '', children, ...rest }) {
    return (
        <button
            type={type}
            className={`inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 font-body-sm text-body-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${BUTTON_VARIANTS[variant]} ${className}`}
            {...rest}
        >
            {icon && (
                <span className="material-symbols-outlined text-[18px]" aria-hidden="true">
                    {icon}
                </span>
            )}
            {children}
        </button>
    );
}

const CONTROL_CLASS =
    'w-full rounded-lg border border-border-subtle bg-surface-container-lowest px-3 py-2 font-body-sm text-body-sm text-on-surface outline-none transition-colors focus:border-primary disabled:bg-surface-container-low';

export function Field({ label, htmlFor, error = null, hint = null, required = false, children, className = '' }) {
    return (
        <label htmlFor={htmlFor} className={`block ${className}`}>
            <span className="mb-1 block font-label-md text-label-md text-on-surface-variant">
                {label}
                {required && <span className="ml-0.5 text-critical">*</span>}
            </span>
            {children}
            {hint && !error && <span className="mt-1 block font-body-sm text-xs text-on-surface-variant">{hint}</span>}
            {error && <span className="mt-1 block font-body-sm text-xs text-critical">{error}</span>}
        </label>
    );
}

export function TextInput({ className = '', ...rest }) {
    return <input className={`${CONTROL_CLASS} ${className}`} {...rest} />;
}

export function TextArea({ className = '', rows = 3, ...rest }) {
    return <textarea rows={rows} className={`${CONTROL_CLASS} ${className}`} {...rest} />;
}

export function SelectInput({ options = [], placeholder = null, className = '', ...rest }) {
    return (
        <select className={`${CONTROL_CLASS} ${className}`} {...rest}>
            {placeholder && <option value="">{placeholder}</option>}
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

/** A definition-list row, for reading one value off the payload. */
export function DetailRow({ label, value, hint = null }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-border-subtle py-2 last:border-b-0">
            <dt className="font-body-sm text-body-sm text-on-surface-variant">{label}</dt>
            <dd className="text-right font-body-sm text-body-sm font-medium text-on-surface">
                {value ?? '—'}
                {hint && <span className="ml-1 font-normal text-on-surface-variant">{hint}</span>}
            </dd>
        </div>
    );
}

/** The whole-page loading state. */
export function LoadingBlock({ label = 'Memuat data…' }) {
    return (
        <div className="flex items-center justify-center gap-2 rounded-xl border border-dashed border-border-subtle bg-surface-container-lowest px-6 py-12">
            <span className="h-4 w-4 animate-spin rounded-full border-2 border-border-subtle border-t-primary" aria-hidden="true" />
            <span className="font-body-sm text-body-sm text-on-surface-variant">{label}</span>
        </div>
    );
}

/** The whole-page failure state, with a way back. */
export function ErrorPanel({ message, onRetry = null }) {
    return (
        <EmptyState
            icon="cloud_off"
            title="Data tidak bisa dimuat"
            description={message}
            action={
                onRetry ? (
                    <Button variant="secondary" icon="refresh" onClick={onRetry} className="mt-2">
                        Coba lagi
                    </Button>
                ) : null
            }
        />
    );
}

/** A count badge for section headers. */
export function CountBadge({ value, label = null }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-border-subtle bg-surface-container-low px-2.5 py-0.5 font-label-md text-label-md text-on-surface-variant">
            {formatNumber(value)}
            {label && <span className="font-normal">{label}</span>}
        </span>
    );
}
