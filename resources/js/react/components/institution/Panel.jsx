/**
 * The card every aggregate page is built from: a titled surface with an
 * optional line of context and an action slot on the right.
 *
 * It exists so the dashboard, map, reports and carbon pages all frame their
 * content identically instead of each inventing its own heading treatment.
 */
import React from 'react';

export default function Panel({
    title,
    description = null,
    actions = null,
    icon = null,
    children,
    className = '',
    bodyClassName = '',
}) {
    return (
        <section
            className={`rounded-xl border border-border-subtle bg-surface-container-lowest p-5 card-shadow ${className}`}
        >
            {(title || actions) && (
                <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                            {icon && (
                                <span className="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">
                                    {icon}
                                </span>
                            )}
                            {title}
                        </h2>
                        {description && (
                            <p className="mt-1 max-w-3xl font-body-sm text-body-sm text-on-surface-variant">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </header>
            )}

            <div className={bodyClassName}>{children}</div>
        </section>
    );
}
