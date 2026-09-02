import React, { useState, useRef, useEffect } from 'react';
import StatusBadge from '../components/shared/StatusBadge';
import { gsap } from '@/gsap';

export default function LandIntelligencePage() {
    const [activeLayer, setActiveLayer] = useState('all');
    const [selectedSector, setSelectedSector] = useState('Sector Alpha');
    const pageRef = useRef(null);

    useEffect(() => {
        if (pageRef.current) {
            gsap.fromTo(
                pageRef.current.querySelectorAll('.stagger-land'),
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.5, stagger: 0.08, ease: 'power2.out' }
            );
        }
    }, []);

    const fireHistory = [
        { year: '2019', height: '15%', color: 'bg-surface-container-highest', label: '15%' },
        { year: '2020', height: '60%', color: 'bg-warning/80', label: '60%' },
        { year: '2021', height: '25%', color: 'bg-surface-container-highest', label: '25%' },
        { year: '2022', height: '10%', color: 'bg-surface-container-highest', label: '10%' },
        { year: '2023', height: '5%', color: 'bg-success', label: '5%' },
    ];

    return (
        <div ref={pageRef} className="space-y-8 animate-in fade-in duration-300">
            {/* Page Header */}
            <div className="stagger-land flex flex-col md:flex-row justify-between items-start md:items-end gap-4 pb-2">
                <div>
                    <h2 className="font-headline-xl text-3xl md:text-4xl font-bold text-primary dark:text-primary-fixed">
                        Land Intelligence & GIS Modeling
                    </h2>
                    <p className="font-body-md text-sm md:text-base text-on-surface-variant mt-1">
                        High-precision topographic layers, slope classification, soil pH, and historical wildfire data.
                    </p>
                </div>
                <div className="flex gap-2 w-full md:w-auto">
                    <button
                        onClick={() => alert('Exporting GIS GeoJSON package')}
                        className="px-4 py-2 bg-surface-container-lowest border border-border-subtle text-primary font-label-md text-xs font-semibold rounded-lg hover:bg-surface-container transition-colors flex items-center justify-center gap-2 shadow-xs"
                    >
                        <span className="material-symbols-outlined text-[16px]">download</span>
                        Export GIS Layer
                    </button>
                    <button
                        onClick={() => alert('Triggering Sentinel-2 re-scan')}
                        className="px-4 py-2 bg-primary-container text-white font-label-md text-xs font-semibold rounded-lg hover:bg-primary transition-colors flex items-center justify-center gap-2 shadow-xs"
                    >
                        <span className="material-symbols-outlined text-[16px]">satellite_alt</span>
                        Fetch Satellite Pass
                    </button>
                </div>
            </div>

            {/* GIS Main Layout */}
            <div className="stagger-land grid grid-cols-1 lg:grid-cols-12 gap-6">
                {/* Interactive GIS Map Canvas (8 cols) */}
                <div className="lg:col-span-8 bg-surface-container-lowest border border-border-subtle rounded-xl shadow-xs overflow-hidden flex flex-col min-h-[580px]">
                    {/* Layer Filter Toolbar */}
                    <div className="p-4 border-b border-border-subtle bg-surface-bright flex flex-wrap justify-between items-center gap-3">
                        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                            {[
                                { id: 'all', label: 'All Layers', icon: 'layers' },
                                { id: 'ndvi', label: 'NDVI Biomass', icon: 'psychiatry' },
                                { id: 'elevation', label: 'Topography & Slopes', icon: 'terrain' },
                                { id: 'fire', label: 'Fire Vulnerability', icon: 'local_fire_department' },
                            ].map((layer) => (
                                <button
                                    key={layer.id}
                                    onClick={() => setActiveLayer(layer.id)}
                                    className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all ${
                                        activeLayer === layer.id
                                            ? 'bg-primary-container text-white shadow-xs'
                                            : 'bg-surface-container-low text-on-surface-variant hover:bg-surface-container'
                                    }`}
                                >
                                    <span className="material-symbols-outlined text-[15px]">{layer.icon}</span>
                                    <span>{layer.label}</span>
                                </button>
                            ))}
                        </div>

                        <span className="text-xs font-semibold text-outline">
                            CRS: EPSG:4326 (WGS 84)
                        </span>
                    </div>

                    {/* Map Surface */}
                    <div className="relative flex-1 bg-surface-container-low overflow-hidden">
                        <div
                            className="absolute inset-0 bg-cover bg-center transition-all duration-500"
                            style={{
                                backgroundImage: `url('https://lh3.googleusercontent.com/aida-public/AB6AXuCmYclg098wRHehaJn8Ex4fbZyX5EzDtNKWq8TSdIHm1z8WUEc_D7H6g-GABH7rEWeuCO3VNcTzvEDV7LKb5Qpr0OXWgsbziY0hvq9M6Eo2GzKKn9OETt3fRGJ0UMqvnP6n1zGkt0sarmCJ1_yDyweuFcrPIlrZ7CbNRF4-wPwQ1jCy015AGALDWXNyLafTORQiEU4O_bRmEK6Y8uc5GBCnKVG-noeMDApuCSuQ1NpYOtwdcZohd4Hz')`,
                                filter: activeLayer === 'ndvi' ? 'hue-rotate(60deg) contrast(1.2)' : activeLayer === 'fire' ? 'hue-rotate(280deg) saturate(1.4)' : 'none',
                            }}
                        />

                        {/* Coordinate & Pin Overlay */}
                        <div className="absolute top-4 left-4 bg-charcoal/80 text-white backdrop-blur-md px-3 py-2 rounded-lg text-xs font-mono shadow-md">
                            <div>LAT: -0.2194° S</div>
                            <div>LON: 113.9213° E</div>
                            <div className="text-emerald-400 mt-1 font-sans text-[11px] font-semibold">
                                Sector Alpha Polygon (327 ha)
                            </div>
                        </div>

                        {/* Map HUD Controls */}
                        <div className="absolute bottom-4 right-4 bg-surface-container-lowest/90 backdrop-blur-md border border-border-subtle rounded-lg shadow-md flex flex-col p-1">
                            <button
                                onClick={() => alert('Zoom in')}
                                className="w-8 h-8 flex items-center justify-center hover:bg-surface-container rounded transition-colors text-primary"
                                aria-label="Zoom in"
                            >
                                <span className="material-symbols-outlined text-[18px]">add</span>
                            </button>
                            <div className="w-full h-px bg-border-subtle my-0.5" />
                            <button
                                onClick={() => alert('Zoom out')}
                                className="w-8 h-8 flex items-center justify-center hover:bg-surface-container rounded transition-colors text-primary"
                                aria-label="Zoom out"
                            >
                                <span className="material-symbols-outlined text-[18px]">remove</span>
                            </button>
                            <div className="w-full h-px bg-border-subtle my-0.5" />
                            <button
                                onClick={() => alert('Reset to center coordinates')}
                                className="w-8 h-8 flex items-center justify-center hover:bg-surface-container rounded transition-colors text-primary"
                                aria-label="My location"
                            >
                                <span className="material-symbols-outlined text-[18px]">my_location</span>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Right: Telemetry & Terrain Metrics (4 cols) */}
                <div className="lg:col-span-4 space-y-6">
                    {/* Topographic Quick Metrics */}
                    <div className="grid grid-cols-2 gap-4">
                        {/* Elevation */}
                        <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-xl shadow-xs">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase font-semibold block mb-1">
                                Avg Elevation
                            </span>
                            <div className="font-headline-md text-2xl font-bold text-primary">380 m</div>
                            <span className="font-label-md text-[10px] text-outline mt-1 block">Peak: 540 m MSL</span>
                        </div>

                        {/* Slope */}
                        <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-xl shadow-xs">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase font-semibold block mb-1">
                                Avg Slope
                            </span>
                            <div className="font-headline-md text-2xl font-bold text-primary">14.2°</div>
                            <span className="font-label-md text-[10px] text-success mt-1 block font-semibold">Low Erosion Risk</span>
                        </div>

                        {/* Fire Risk */}
                        <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-xl shadow-xs">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase font-semibold block mb-1">
                                Fire Risk
                            </span>
                            <div className="font-headline-md text-2xl font-bold text-success">32%</div>
                            <div className="mt-2 w-full bg-surface-container-highest rounded-full h-1.5 overflow-hidden">
                                <div className="bg-success h-full rounded-full" style={{ width: '32%' }}></div>
                            </div>
                            <span className="font-label-md text-[10px] text-success mt-1.5 block font-bold uppercase tracking-wider">
                                Low Risk
                            </span>
                        </div>

                        {/* Soil Quality */}
                        <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-xl shadow-xs">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase font-semibold block mb-1">
                                Soil Quality
                            </span>
                            <div className="font-headline-md text-2xl font-bold text-primary">Good</div>
                            <div className="mt-2 flex gap-1">
                                <div className="h-1.5 flex-1 bg-success rounded-l-full"></div>
                                <div className="h-1.5 flex-1 bg-success"></div>
                                <div className="h-1.5 flex-1 bg-success"></div>
                                <div className="h-1.5 flex-1 bg-surface-container-highest"></div>
                                <div className="h-1.5 flex-1 bg-surface-container-highest rounded-r-full"></div>
                            </div>
                            <span className="font-label-md text-[10px] text-outline mt-1.5 block uppercase">
                                pH 6.8 • Loamy Peat
                            </span>
                        </div>
                    </div>

                    {/* Historical Wildfire Chart */}
                    <div className="bg-surface-container-lowest border border-border-subtle p-5 rounded-xl shadow-xs">
                        <div className="flex justify-between items-center mb-3">
                            <span className="font-label-md text-xs text-on-surface-variant uppercase font-bold tracking-wider">
                                Historical Fire Incidence
                            </span>
                            <span className="text-[10px] text-outline font-semibold">2019 - 2023</span>
                        </div>

                        <div className="h-32 flex items-end gap-3 pb-2 border-b border-border-subtle relative pt-4">
                            <div className="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-20">
                                <div className="border-t border-outline w-full" />
                                <div className="border-t border-outline w-full" />
                                <div className="border-t border-outline w-full" />
                            </div>

                            {fireHistory.map((item, i) => (
                                <div key={i} className="flex-1 flex flex-col justify-end items-center group relative h-full">
                                    <div
                                        className={`w-full ${item.color} rounded-t transition-all duration-300 group-hover:scale-105`}
                                        style={{ height: item.height }}
                                        title={`${item.year}: ${item.label}`}
                                    />
                                    <span className="font-label-md text-[10px] text-outline mt-2">{item.year}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Field Report Asset */}
                    <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-xl flex items-center justify-between shadow-xs">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center border border-border-subtle">
                                <span className="material-symbols-outlined text-primary text-[20px]">content_paste_search</span>
                            </div>
                            <div>
                                <span className="font-body-sm text-xs font-bold text-primary block">
                                    Hydrology & Soil Report
                                </span>
                                <span className="font-label-md text-[10px] text-outline">Verified by Field Team • 2 days ago</span>
                            </div>
                        </div>
                        <button
                            onClick={() => alert('Downloading Hydrology & Soil Report PDF')}
                            className="p-2 text-primary hover:bg-surface-container rounded-full transition-colors"
                            aria-label="Download Field Report"
                        >
                            <span className="material-symbols-outlined text-[20px]">download</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
