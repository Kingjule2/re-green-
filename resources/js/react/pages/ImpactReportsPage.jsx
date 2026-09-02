import React, { useState, useRef, useEffect } from 'react';
import StatusBadge from '../components/shared/StatusBadge';
import { gsap } from '@/gsap';

export default function ImpactReportsPage() {
    const [selectedProject, setSelectedProject] = useState('Kalimantan Restoration Project');
    const [reportType, setReportType] = useState('Comprehensive ESG Overview');
    const [isGenerating, setIsGenerating] = useState(false);
    const pageRef = useRef(null);

    useEffect(() => {
        if (pageRef.current) {
            gsap.fromTo(
                pageRef.current.querySelectorAll('.stagger-rep'),
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5, stagger: 0.07, ease: 'power2.out' }
            );
        }
    }, []);

    const reports = [
        {
            title: 'Monthly Progress & Biomass Audit',
            range: 'Oct 2026',
            formats: ['PDF'],
            icon: 'description',
        },
        {
            title: 'Annual Carbon Accounting & Sequestration',
            range: '2025 - 2026',
            formats: ['PDF', 'CSV'],
            icon: 'analytics',
        },
        {
            title: 'Community Social Impact & Fair Wage Audit',
            range: 'Q3 2026',
            formats: ['PDF'],
            icon: 'diversity_3',
        },
        {
            title: 'Riparian Water Retention Assessment',
            range: 'Aug 2026',
            formats: ['PDF', 'CSV'],
            icon: 'water_drop',
        },
    ];

    const handleGenerate = (e) => {
        e.preventDefault();
        setIsGenerating(true);
        setTimeout(() => {
            setIsGenerating(false);
            alert(`ESG Report generated successfully for ${selectedProject} (${reportType})!`);
        }, 1500);
    };

    return (
        <div ref={pageRef} className="space-y-8 animate-in fade-in duration-300">
            {/* Header */}
            <div className="stagger-rep pb-2">
                <h2 className="font-headline-xl text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                    Impact & ESG Carbon Reports
                </h2>
                <p className="font-body-md text-sm md:text-base text-on-surface-variant max-w-2xl mt-1">
                    Comprehensive ESG verification, verified carbon units (VCU), and multi-standard audit exports.
                </p>
            </div>

            {/* Key Impact Metrics Top Row */}
            <div className="stagger-rep grid grid-cols-1 md:grid-cols-3 gap-6">
                {/* Metric 1 */}
                <div className="bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between hover:border-primary/40 transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <span className="material-symbols-outlined text-primary text-[22px]">co2</span>
                            <h3 className="font-label-md text-xs text-on-surface-variant uppercase font-bold tracking-wider">
                                Carbon Sequestrated
                            </h3>
                        </div>
                        <div className="font-stats-lg text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                            450 <span className="text-sm font-normal text-outline">tonnes CO₂e</span>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center gap-2">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-success font-label-md text-xs font-semibold">
                            <span className="material-symbols-outlined text-[14px] mr-1">trending_up</span>
                            +15% vs previous year
                        </span>
                    </div>
                </div>

                {/* Metric 2 */}
                <div className="bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between hover:border-primary/40 transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <span className="material-symbols-outlined text-info text-[22px]">water_drop</span>
                            <h3 className="font-label-md text-xs text-on-surface-variant uppercase font-bold tracking-wider">
                                Water Retention Capacity
                            </h3>
                        </div>
                        <div className="font-stats-lg text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                            +12% <span className="text-sm font-normal text-outline">aquifer recharge</span>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center gap-2">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-success font-label-md text-xs font-semibold">
                            <span className="material-symbols-outlined text-[14px] mr-1">trending_up</span>
                            Consistent improvement
                        </span>
                    </div>
                </div>

                {/* Metric 3 */}
                <div className="bg-surface-container-lowest border border-border-subtle rounded-xl p-6 card-shadow flex flex-col justify-between hover:border-primary/40 transition-colors">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <span className="material-symbols-outlined text-secondary text-[22px]">eco</span>
                            <h3 className="font-label-md text-xs text-on-surface-variant uppercase font-bold tracking-wider">
                                Biodiversity Index
                            </h3>
                        </div>
                        <div className="font-stats-lg text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                            7.4 <span className="text-sm font-normal text-outline">/ 10 Shannon Index</span>
                        </div>
                    </div>
                    <div className="mt-4 flex items-center gap-2">
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 font-label-md text-xs font-semibold">
                            <span className="material-symbols-outlined text-[14px] mr-1">trending_flat</span>
                            Stable faunal return
                        </span>
                    </div>
                </div>
            </div>

            {/* Generated Reports & Builder Panel */}
            <div className="stagger-rep grid grid-cols-1 lg:grid-cols-12 gap-6">
                {/* Generated Reports List (8 cols) */}
                <div className="lg:col-span-8 bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xs flex flex-col overflow-hidden">
                    <div className="p-5 border-b border-border-subtle flex justify-between items-center bg-surface-bright">
                        <div>
                            <h3 className="font-headline-md text-lg font-bold text-on-surface">
                                Generated Audit Reports
                            </h3>
                            <p className="text-xs text-outline">Verified PDFs and raw accounting CSV files</p>
                        </div>
                        <span className="text-xs font-semibold text-outline">
                            4 Archived Reports
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse min-w-[580px]">
                            <thead>
                                <tr className="border-b border-border-subtle bg-surface-bright text-on-surface-variant font-label-md text-xs uppercase tracking-wider">
                                    <th className="p-4 font-bold">Report Title</th>
                                    <th className="p-4 font-bold">Coverage Period</th>
                                    <th className="p-4 font-bold">Format</th>
                                    <th className="p-4 font-bold text-right">Download</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border-subtle text-xs">
                                {reports.map((rep, idx) => (
                                    <tr
                                        key={idx}
                                        className="hover:bg-surface-container-low transition-colors group cursor-pointer"
                                    >
                                        <td className="p-4">
                                            <div className="flex items-center gap-3">
                                                <span className="material-symbols-outlined text-outline group-hover:text-primary transition-colors text-[20px]">
                                                    {rep.icon}
                                                </span>
                                                <span className="font-semibold text-on-surface group-hover:text-primary">
                                                    {rep.title}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="p-4 text-on-surface-variant font-medium">{rep.range}</td>
                                        <td className="p-4">
                                            <div className="flex gap-1.5">
                                                {rep.formats.map((fmt) => (
                                                    <span
                                                        key={fmt}
                                                        className="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant font-mono text-[10px] font-bold"
                                                    >
                                                        {fmt}
                                                    </span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="p-4 text-right">
                                            <button
                                                onClick={() => alert(`Downloading ${rep.title}`)}
                                                className="text-primary hover:underline font-semibold flex items-center justify-end w-full gap-1"
                                            >
                                                <span className="material-symbols-outlined text-[16px]">download</span>
                                                Export
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Report Builder Form (4 cols) */}
                <div className="lg:col-span-4 bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xs p-6 flex flex-col justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-4 pb-3 border-b border-border-subtle">
                            <span className="material-symbols-outlined text-primary text-[20px]">build</span>
                            <h3 className="font-headline-md text-base font-bold text-on-surface">
                                Custom Report Builder
                            </h3>
                        </div>

                        <form onSubmit={handleGenerate} className="space-y-4 text-xs">
                            {/* Project Select */}
                            <div>
                                <label className="block font-label-md font-semibold text-on-surface-variant mb-1">
                                    Concession / Project
                                </label>
                                <select
                                    value={selectedProject}
                                    onChange={(e) => setSelectedProject(e.target.value)}
                                    className="w-full bg-surface-bright border border-border-subtle rounded-lg px-3 py-2 text-on-surface font-medium focus:outline-none focus:border-primary"
                                >
                                    <option>Kalimantan Restoration Project</option>
                                    <option>Sumatra Peatland Conservation</option>
                                    <option>Riau Mangrove Zone</option>
                                    <option>All Projects Aggregated</option>
                                </select>
                            </div>

                            {/* Report Type */}
                            <div>
                                <label className="block font-label-md font-semibold text-on-surface-variant mb-1">
                                    Report Framework
                                </label>
                                <select
                                    value={reportType}
                                    onChange={(e) => setReportType(e.target.value)}
                                    className="w-full bg-surface-bright border border-border-subtle rounded-lg px-3 py-2 text-on-surface font-medium focus:outline-none focus:border-primary"
                                >
                                    <option>Comprehensive ESG Overview (GRI Standards)</option>
                                    <option>Carbon Accounting & VCU Sequestration</option>
                                    <option>Biodiversity & Bioacoustic Field Audit</option>
                                    <option>Community Income & Livelihood Ledger</option>
                                </select>
                            </div>

                            {/* Date Range */}
                            <div>
                                <label className="block font-label-md font-semibold text-on-surface-variant mb-1">
                                    Audit Date Range
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    <input
                                        type="date"
                                        defaultValue="2026-01-01"
                                        className="bg-surface-bright border border-border-subtle rounded-lg px-2.5 py-1.5 text-on-surface focus:outline-none focus:border-primary"
                                    />
                                    <input
                                        type="date"
                                        defaultValue="2026-09-01"
                                        className="bg-surface-bright border border-border-subtle rounded-lg px-2.5 py-1.5 text-on-surface focus:outline-none focus:border-primary"
                                    />
                                </div>
                            </div>

                            {/* Format Checkboxes */}
                            <div>
                                <label className="block font-label-md font-semibold text-on-surface-variant mb-2">
                                    Export Format
                                </label>
                                <div className="flex gap-4">
                                    <label className="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" defaultChecked className="rounded text-primary focus:ring-primary" />
                                        <span>PDF Report</span>
                                    </label>
                                    <label className="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" defaultChecked className="rounded text-primary focus:ring-primary" />
                                        <span>Raw CSV</span>
                                    </label>
                                    <label className="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" className="rounded text-primary focus:ring-primary" />
                                        <span>GeoJSON</span>
                                    </label>
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={isGenerating}
                                className="w-full mt-2 py-2.5 bg-primary-container text-white font-label-md text-xs font-bold rounded-lg hover:bg-primary transition-all flex items-center justify-center gap-2 shadow-xs cursor-pointer disabled:opacity-75"
                            >
                                <span className={`material-symbols-outlined text-[16px] ${isGenerating ? 'animate-spin' : ''}`}>
                                    {isGenerating ? 'hourglass_top' : 'auto_awesome'}
                                </span>
                                <span>{isGenerating ? 'Compiling Report...' : 'Generate ESG Report'}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}
