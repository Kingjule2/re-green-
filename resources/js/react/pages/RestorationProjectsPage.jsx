import React, { useState, useRef, useEffect } from 'react';
import StatusBadge from '../components/shared/StatusBadge';
import { gsap } from '@/gsap';

export default function RestorationProjectsPage() {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [regionFilter, setRegionFilter] = useState('all');
    const [selectedProject, setSelectedProject] = useState(null);
    const pageRef = useRef(null);

    useEffect(() => {
        if (pageRef.current) {
            gsap.fromTo(
                pageRef.current.querySelectorAll('.stagger-proj'),
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.45, stagger: 0.06, ease: 'power2.out' }
            );
        }
    }, []);

    const projects = [
        {
            id: 'KSA-001',
            name: 'Kalimantan Selatan - Sector Alpha',
            location: 'South Kalimantan',
            status: 'Healthy',
            area: '1,200 ha',
            progress: 45,
            progressColor: 'bg-success',
            species: 'Shorea balangeran, Melaleuca cajuputi',
            leader: 'Siti Aminah',
            lastAudit: 'Aug 24, 2026',
        },
        {
            id: 'KBB-042',
            name: 'Kalimantan Barat - Sector Beta',
            location: 'West Kalimantan',
            status: 'Needs Attention',
            area: '850 ha',
            progress: 22,
            progressColor: 'bg-warning',
            species: 'Dyera polyphylla, Alstonia scholaris',
            leader: 'Budi Santoso',
            lastAudit: 'Aug 18, 2026',
        },
        {
            id: 'RPZ-011',
            name: 'Riau - Peatland Zone 1',
            location: 'Riau Province',
            status: 'Critical',
            area: '400 ha',
            progress: 8,
            progressColor: 'bg-critical',
            species: 'Rhizophora apiculata, Avicennia marina',
            leader: 'Rizki Aditya',
            lastAudit: 'Aug 29, 2026',
        },
        {
            id: 'SSM-089',
            name: 'Sumatra Selatan - Coastal Mangrove',
            location: 'South Sumatra',
            status: 'Healthy',
            area: '620 ha',
            progress: 68,
            progressColor: 'bg-success',
            species: 'Bruguiera gymnorhiza, Sonneratia alba',
            leader: 'Dewi Lestari',
            lastAudit: 'Aug 12, 2026',
        },
        {
            id: 'KTT-104',
            name: 'Kalimantan Tengah - Riparian Corridor',
            location: 'Central Kalimantan',
            status: 'Healthy',
            area: '950 ha',
            progress: 54,
            progressColor: 'bg-success',
            species: 'Campnosperma auriculatum, Palaquium leiocarpum',
            leader: 'Agus Pratama',
            lastAudit: 'Aug 05, 2026',
        },
    ];

    const filteredProjects = projects.filter((p) => {
        const matchesSearch =
            p.name.toLowerCase().includes(search.toLowerCase()) ||
            p.location.toLowerCase().includes(search.toLowerCase()) ||
            p.id.toLowerCase().includes(search.toLowerCase());

        const matchesStatus =
            statusFilter === 'all' ||
            p.status.toLowerCase().includes(statusFilter.toLowerCase());

        const matchesRegion =
            regionFilter === 'all' ||
            p.location.toLowerCase().includes(regionFilter.toLowerCase());

        return matchesSearch && matchesStatus && matchesRegion;
    });

    return (
        <div ref={pageRef} className="space-y-6 animate-in fade-in duration-300">
            {/* Header */}
            <div className="stagger-proj flex flex-col md:flex-row justify-between items-start md:items-end gap-4 pb-2">
                <div>
                    <h2 className="font-headline-xl text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                        Restoration Projects Directory
                    </h2>
                    <p className="font-body-md text-sm md:text-base text-on-surface-variant mt-1">
                        Active conservation and reforestation concessions under management.
                    </p>
                </div>
                <div className="flex gap-2 w-full md:w-auto">
                    <button
                        onClick={() => alert('Exporting full project CSV')}
                        className="px-4 py-2 bg-surface-container-lowest border border-border-subtle text-primary font-label-md text-xs font-semibold rounded-lg hover:bg-surface-container transition-colors flex items-center justify-center gap-2 shadow-xs"
                    >
                        <span className="material-symbols-outlined text-[16px]">download</span>
                        Export CSV
                    </button>
                    <button
                        onClick={() => alert('Add Project Modal')}
                        className="px-4 py-2 bg-primary-container text-white font-label-md text-xs font-semibold rounded-lg hover:bg-primary transition-colors flex items-center justify-center gap-2 shadow-xs"
                    >
                        <span className="material-symbols-outlined text-[16px]">add</span>
                        New Site
                    </button>
                </div>
            </div>

            {/* Filter Bar */}
            <div className="stagger-proj bg-surface-container-lowest border border-border-subtle rounded-xl p-4 shadow-xs flex flex-col sm:flex-row gap-3 items-center justify-between">
                <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    {/* Search */}
                    <div className="relative flex-1 sm:w-64">
                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">
                            search
                        </span>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Filter by name, ID, region..."
                            className="w-full pl-9 pr-3 py-2 bg-surface-bright border border-border-subtle rounded-lg text-xs font-body-sm text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    {/* Status Filter */}
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="bg-surface-bright border border-border-subtle rounded-lg px-3 py-2 text-xs font-semibold text-on-surface focus:outline-none focus:border-primary cursor-pointer"
                    >
                        <option value="all">All Statuses</option>
                        <option value="healthy">Healthy</option>
                        <option value="attention">Needs Attention</option>
                        <option value="critical">Critical</option>
                    </select>

                    {/* Region Filter */}
                    <select
                        value={regionFilter}
                        onChange={(e) => setRegionFilter(e.target.value)}
                        className="bg-surface-bright border border-border-subtle rounded-lg px-3 py-2 text-xs font-semibold text-on-surface focus:outline-none focus:border-primary cursor-pointer"
                    >
                        <option value="all">All Regions</option>
                        <option value="kalimantan">Kalimantan</option>
                        <option value="sumatra">Sumatra</option>
                        <option value="riau">Riau</option>
                    </select>
                </div>

                <div className="text-xs text-outline font-semibold w-full sm:w-auto text-right">
                    Showing {filteredProjects.length} of {projects.length} sites
                </div>
            </div>

            {/* Table */}
            <div className="stagger-proj bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse min-w-[760px]">
                        <thead>
                            <tr className="border-b border-border-subtle bg-surface-bright text-on-surface-variant font-label-md text-xs uppercase tracking-wider">
                                <th className="px-6 py-3.5 font-bold">Project Name & ID</th>
                                <th className="px-6 py-3.5 font-bold">Location</th>
                                <th className="px-6 py-3.5 font-bold">Status</th>
                                <th className="px-6 py-3.5 font-bold">Total Area</th>
                                <th className="px-6 py-3.5 font-bold w-48">Recovery Target</th>
                                <th className="px-6 py-3.5 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border-subtle text-xs">
                            {filteredProjects.map((p) => (
                                <tr
                                    key={p.id}
                                    onClick={() => setSelectedProject(p)}
                                    className="hover:bg-surface-container-low transition-colors cursor-pointer group"
                                >
                                    <td className="px-6 py-4">
                                        <div className="font-semibold text-primary text-sm group-hover:underline">
                                            {p.name}
                                        </div>
                                        <div className="text-outline text-[11px] font-mono mt-0.5">ID: {p.id}</div>
                                    </td>
                                    <td className="px-6 py-4 text-on-surface font-medium">{p.location}</td>
                                    <td className="px-6 py-4">
                                        <StatusBadge status={p.status} />
                                    </td>
                                    <td className="px-6 py-4 text-on-surface font-semibold">{p.area}</td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-2.5">
                                            <div className="w-full bg-surface-container-highest rounded-full h-2 overflow-hidden">
                                                <div
                                                    className={`h-full ${p.progressColor} rounded-full`}
                                                    style={{ width: `${p.progress}%` }}
                                                />
                                            </div>
                                            <span className="font-bold text-on-surface w-8 text-right font-mono">
                                                {p.progress}%
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setSelectedProject(p);
                                            }}
                                            className="px-3 py-1 text-xs font-bold text-primary hover:bg-surface-container rounded-md transition-colors"
                                        >
                                            Manage
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Table Footer / Pagination */}
                <div className="px-6 py-3.5 border-t border-border-subtle bg-surface-bright flex items-center justify-between text-xs text-on-surface-variant">
                    <span>Showing 1 to {filteredProjects.length} entries</span>
                    <div className="flex items-center gap-1">
                        <button className="p-1 rounded hover:bg-surface-container text-outline disabled:opacity-50" disabled>
                            <span className="material-symbols-outlined text-[18px]">chevron_left</span>
                        </button>
                        <button className="w-7 h-7 rounded bg-primary-container text-white font-bold flex items-center justify-center">
                            1
                        </button>
                        <button className="w-7 h-7 rounded hover:bg-surface-container text-on-surface font-semibold flex items-center justify-center">
                            2
                        </button>
                        <button className="p-1 rounded hover:bg-surface-container text-outline">
                            <span className="material-symbols-outlined text-[18px]">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* Quick Detail Modal Drawer */}
            {selectedProject && (
                <div className="fixed inset-0 bg-charcoal/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
                    <div className="bg-surface-container-lowest border border-border-subtle rounded-2xl max-w-lg w-full p-6 shadow-2xl animate-in zoom-in-95">
                        <div className="flex justify-between items-start pb-3 border-b border-border-subtle mb-4">
                            <div>
                                <span className="text-[10px] text-outline font-mono uppercase">{selectedProject.id}</span>
                                <h3 className="text-lg font-bold text-primary">{selectedProject.name}</h3>
                                <p className="text-xs text-outline">{selectedProject.location}</p>
                            </div>
                            <button
                                onClick={() => setSelectedProject(null)}
                                className="p-1 text-on-surface-variant hover:text-primary rounded-lg"
                            >
                                <span className="material-symbols-outlined">close</span>
                            </button>
                        </div>

                        <div className="space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-3 bg-surface-container-low p-3.5 rounded-xl">
                                <div>
                                    <span className="text-outline block">Status:</span>
                                    <StatusBadge status={selectedProject.status} className="mt-1" />
                                </div>
                                <div>
                                    <span className="text-outline block">Total Area:</span>
                                    <span className="font-bold text-primary text-sm mt-1 block">{selectedProject.area}</span>
                                </div>
                                <div>
                                    <span className="text-outline block">Site Lead:</span>
                                    <span className="font-semibold text-on-surface mt-1 block">{selectedProject.leader}</span>
                                </div>
                                <div>
                                    <span className="text-outline block">Last Field Audit:</span>
                                    <span className="font-semibold text-on-surface mt-1 block">{selectedProject.lastAudit}</span>
                                </div>
                            </div>

                            <div>
                                <span className="text-outline block mb-1 font-semibold">Planted Species Diversity:</span>
                                <p className="p-2.5 rounded-lg bg-surface border border-border-subtle text-on-surface">
                                    {selectedProject.species}
                                </p>
                            </div>

                            <div>
                                <div className="flex justify-between items-center mb-1">
                                    <span className="text-outline font-semibold">Canopy Recovery Progress:</span>
                                    <span className="font-bold text-primary">{selectedProject.progress}%</span>
                                </div>
                                <div className="w-full bg-surface-container-highest rounded-full h-2.5 overflow-hidden">
                                    <div
                                        className={`h-full ${selectedProject.progressColor}`}
                                        style={{ width: `${selectedProject.progress}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-2">
                            <button
                                onClick={() => setSelectedProject(null)}
                                className="px-4 py-2 border border-border-subtle rounded-lg text-xs font-semibold text-on-surface hover:bg-surface-container"
                            >
                                Close
                            </button>
                            <button
                                onClick={() => {
                                    alert(`Managing site ${selectedProject.id}`);
                                    setSelectedProject(null);
                                }}
                                className="px-4 py-2 bg-primary-container text-white rounded-lg text-xs font-semibold hover:bg-primary"
                            >
                                Open Full Console
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
