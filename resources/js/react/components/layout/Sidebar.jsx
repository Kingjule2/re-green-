import React from 'react';

const NAV_ITEMS = [
    { id: 'overview', label: 'Overview', icon: 'dashboard' },
    { id: 'restoration-projects', label: 'Restoration Projects', icon: 'forest' },
    { id: 'land-intelligence', label: 'Land Intelligence', icon: 'map' },
    { id: 'monitoring', label: 'Monitoring', icon: 'visibility' },
    { id: 'community', label: 'Community', icon: 'groups' },
    { id: 'impact-reports', label: 'Impact Reports', icon: 'assessment' },
];

export default function Sidebar({
    activePage,
    onNavigate,
    mobileOpen,
    onCloseMobile,
}) {
    return (
        <>
            {/* Mobile Backdrop */}
            {mobileOpen && (
                <div
                    className="fixed inset-0 bg-charcoal/50 backdrop-blur-xs z-30 md:hidden"
                    onClick={onCloseMobile}
                    aria-hidden="true"
                />
            )}

            {/* Sidebar Shell */}
            <aside
                className={`fixed top-0 left-0 h-full w-[260px] bg-surface dark:bg-charcoal border-r border-border-subtle dark:border-outline-variant flex flex-col py-base z-40 transition-transform duration-300 md:translate-x-0 ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
                aria-label="Main Navigation"
            >
                {/* Brand Header */}
                <div className="px-6 py-4 mb-4 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="w-3 h-3 rounded-full bg-success"></span>
                            <h1 className="font-headline-md text-headline-md font-bold text-primary dark:text-primary-fixed tracking-tight">
                                RE:GREEN
                            </h1>
                        </div>
                        <p className="font-body-sm text-xs text-outline dark:text-outline-variant mt-0.5">
                            Smart Restoration Intelligence
                        </p>
                    </div>
                    <button
                        className="md:hidden p-1 text-on-surface-variant hover:text-primary"
                        onClick={onCloseMobile}
                        aria-label="Close navigation menu"
                    >
                        <span className="material-symbols-outlined">close</span>
                    </button>
                </div>

                {/* Nav Links */}
                <div className="flex-1 overflow-y-auto w-full px-2">
                    <ul className="flex flex-col gap-1 w-full" role="menubar">
                        {NAV_ITEMS.map((item) => {
                            const isActive = activePage === item.id;
                            return (
                                <li key={item.id} role="none">
                                    <button
                                        role="menuitem"
                                        onClick={() => {
                                            onNavigate(item.id);
                                            onCloseMobile?.();
                                        }}
                                        className={`flex items-center gap-3.5 px-4 py-3 w-full rounded-lg text-left font-label-md text-sm font-semibold transition-all duration-150 ${
                                            isActive
                                                ? 'bg-surface-container text-primary dark:bg-primary-container dark:text-on-primary-container shadow-xs font-bold border-l-4 border-primary dark:border-primary-fixed'
                                                : 'text-on-surface-variant hover:bg-surface-container-low dark:hover:bg-surface-variant hover:text-primary dark:hover:text-primary-fixed'
                                        }`}
                                    >
                                        <span
                                            className={`material-symbols-outlined text-[20px] ${
                                                isActive ? 'text-primary dark:text-primary-fixed' : 'text-outline'
                                            }`}
                                            data-weight={isActive ? 'fill' : undefined}
                                            aria-hidden="true"
                                        >
                                            {item.icon}
                                        </span>
                                        <span>{item.label}</span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </div>

                {/* Footer Utility Links */}
                <div className="mt-auto px-4 py-3 border-t border-border-subtle dark:border-outline-variant">
                    <ul className="flex flex-col gap-1 w-full">
                        <li>
                            <button
                                onClick={() => {
                                    alert('Settings panel modal (prototype)');
                                }}
                                className="flex items-center gap-3 px-3 py-2 text-on-surface-variant hover:bg-surface-container-low rounded-lg transition-colors w-full font-label-md text-xs"
                            >
                                <span className="material-symbols-outlined text-[18px]">settings</span>
                                <span>Settings</span>
                            </button>
                        </li>
                        <li>
                            <button
                                onClick={() => {
                                    alert('Documentation & Help Center');
                                }}
                                className="flex items-center gap-3 px-3 py-2 text-on-surface-variant hover:bg-surface-container-low rounded-lg transition-colors w-full font-label-md text-xs"
                            >
                                <span className="material-symbols-outlined text-[18px]">help</span>
                                <span>Help Center</span>
                            </button>
                        </li>
                        <li className="pt-2">
                            <div className="flex items-center gap-3 px-3 py-2 rounded-lg bg-surface-container-low border border-border-subtle">
                                <img
                                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuBp9wzcs-mQdJbIrFVYU8e8eeCYno8C7YkHykq1LwI2ukbvWXNAVk0AVkLPIm5GVsi-5lMm-wAPxV1Zsn7TBEZCGT16ayMHXXAnIIXmSDyWDowkT9DtG48Yu_qVfmUklQuX1wVe8Pn4PlhPOYurmJvzXG766nOJm2zRl3bwbYEnGqYILat0KDVVboLuxuiTuWtx99E7Rw7pPcLG6Mcm3hPpdrsvOIgi3rw_0oYgwzdnSlrNfuzBrl6o"
                                    alt="Alex Morgan"
                                    className="w-8 h-8 rounded-full object-cover border border-border-subtle"
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="font-body-sm text-xs font-bold text-on-surface truncate">
                                        Alex Morgan
                                    </p>
                                    <p className="font-label-md text-[10px] text-outline truncate">
                                        Lead Ecologist
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </aside>
        </>
    );
}
