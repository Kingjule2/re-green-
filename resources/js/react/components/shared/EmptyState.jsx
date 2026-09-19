import React from 'react';

/**
 * The one shared empty/placeholder surface, so "nothing here yet" always looks
 * like the same message instead of a blank panel.
 */
export default function EmptyState({ icon = 'eco', title, description, action = null, className = '' }) {
    return (
        <div
            className={`flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border-subtle bg-surface-container-lowest px-6 py-10 text-center ${className}`}
        >
            <span className="material-symbols-outlined text-[32px] text-outline" aria-hidden="true">
                {icon}
            </span>
            <p className="font-headline-sm text-headline-sm text-on-surface">{title}</p>
            {description && <p className="max-w-md font-body-sm text-body-sm text-on-surface-variant">{description}</p>}
            {action}
        </div>
    );
}
