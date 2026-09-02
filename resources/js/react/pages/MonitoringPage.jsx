import React, { useState, useRef, useEffect } from 'react';
import StatusBadge from '../components/shared/StatusBadge';
import { gsap } from '@/gsap';

export default function MonitoringPage() {
    const [isSyncing, setIsSyncing] = useState(false);
    const [syncTime, setSyncTime] = useState('Just now');
    const pageRef = useRef(null);

    useEffect(() => {
        if (pageRef.current) {
            gsap.fromTo(
                pageRef.current.querySelectorAll('.stagger-card'),
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.45, stagger: 0.07, ease: 'power2.out' }
            );
        }
    }, []);

    const handleForceSync = () => {
        setIsSyncing(true);
        setTimeout(() => {
            setIsSyncing(false);
            setSyncTime('Synced just now');
        }, 1200);
    };

    return (
        <div ref={pageRef} className="space-y-8 animate-in fade-in duration-300">
            {/* Header */}
            <div className="stagger-card flex flex-col md:flex-row justify-between items-start md:items-end gap-4 pb-2">
                <div>
                    <h2 className="font-headline-xl text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                        Live Monitoring & Sensor Telemetry
                    </h2>
                    <p className="font-body-lg text-sm md:text-base text-on-surface-variant mt-1 flex items-center gap-2">
                        <span className="w-2.5 h-2.5 rounded-full bg-success animate-pulse" aria-hidden="true"></span>
                        <span>Connected to IoT Gateway Alpha • Real-time telemetry from Sector B ({syncTime})</span>
                    </p>
                </div>
                <button
                    onClick={handleForceSync}
                    disabled={isSyncing}
                    className="bg-primary-container text-white border-none rounded-lg px-4 py-2.5 font-label-md text-xs font-semibold flex items-center gap-2 hover:bg-primary transition-all shadow-xs cursor-pointer disabled:opacity-75"
                >
                    <span className={`material-symbols-outlined text-[18px] ${isSyncing ? 'animate-spin' : ''}`}>
                        refresh
                    </span>
                    <span>{isSyncing ? 'Synchronizing...' : 'Force Sync'}</span>
                </button>
            </div>

            {/* Bento Grid Layout */}
            <div className="grid grid-cols-1 md:grid-cols-12 gap-6">
                {/* 1. Live Sensor Data Feeds (8 Cols) */}
                <div className="stagger-card md:col-span-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    {/* Soil Moisture */}
                    <div className="bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between hover:border-info/40 transition-colors">
                        <div className="flex justify-between items-start">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase tracking-widest font-semibold">
                                Soil Moisture
                            </span>
                            <span className="material-symbols-outlined text-info text-[22px]">water_drop</span>
                        </div>
                        <div className="my-4">
                            <div className="font-stats-lg text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                                42.8%
                            </div>
                            <div className="font-body-sm text-xs text-success flex items-center gap-1 mt-1 font-semibold">
                                <span className="material-symbols-outlined text-[16px]">trending_up</span>
                                +1.2% from baseline
                            </div>
                        </div>
                        {/* Sparkline */}
                        <div className="w-full h-10 border-b border-border-subtle relative pt-2">
                            <svg className="w-full h-full stroke-info fill-none stroke-[2.5px]" viewBox="0 0 100 20">
                                <path d="M0,15 L20,10 L40,12 L60,5 L80,8 L100,2" />
                            </svg>
                        </div>
                    </div>

                    {/* Ambient Temp */}
                    <div className="bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between hover:border-warning/40 transition-colors">
                        <div className="flex justify-between items-start">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase tracking-widest font-semibold">
                                Ambient Temp
                            </span>
                            <span className="material-symbols-outlined text-warning text-[22px]">thermostat</span>
                        </div>
                        <div className="my-4">
                            <div className="font-stats-lg text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                                28.4°C
                            </div>
                            <div className="font-body-sm text-xs text-critical flex items-center gap-1 mt-1 font-semibold">
                                <span className="material-symbols-outlined text-[16px]">warning</span>
                                +2.1°C vs norm
                            </div>
                        </div>
                        {/* Sparkline */}
                        <div className="w-full h-10 border-b border-border-subtle relative pt-2">
                            <svg className="w-full h-full stroke-warning fill-none stroke-[2.5px]" viewBox="0 0 100 20">
                                <path d="M0,10 L20,12 L40,8 L60,15 L80,5 L100,2" />
                            </svg>
                        </div>
                    </div>

                    {/* Humidity */}
                    <div className="bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between hover:border-secondary/40 transition-colors">
                        <div className="flex justify-between items-start">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase tracking-widest font-semibold">
                                Air Humidity
                            </span>
                            <span className="material-symbols-outlined text-secondary text-[22px]">air</span>
                        </div>
                        <div className="my-4">
                            <div className="font-stats-lg text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                                64%
                            </div>
                            <div className="font-body-sm text-xs text-on-surface-variant flex items-center gap-1 mt-1 font-semibold">
                                <span className="material-symbols-outlined text-[16px] text-success">check_circle</span>
                                Stable & nominal
                            </div>
                        </div>
                        {/* Sparkline */}
                        <div className="w-full h-10 border-b border-border-subtle relative pt-2">
                            <svg className="w-full h-full stroke-secondary fill-none stroke-[2.5px]" viewBox="0 0 100 20">
                                <path d="M0,10 L20,10 L40,9 L60,11 L80,10 L100,10" />
                            </svg>
                        </div>
                    </div>
                </div>

                {/* 2. Circular Recovery Gauge (4 Cols) */}
                <div className="stagger-card md:col-span-4 bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col items-center justify-center text-center">
                    <span className="font-label-md text-xs text-on-surface-variant uppercase tracking-widest self-start font-semibold mb-2">
                        Vegetation Recovery Index
                    </span>
                    <div className="relative w-36 h-36 my-2 flex items-center justify-center">
                        <svg className="circular-chart w-full h-full" viewBox="0 0 36 36">
                            <path
                                className="circle-bg"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                            />
                            <path
                                className="circle stroke-success"
                                strokeDasharray="78, 100"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                            />
                        </svg>
                        <div className="absolute inset-0 flex flex-col items-center justify-center">
                            <span className="font-stats-lg text-2xl font-bold text-primary dark:text-primary-fixed">
                                78%
                            </span>
                            <span className="text-[10px] text-outline uppercase font-semibold">NDVI Index</span>
                        </div>
                    </div>
                    <p className="font-body-sm text-xs text-on-surface-variant font-medium">
                        Target: <span className="font-bold text-primary">85% by Q4 2026</span>
                    </p>
                </div>

                {/* 3. Drone / Satellite Imagery Gallery (8 Cols) */}
                <div className="stagger-card md:col-span-8 bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col">
                    <div className="flex justify-between items-center mb-4">
                        <div>
                            <span className="font-label-md text-xs text-on-surface-variant uppercase tracking-widest font-semibold block">
                                Recent Aerial & NDVI Scans
                            </span>
                            <p className="text-xs text-outline">Autonomous drone passes across Kalimantan Sector Alpha</p>
                        </div>
                        <button
                            onClick={() => alert('Opening full aerial archives')}
                            className="text-primary hover:underline font-label-md text-xs font-bold"
                        >
                            View Archives
                        </button>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
                        {/* Scan 1 */}
                        <div className="relative group overflow-hidden rounded-lg border border-border-subtle shadow-xs">
                            <div
                                className="bg-cover bg-center w-full h-32 transition-transform duration-500 group-hover:scale-110"
                                style={{
                                    backgroundImage: `url('https://lh3.googleusercontent.com/aida-public/AB6AXuApLVzoJNvwpzX20J_pE8WjXbYIuaGsZ2icBcwd0i3LJFvDmPSlbbTQeEYjkFZV7ZPQ8R0G1nI9cNw_N3FfHrStJfAXWjqJBShDkTs6rAEyM-bz-3JmmdJkUubtHaC0kz4oEpq3z0sRKb-FtW6slzRu7hJAcv0M_Ub7lhYm-xt051N-TOj0gBbb56g58opnjIYoVpDCw4oU796Fb8l9DV-M5u-3MGnM-fPywqHR6JckQZXe8JVqXh-4')`,
                                }}
                            />
                            <div className="absolute bottom-0 left-0 w-full bg-gradient-to-t from-charcoal/90 via-charcoal/40 to-transparent p-2 text-white">
                                <span className="font-label-md text-[10px] flex items-center gap-1 font-semibold">
                                    <span className="material-symbols-outlined text-[13px]">flight</span>
                                    10:45 AM • Canopy
                                </span>
                            </div>
                        </div>

                        {/* Scan 2 */}
                        <div className="relative group overflow-hidden rounded-lg border border-border-subtle shadow-xs">
                            <div
                                className="bg-cover bg-center w-full h-32 transition-transform duration-500 group-hover:scale-110"
                                style={{
                                    backgroundImage: `url('https://lh3.googleusercontent.com/aida-public/AB6AXuBRIfB99xq6iTYzJbmsap04SaozoTZg5Y4tr2tObQtXwOMgIl_XKroBvwMR7ZAwvmUBvLMmM8cNXmss8JxwBTjqfzaLhoKA74FPOCbUZbuviahwF4s_bTjcgiHGzrNChd-1izszM-NHhHvlwAjtcoAIyFjjrw1c1ZRYwGQgjbQVOT1mrqQlbICIjdJOPoE3HkxzqWSNmZFc6RimhTYyW7V3jGmvybOWn1x4LDAuAnPqTUjMKEqVxTz5')`,
                                }}
                            />
                            <div className="absolute bottom-0 left-0 w-full bg-gradient-to-t from-charcoal/90 via-charcoal/40 to-transparent p-2 text-white">
                                <span className="font-label-md text-[10px] flex items-center gap-1 font-semibold">
                                    <span className="material-symbols-outlined text-[13px]">satellite</span>
                                    08:00 AM • NDVI
                                </span>
                            </div>
                        </div>

                        {/* Scan 3 */}
                        <div className="relative group overflow-hidden rounded-lg border border-border-subtle shadow-xs">
                            <div
                                className="bg-cover bg-center w-full h-32 transition-transform duration-500 group-hover:scale-110"
                                style={{
                                    backgroundImage: `url('https://lh3.googleusercontent.com/aida-public/AB6AXuCmYclg098wRHehaJn8Ex4fbZyX5EzDtNKWq8TSdIHm1z8WUEc_D7H6g-GABH7rEWeuCO3VNcTzvEDV7LKb5Qpr0OXWgsbziY0hvq9M6Eo2GzKKn9OETt3fRGJ0UMqvnP6n1zGkt0sarmCJ1_yDyweuFcrPIlrZ7CbNRF4-wPwQ1jCy015AGALDWXNyLafTORQiEU4O_bRmEK6Y8uc5GBCnKVG-noeMDApuCSuQ1NpYOtwdcZohd4Hz')`,
                                }}
                            />
                            <div className="absolute bottom-0 left-0 w-full bg-gradient-to-t from-charcoal/90 via-charcoal/40 to-transparent p-2 text-white">
                                <span className="font-label-md text-[10px] flex items-center gap-1 font-semibold">
                                    <span className="material-symbols-outlined text-[13px]">landscape</span>
                                    Yesterday • GIS Grid
                                </span>
                            </div>
                        </div>

                        {/* Scan 4 */}
                        <div className="relative group overflow-hidden rounded-lg border border-border-subtle shadow-xs">
                            <div
                                className="bg-cover bg-center w-full h-32 transition-transform duration-500 group-hover:scale-110"
                                style={{
                                    backgroundImage: `url('https://lh3.googleusercontent.com/aida-public/AB6AXuApLVzoJNvwpzX20J_pE8WjXbYIuaGsZ2icBcwd0i3LJFvDmPSlbbTQeEYjkFZV7ZPQ8R0G1nI9cNw_N3FfHrStJfAXWjqJBShDkTs6rAEyM-bz-3JmmdJkUubtHaC0kz4oEpq3z0sRKb-FtW6slzRu7hJAcv0M_Ub7lhYm-xt051N-TOj0gBbb56g58opnjIYoVpDCw4oU796Fb8l9DV-M5u-3MGnM-fPywqHR6JckQZXe8JVqXh-4')`,
                                }}
                            />
                            <div className="absolute bottom-0 left-0 w-full bg-gradient-to-t from-charcoal/90 via-charcoal/40 to-transparent p-2 text-white">
                                <span className="font-label-md text-[10px] flex items-center gap-1 font-semibold">
                                    <span className="material-symbols-outlined text-[13px]">radar</span>
                                    Hydrology Thermal
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* 4. AI Diagnostics Panel (4 Cols) */}
                <div className="stagger-card md:col-span-4 bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between">
                    <div>
                        <div className="flex justify-between items-center mb-4 border-b border-border-subtle pb-3">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase tracking-widest flex items-center gap-2 font-bold">
                                <span className="material-symbols-outlined text-primary text-[18px]">memory</span>
                                AI Diagnostics & Alerts
                            </span>
                            <span className="px-2 py-0.5 bg-rose-100 text-rose-800 rounded-full font-label-md text-[10px] font-bold">
                                2 Active
                            </span>
                        </div>

                        <div className="space-y-3">
                            {/* Alert 1 */}
                            <div className="p-3 border border-rose-200 bg-rose-50/50 rounded-lg flex gap-3 items-start">
                                <span className="material-symbols-outlined text-critical text-[20px] mt-0.5">warning</span>
                                <div className="min-w-0 flex-1">
                                    <h4 className="font-label-md text-xs font-bold text-on-surface">Anomalous Temp Rise</h4>
                                    <p className="font-body-sm text-[11px] text-on-surface-variant mt-0.5">
                                        Sector B continuous +2.1°C elevation above predictive baseline for 4h.
                                    </p>
                                    <span className="font-label-md text-[10px] text-outline mt-1.5 block">10m ago • Sensor T-42</span>
                                </div>
                            </div>

                            {/* Alert 2 */}
                            <div className="p-3 border border-amber-200 bg-amber-50/50 rounded-lg flex gap-3 items-start">
                                <span className="material-symbols-outlined text-amber-600 text-[20px] mt-0.5">science</span>
                                <div className="min-w-0 flex-1">
                                    <h4 className="font-label-md text-xs font-bold text-on-surface">Soil pH Deviation</h4>
                                    <p className="font-body-sm text-[11px] text-on-surface-variant mt-0.5">
                                        Expected 6.5. Recorded 5.8 near watercourse Alpha.
                                    </p>
                                    <span className="font-label-md text-[10px] text-outline mt-1.5 block">1h ago • Sensor P-12</span>
                                </div>
                            </div>

                            {/* Alert 3 (Nominal) */}
                            <div className="p-3 border border-emerald-200 bg-emerald-50/30 rounded-lg flex gap-3 items-start">
                                <span className="material-symbols-outlined text-emerald-600 text-[20px] mt-0.5">check_circle</span>
                                <div className="min-w-0 flex-1">
                                    <h4 className="font-label-md text-xs font-bold text-on-surface">Canopy Density Nominal</h4>
                                    <p className="font-body-sm text-[11px] text-on-surface-variant mt-0.5">
                                        Satellite NDVI growth curve fully matches expected trajectory.
                                    </p>
                                    <span className="font-label-md text-[10px] text-outline mt-1.5 block">4h ago • Sat-Link</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
