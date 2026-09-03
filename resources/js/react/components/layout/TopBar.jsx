import React, { useState } from 'react';

export default function TopBar({
    pageTitle,
    onOpenMobile,
    selectedProject,
    onSelectProject,
    darkMode,
    onToggleDarkMode,
}) {
    const [searchQuery, setSearchQuery] = useState('');
    const [showNotifications, setShowNotifications] = useState(false);

    return (
        <header className="sticky top-0 bg-surface dark:bg-charcoal border-b border-border-subtle dark:border-outline-variant flex justify-between items-center h-16 px-4 md:px-margin-desktop z-20 shrink-0">
            {/* Left Header Info */}
            <div className="flex items-center gap-3">
                <button
                    onClick={onOpenMobile}
                    className="md:hidden p-2 text-on-surface-variant hover:text-primary rounded-lg hover:bg-surface-container transition-colors"
                    aria-label="Open Navigation Menu"
                >
                    <span className="material-symbols-outlined">menu</span>
                </button>

                <h2 className="font-headline-lg text-xl md:text-2xl font-bold text-primary dark:text-primary-fixed">
                    {pageTitle}
                </h2>

                {/* Project Selector */}
                <div className="hidden lg:flex items-center gap-2 ml-6 pl-6 border-l border-border-subtle">
                    <span className="material-symbols-outlined text-outline text-[18px]">folder_open</span>
                    <select
                        value={selectedProject}
                        onChange={(e) => onSelectProject(e.target.value)}
                        className="bg-transparent border-none text-on-surface-variant font-label-md text-xs font-semibold focus:ring-0 cursor-pointer py-1 pr-6"
                        aria-label="Active Restoration Project"
                    >
                        <option value="kalimantan">Kalimantan Restoration Project (Sector Alpha)</option>
                        <option value="sumatra">Sumatra Peatland Revival (Zone 1)</option>
                        <option value="riau">Riau Mangrove & Peatland Sanctuary</option>
                    </select>
                </div>
            </div>

            {/* Right Action Icons */}
            <div className="flex items-center gap-3 md:gap-4">
                {/* Search Bar */}
                <div className="hidden sm:flex items-center bg-surface-container-lowest dark:bg-surface-container border border-border-subtle rounded-full px-3.5 py-1.5 focus-within:border-primary transition-all">
                    <span className="material-symbols-outlined text-outline text-sm mr-2">search</span>
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Search params, sectors..."
                        className="bg-transparent border-none focus:ring-0 text-body-sm font-body-sm p-0 w-36 md:w-52 text-on-surface placeholder:text-outline outline-none"
                        aria-label="Search projects, sectors and parameters"
                    />
                </div>

                {/* Dark Mode Toggle */}
                <button
                    onClick={onToggleDarkMode}
                    className="p-2 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-full transition-colors relative"
                    title={darkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'}
                    aria-label="Toggle Dark Mode"
                >
                    <span className="material-symbols-outlined text-[20px]">
                        {darkMode ? 'light_mode' : 'dark_mode'}
                    </span>
                </button>

                {/* Notifications */}
                <div className="relative">
                    <button
                        onClick={() => setShowNotifications(!showNotifications)}
                        className="p-2 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-full transition-colors relative"
                        aria-label="Notifications"
                    >
                        <span className="material-symbols-outlined text-[20px]">notifications</span>
                        <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-critical rounded-full animate-pulse"></span>
                    </button>

                    {showNotifications && (
                        <div className="absolute right-0 mt-2 w-80 bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xl p-4 z-50 animate-in fade-in slide-in-from-top-2">
                            <div className="flex items-center justify-between pb-2 border-b border-border-subtle mb-3">
                                <h4 className="font-label-md text-xs font-bold uppercase tracking-wider text-primary">
                                    Live Alerts (2)
                                </h4>
                                <span className="text-[10px] text-critical font-semibold bg-rose-50 px-2 py-0.5 rounded-full">
                                    Action Required
                                </span>
                            </div>
                            <div className="space-y-2.5 text-xs">
                                <div className="p-2 rounded bg-amber-50/50 border border-amber-200/50">
                                    <div className="font-semibold text-amber-900 flex items-center gap-1.5">
                                        <span className="material-symbols-outlined text-amber-600 text-[14px]">warning</span>
                                        Sector B Temp Spike (+2.1°C)
                                    </div>
                                    <p className="text-amber-700 text-[11px] mt-0.5">Telemetry sensor T-42 reported 10 mins ago.</p>
                                </div>
                                <div className="p-2 rounded bg-emerald-50/50 border border-emerald-200/50">
                                    <div className="font-semibold text-emerald-900 flex items-center gap-1.5">
                                        <span className="material-symbols-outlined text-emerald-600 text-[14px]">check_circle</span>
                                        Sat-Link NDVI Scan Synchronized
                                    </div>
                                    <p className="text-emerald-700 text-[11px] mt-0.5">Sector Alpha canopy coverage refreshed.</p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Avatar */}
                <div className="w-8 h-8 rounded-full overflow-hidden border border-border-subtle">
                    <img
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuBp9wzcs-mQdJbIrFVYU8e8eeCYno8C7YkHykq1LwI2ukbvWXNAVk0AVkLPIm5GVsi-5lMm-wAPxV1Zsn7TBEZCGT16ayMHXXAnIIXmSDyWDowkT9DtG48Yu_qVfmUklQuX1wVe8Pn4PlhPOYurmJvzXG766nOJm2zRl3bwbYEnGqYILat0KDVVboLuxuiTuWtx99E7Rw7pPcLG6Mcm3hPpdrsvOIgi3rw_0oYgwzdnSlrNfuzBrl6o"
                        alt="Profile"
                        className="w-full h-full object-cover"
                    />
                </div>
            </div>
        </header>
    );
}
