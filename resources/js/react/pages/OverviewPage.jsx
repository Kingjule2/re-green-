import React, { useRef, useEffect } from 'react';
import KpiCard from '../components/shared/KpiCard';
import StatusBadge from '../components/shared/StatusBadge';
import { gsap } from '@/gsap';

export default function OverviewPage({ onNavigate }) {
    const pageRef = useRef(null);

    useEffect(() => {
        if (pageRef.current) {
            gsap.fromTo(
                pageRef.current.querySelectorAll('.stagger-fade'),
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5, stagger: 0.08, ease: 'power2.out' }
            );
        }
    }, []);

    const healthList = [
        { name: 'Kalimantan Selatan', sector: 'Sector Alpha', status: 'Healthy', percent: 92, color: 'bg-success' },
        { name: 'Kalimantan Barat', sector: 'Sector Beta', status: 'Needs Attention', percent: 71, color: 'bg-warning' },
        { name: 'Riau', sector: 'Peatland Zone 1', status: 'Healthy', percent: 84, color: 'bg-success' },
        { name: 'Sumatra Selatan', sector: 'Coastal Sector', status: 'Critical', percent: 48, color: 'bg-critical' },
    ];

    return (
        <div ref={pageRef} className="space-y-8 animate-in fade-in duration-300">
            {/* 1. Welcome Header */}
            <section className="stagger-fade flex flex-col md:flex-row justify-between items-start md:items-end gap-4 pb-2">
                <div>
                    <p className="font-label-md text-xs text-outline uppercase tracking-wider font-semibold mb-1">
                        September 1, 2026
                    </p>
                    <h2 className="font-headline-xl text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                        Good morning, Alex
                    </h2>
                    <p className="font-body-md text-sm md:text-base text-on-surface-variant mt-1">
                        Here is the latest intelligence overview across your active restoration zones.
                    </p>
                </div>
                <div className="flex gap-3 w-full md:w-auto">
                    <button
                        onClick={() => onNavigate('impact-reports')}
                        className="px-4 py-2.5 bg-surface-container-lowest border border-primary-container text-primary-container dark:text-primary-fixed font-label-md text-xs font-semibold rounded-lg hover:bg-surface-container transition-all flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                    >
                        <span className="material-symbols-outlined text-[18px]">assessment</span>
                        View Reports
                    </button>
                    <button
                        onClick={() => onNavigate('restoration-projects')}
                        className="px-4 py-2.5 bg-primary-container text-white font-label-md text-xs font-semibold rounded-lg hover:bg-primary transition-all flex items-center justify-center gap-2 shadow-sm cursor-pointer"
                    >
                        <span className="material-symbols-outlined text-[18px]">add</span>
                        New Restoration Project
                    </button>
                </div>
            </section>

            {/* 2. KPI Cards Row */}
            <section className="stagger-fade grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <KpiCard
                    title="Total Area Restored"
                    value="327"
                    unit="ha"
                    trend="+12.4% vs previous month"
                    isPositive={true}
                    icon="landscape"
                    trendIcon="trending_up"
                    delay={0.1}
                />
                <KpiCard
                    title="Active Projects"
                    value="12"
                    unit=""
                    trend="+2 this quarter"
                    isPositive={true}
                    icon="account_tree"
                    trendIcon="add_circle"
                    delay={0.2}
                />
                <KpiCard
                    title="Plant Survival Rate"
                    value="87.4"
                    unit="%"
                    trend="+4.2% vs last cycle"
                    isPositive={true}
                    icon="psychiatry"
                    trendIcon="trending_up"
                    delay={0.3}
                />
                <KpiCard
                    title="Community Members"
                    value="84"
                    unit=""
                    trend="+16 actively involved"
                    isPositive={true}
                    icon="group"
                    trendIcon="arrow_upward"
                    delay={0.4}
                />
            </section>

            {/* 3. Main Section: GIS Map & Project Health List */}
            <section className="stagger-fade grid grid-cols-1 lg:grid-cols-12 gap-6">
                {/* Left: GIS Interactive Map Canvas */}
                <div className="lg:col-span-7 xl:col-span-8 bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xs overflow-hidden flex flex-col min-h-[480px]">
                    <div className="p-5 border-b border-border-subtle flex justify-between items-center bg-surface-bright">
                        <div>
                            <h3 className="font-headline-md text-lg font-bold text-on-surface">
                                Restoration Activity & Polygon Mapping
                            </h3>
                            <p className="text-xs text-outline">Real-time geospatial boundaries for Kalimantan zone</p>
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => onNavigate('land-intelligence')}
                                className="px-3 py-1.5 border border-border-subtle rounded-lg text-xs font-semibold text-primary hover:bg-surface-container transition-colors flex items-center gap-1"
                            >
                                <span className="material-symbols-outlined text-[16px]">map</span>
                                Full GIS
                            </button>
                        </div>
                    </div>

                    <div className="relative flex-1 bg-surface-container-low overflow-hidden">
                        {/* High-res Satellite Map Background */}
                        <div
                            className="absolute inset-0 bg-cover bg-center opacity-85 transition-transform duration-700 hover:scale-105"
                            style={{
                                backgroundImage: `url('https://lh3.googleusercontent.com/aida-public/AB6AXuCmYclg098wRHehaJn8Ex4fbZyX5EzDtNKWq8TSdIHm1z8WUEc_D7H6g-GABH7rEWeuCO3VNcTzvEDV7LKb5Qpr0OXWgsbziY0hvq9M6Eo2GzKKn9OETt3fRGJ0UMqvnP6n1zGkt0sarmCJ1_yDyweuFcrPIlrZ7CbNRF4-wPwQ1jCy015AGALDWXNyLafTORQiEU4O_bRmEK6Y8uc5GBCnKVG-noeMDApuCSuQ1NpYOtwdcZohd4Hz')`,
                            }}
                        />

                        {/* Interactive Markers Overlay */}
                        <div className="absolute inset-0 p-4 pointer-events-none flex flex-col justify-between">
                            {/* Top Badge */}
                            <div className="self-start bg-charcoal/80 text-white backdrop-blur-md px-3 py-1.5 rounded-full text-xs font-semibold flex items-center gap-2 pointer-events-auto">
                                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                <span>4 Zones Monitored via Sentinel-2</span>
                            </div>

                            {/* Bottom Controls & Legend */}
                            <div className="flex justify-between items-end gap-4 pointer-events-auto">
                                <div className="bg-surface-container-lowest/90 backdrop-blur-md border border-border-subtle rounded-lg p-3.5 shadow-sm text-xs">
                                    <h4 className="font-label-md text-[11px] font-bold text-on-surface mb-2 uppercase tracking-wide">
                                        Status Legend
                                    </h4>
                                    <ul className="space-y-1.5 font-body-sm text-[11px] text-on-surface-variant">
                                        <li className="flex items-center gap-2">
                                            <span className="w-2.5 h-2.5 rounded-full bg-success"></span> Healthy (Sector Alpha)
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <span className="w-2.5 h-2.5 rounded-full bg-warning"></span> Needs Attention (Beta)
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <span className="w-2.5 h-2.5 rounded-full bg-critical"></span> Critical (Coastal Zone)
                                        </li>
                                    </ul>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <button
                                        onClick={() => alert('Zoom in')}
                                        className="w-9 h-9 bg-white border border-border-subtle rounded-lg flex items-center justify-center hover:bg-surface-container shadow-xs transition-colors"
                                        aria-label="Zoom In"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">add</span>
                                    </button>
                                    <button
                                        onClick={() => alert('Zoom out')}
                                        className="w-9 h-9 bg-white border border-border-subtle rounded-lg flex items-center justify-center hover:bg-surface-container shadow-xs transition-colors"
                                        aria-label="Zoom Out"
                                    >
                                        <span className="material-symbols-outlined text-[18px]">remove</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right: Project Health List */}
                <div className="lg:col-span-5 xl:col-span-4 bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xs flex flex-col min-h-[480px]">
                    <div className="p-5 border-b border-border-subtle bg-surface-bright">
                        <h3 className="font-headline-md text-lg font-bold text-on-surface">
                            Project Health
                        </h3>
                        <p className="font-body-sm text-xs text-outline mt-0.5">
                            Status & recovery indices of primary sites
                        </p>
                    </div>

                    <div className="flex-1 overflow-y-auto p-3 space-y-1">
                        {healthList.map((item, idx) => (
                            <div
                                key={idx}
                                onClick={() => onNavigate('restoration-projects')}
                                className="p-3.5 hover:bg-surface-container-low rounded-lg transition-colors border border-transparent hover:border-border-subtle cursor-pointer group"
                            >
                                <div className="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 className="font-semibold text-sm text-on-surface group-hover:text-primary transition-colors">
                                            {item.name}
                                        </h4>
                                        <p className="text-xs text-outline flex items-center gap-1 mt-0.5">
                                            <span className="material-symbols-outlined text-[14px]">location_on</span>
                                            {item.sector}
                                        </p>
                                    </div>
                                    <StatusBadge status={item.status} />
                                </div>

                                <div className="flex items-center gap-3 mt-3">
                                    <div className="flex-1 h-2 bg-surface-container-highest rounded-full overflow-hidden">
                                        <div
                                            className={`h-full ${item.color} rounded-full transition-all duration-1000`}
                                            style={{ width: `${item.percent}%` }}
                                        />
                                    </div>
                                    <span className="font-label-md text-xs font-bold text-on-surface w-8 text-right">
                                        {item.percent}%
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="p-4 border-t border-border-subtle bg-surface-container-lowest mt-auto">
                        <button
                            onClick={() => onNavigate('restoration-projects')}
                            className="w-full py-2.5 bg-surface-container-low text-primary font-label-md text-xs font-bold rounded-lg hover:bg-surface-container transition-colors flex items-center justify-center gap-2"
                        >
                            <span>View All 12 Projects</span>
                            <span className="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    );
}
