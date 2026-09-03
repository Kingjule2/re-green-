import React, { useState, useRef, useEffect } from 'react';
import { gsap } from '@/gsap';

const BASEMAP_URL =
    'https://lh3.googleusercontent.com/aida-public/AB6AXuA1cVlVsxdkvX1kgPEfEpnCHRXXzLBuR1YVkhzWbkGTeWOwcaWhgTOpzpMRUthvx3KwLK6uo5mc5A3s0Ml756fqRuFIKxRceYFv9zGTcuD4TGQb0pg4R5Bt1srs0GEM-BPzBK8zH4huWQJnXMCDFscV-SVhJG0PYV0y77zZJgQhx0MysC4paa6grny_BdfiG12ZJJN-qW8zT9aND_i3i38f-Nf2IcQZ1aKPAmgwgQq3xWhYadm5wPOU';

/**
 * Overlay layers available on the GIS canvas.
 *
 * @type {Array<{id: string, label: string, dot: string|null, tint: string|null}>}
 */
const MAP_LAYERS = [
    { id: 'basemap', label: 'Satellite Basemap', dot: null, tint: null },
    { id: 'ndvi', label: 'Vegetation Density (NDVI)', dot: 'bg-success', tint: 'bg-success/25' },
    { id: 'fire', label: 'Fire Risk Index', dot: 'bg-critical', tint: 'bg-critical/20' },
    { id: 'moisture', label: 'Soil Moisture Levels', dot: 'bg-info', tint: 'bg-info/20' },
    { id: 'biomass', label: 'Biomass Estimate', dot: 'bg-earth-tan', tint: 'bg-earth-tan/25' },
];

const FIRE_HISTORY = [
    { year: '2019', height: '15%', color: 'bg-surface-container-highest', emphasis: false },
    { year: '2020', height: '60%', color: 'bg-warning/60', emphasis: false },
    { year: '2021', height: '25%', color: 'bg-surface-container-highest', emphasis: false },
    { year: '2022', height: '10%', color: 'bg-surface-container-highest', emphasis: false },
    { year: '2023', height: '5%', color: 'bg-surface-container-highest', emphasis: true },
];

export default function LandIntelligencePage() {
    const [activeLayers, setActiveLayers] = useState({
        basemap: true,
        ndvi: true,
        fire: true,
        moisture: false,
        biomass: false,
    });
    const [opacity, setOpacity] = useState(85);
    const [parcelOpen, setParcelOpen] = useState(true);
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

    const toggleLayer = (id) => {
        setActiveLayers((current) => ({ ...current, [id]: !current[id] }));
    };

    return (
        <div ref={pageRef} className="space-y-8 animate-in fade-in duration-300">
            {/* Page Header */}
            <div className="stagger-land flex flex-col md:flex-row justify-between items-start md:items-end gap-4 pb-2">
                <div>
                    <h2 className="font-headline-xl text-headline-xl text-primary dark:text-primary-fixed">
                        Land Intelligence &amp; GIS Modeling
                    </h2>
                    <p className="font-body-md text-body-md text-on-surface-variant mt-1">
                        High-precision topographic layers, slope classification, soil pH, and historical wildfire data.
                    </p>
                </div>
                <div className="flex gap-2 w-full md:w-auto">
                    <button
                        onClick={() => alert('Exporting GIS GeoJSON package')}
                        className="px-4 py-2 bg-surface-container-lowest border border-border-subtle text-primary font-label-md text-label-md rounded-lg hover:bg-surface-container transition-colors flex items-center justify-center gap-2 card-shadow"
                    >
                        <span className="material-symbols-outlined text-[16px]">download</span>
                        Export GIS Layer
                    </button>
                    <button
                        onClick={() => alert('Triggering Sentinel-2 re-scan')}
                        className="px-4 py-2 bg-primary-container text-on-primary font-label-md text-label-md rounded-lg hover:bg-primary transition-colors flex items-center justify-center gap-2 card-shadow"
                    >
                        <span className="material-symbols-outlined text-[16px]">satellite_alt</span>
                        Fetch Satellite Pass
                    </button>
                </div>
            </div>

            {/* GIS Main Layout */}
            <div className="stagger-land grid grid-cols-1 lg:grid-cols-12 gap-gutter">
                {/* Interactive GIS Map Canvas (8 cols) */}
                <div className="lg:col-span-8 bg-surface-container-lowest border border-border-subtle rounded-lg card-shadow overflow-hidden flex flex-col min-h-[620px]">
                    <div className="p-4 border-b border-border-subtle bg-surface-bright flex flex-wrap justify-between items-center gap-3">
                        <div className="flex items-center gap-2">
                            <span className="material-symbols-outlined text-on-surface-variant text-[20px]">map</span>
                            <h3 className="font-headline-md text-headline-sm text-primary">Kalimantan GIS Canvas</h3>
                        </div>
                        <span className="font-label-md text-label-md text-outline">CRS: EPSG:4326 (WGS 84)</span>
                    </div>

                    {/* Map Stage — floating panels are positioned against this element */}
                    <div className="relative flex-1 bg-surface-container-low overflow-hidden">
                        {/* Satellite Basemap */}
                        {activeLayers.basemap && (
                            <div
                                className="absolute inset-0 bg-cover bg-center transition-opacity duration-300"
                                style={{ backgroundImage: `url('${BASEMAP_URL}')`, opacity: opacity / 100 }}
                            >
                                <div className="absolute inset-0 bg-primary/5" />
                            </div>
                        )}

                        {/* Data Layer Tints */}
                        {MAP_LAYERS.filter((layer) => layer.tint && activeLayers[layer.id]).map((layer) => (
                            <div
                                key={layer.id}
                                className={`absolute inset-0 mix-blend-multiply transition-opacity duration-300 ${layer.tint}`}
                                aria-hidden="true"
                            />
                        ))}

                        {/* Floating Overlay Rail */}
                        <div className="absolute inset-0 pointer-events-none p-4 flex flex-col justify-between gap-4">
                            <div className="flex justify-between items-start gap-4">
                                {/* Layer Controls */}
                                <div className="pointer-events-auto w-[280px] max-w-[75%] bg-surface-container-lowest/95 backdrop-blur-md border border-border-subtle rounded-lg card-shadow flex flex-col">
                                    <div className="px-5 py-3.5 border-b border-border-subtle flex items-center gap-2">
                                        <span className="material-symbols-outlined text-on-surface-variant text-[20px]">
                                            layers
                                        </span>
                                        <h4 className="font-headline-md text-body-md font-semibold text-primary">
                                            Layer Controls
                                        </h4>
                                    </div>

                                    <div className="p-5 flex flex-col gap-4">
                                        {MAP_LAYERS.map((layer) => (
                                            <label
                                                key={layer.id}
                                                className="flex items-center justify-between cursor-pointer group gap-3"
                                            >
                                                <span className="flex items-center gap-2">
                                                    {layer.dot && (
                                                        <span
                                                            className={`w-2 h-2 rounded-full shrink-0 ${layer.dot}`}
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    <span className="font-body-sm text-body-sm text-on-surface group-hover:text-primary transition-colors">
                                                        {layer.label}
                                                    </span>
                                                </span>
                                                <input
                                                    type="checkbox"
                                                    className="control-checkbox shrink-0"
                                                    checked={activeLayers[layer.id]}
                                                    onChange={() => toggleLayer(layer.id)}
                                                />
                                            </label>
                                        ))}
                                    </div>

                                    <div className="px-5 py-4 bg-surface-container-low border-t border-border-subtle rounded-b-lg">
                                        <div className="flex justify-between items-center mb-2">
                                            <label
                                                htmlFor="basemap-opacity"
                                                className="font-label-md text-label-md text-outline"
                                            >
                                                Opacity
                                            </label>
                                            <span className="font-label-md text-label-md text-primary">{opacity}%</span>
                                        </div>
                                        <input
                                            id="basemap-opacity"
                                            type="range"
                                            min="0"
                                            max="100"
                                            value={opacity}
                                            onChange={(e) => setOpacity(Number(e.target.value))}
                                            className="w-full h-1 bg-border-subtle rounded-lg appearance-none cursor-pointer accent-primary"
                                        />
                                    </div>
                                </div>

                                {/* Coordinate HUD */}
                                <div className="pointer-events-auto hidden sm:block bg-charcoal/80 text-white backdrop-blur-md px-3 py-2 rounded-lg font-label-md text-label-md shadow-md">
                                    <div>LAT: 2°14&apos;S</div>
                                    <div>LON: 114°53&apos;E</div>
                                    <div className="text-primary-fixed mt-1">Sector 7G-Alpha (327 ha)</div>
                                </div>
                            </div>

                            {/* Zoom Cluster */}
                            <div className="self-end pointer-events-auto bg-surface-container-lowest/95 backdrop-blur-md border border-border-subtle rounded-lg card-shadow flex flex-col p-1">
                                <button
                                    onClick={() => alert('Zoom in')}
                                    className="w-8 h-8 flex items-center justify-center hover:bg-surface-container-low text-on-surface transition-colors rounded"
                                    aria-label="Zoom in"
                                >
                                    <span className="material-symbols-outlined text-[20px]">add</span>
                                </button>
                                <div className="w-full h-px bg-border-subtle my-0.5" />
                                <button
                                    onClick={() => alert('Zoom out')}
                                    className="w-8 h-8 flex items-center justify-center hover:bg-surface-container-low text-on-surface transition-colors rounded"
                                    aria-label="Zoom out"
                                >
                                    <span className="material-symbols-outlined text-[20px]">remove</span>
                                </button>
                                <div className="w-full h-px bg-border-subtle my-0.5" />
                                <button
                                    onClick={() => alert('Recentring on current parcel')}
                                    className="w-8 h-8 flex items-center justify-center hover:bg-surface-container-low text-primary transition-colors rounded"
                                    aria-label="My location"
                                >
                                    <span
                                        className="material-symbols-outlined text-[18px]"
                                        data-weight="fill"
                                    >
                                        my_location
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Selected Parcel Inspector (4 cols) */}
                <div className="lg:col-span-4">
                    {parcelOpen ? (
                        <div className="bg-surface-container-lowest border border-border-subtle rounded-lg card-shadow flex flex-col overflow-hidden">
                            {/* Panel Header */}
                            <div className="px-6 py-5 border-b border-border-subtle flex justify-between items-start gap-3">
                                <div>
                                    <span className="font-label-md text-label-md text-outline mb-1 block uppercase">
                                        Selected Parcel
                                    </span>
                                    <h3 className="font-headline-md text-headline-md text-primary">Sector 7G-Alpha</h3>
                                    <span className="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1 mt-1">
                                        <span className="material-symbols-outlined text-[16px]">location_on</span>
                                        2°14&apos;S, 114°53&apos;E
                                    </span>
                                </div>
                                <button
                                    onClick={() => setParcelOpen(false)}
                                    className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-surface-container-low text-outline transition-colors shrink-0"
                                    aria-label="Close parcel inspector"
                                >
                                    <span className="material-symbols-outlined text-[20px]">close</span>
                                </button>
                            </div>

                            {/* Panel Body */}
                            <div className="p-6 flex flex-col gap-6 bg-surface">
                                <div className="grid grid-cols-2 gap-4">
                                    {/* Land Risk Index */}
                                    <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-lg">
                                        <span className="font-label-md text-label-md text-on-surface-variant block mb-2 uppercase">
                                            Land Risk Index
                                        </span>
                                        <div className="flex items-end gap-2">
                                            <span className="font-stats-lg text-stats-lg text-primary leading-none">32</span>
                                            <span className="font-body-sm text-body-sm text-outline mb-1">/100</span>
                                        </div>
                                        <div className="mt-3 w-full bg-surface-container-high rounded-full h-1.5 overflow-hidden">
                                            <div className="bg-success h-1.5 rounded-full" style={{ width: '32%' }} />
                                        </div>
                                        <span className="font-label-md text-[10px] text-success mt-2 block tracking-wider uppercase">
                                            Low Risk
                                        </span>
                                    </div>

                                    {/* Soil Quality */}
                                    <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-lg">
                                        <span className="font-label-md text-label-md text-on-surface-variant block mb-2 uppercase">
                                            Soil Quality
                                        </span>
                                        <div className="flex items-center mt-1">
                                            <span className="font-headline-md text-headline-md text-primary">Good</span>
                                        </div>
                                        <div className="mt-3 flex gap-1">
                                            <div className="h-1.5 flex-1 bg-success rounded-l-full" />
                                            <div className="h-1.5 flex-1 bg-success" />
                                            <div className="h-1.5 flex-1 bg-success" />
                                            <div className="h-1.5 flex-1 bg-surface-container-high" />
                                            <div className="h-1.5 flex-1 bg-surface-container-high rounded-r-full" />
                                        </div>
                                        <span className="font-label-md text-[10px] text-outline mt-2 block tracking-wider uppercase">
                                            pH 6.8 — Loamy
                                        </span>
                                    </div>

                                    {/* Avg Elevation */}
                                    <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-lg">
                                        <span className="font-label-md text-label-md text-on-surface-variant block mb-2 uppercase">
                                            Avg Elevation
                                        </span>
                                        <div className="font-headline-md text-headline-md text-primary">380 m</div>
                                        <span className="font-label-md text-[10px] text-outline mt-2 block uppercase tracking-wider">
                                            Peak: 540 m MSL
                                        </span>
                                    </div>

                                    {/* Avg Slope */}
                                    <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-lg">
                                        <span className="font-label-md text-label-md text-on-surface-variant block mb-2 uppercase">
                                            Avg Slope
                                        </span>
                                        <div className="font-headline-md text-headline-md text-primary">14.2°</div>
                                        <span className="font-label-md text-[10px] text-success mt-2 block uppercase tracking-wider">
                                            Low Erosion Risk
                                        </span>
                                    </div>
                                </div>

                                {/* Historical Fire Data */}
                                <div className="bg-surface-container-lowest border border-border-subtle p-5 rounded-lg">
                                    <div className="flex justify-between items-center mb-4">
                                        <span className="font-label-md text-label-md text-on-surface-variant uppercase">
                                            Historical Fire Data
                                        </span>
                                        <button
                                            onClick={() => alert('Fire history options: export, change range, compare sector')}
                                            className="text-primary hover:bg-surface-container-low p-1 rounded transition-colors"
                                            aria-label="Historical fire data options"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">more_vert</span>
                                        </button>
                                    </div>

                                    <div className="h-32 flex items-end gap-2 pb-2 border-b border-border-subtle relative">
                                        <div className="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-20">
                                            <div className="border-t border-outline w-full" />
                                            <div className="border-t border-outline w-full" />
                                            <div className="border-t border-outline w-full" />
                                            <div className="border-t border-outline w-full" />
                                        </div>

                                        {FIRE_HISTORY.map((item) => (
                                            <div
                                                key={item.year}
                                                className="flex-1 flex flex-col justify-end items-center group relative h-full"
                                            >
                                                <div
                                                    className={`w-full ${item.color} group-hover:bg-primary/20 transition-colors rounded-t`}
                                                    style={{ height: item.height }}
                                                    title={`${item.year}: ${item.height} of peak incidence`}
                                                />
                                                <span
                                                    className={`font-label-md text-[9px] mt-2 ${
                                                        item.emphasis ? 'text-primary font-bold' : 'text-outline'
                                                    }`}
                                                >
                                                    {item.year}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Field Report */}
                                <div className="bg-surface-container-lowest border border-border-subtle p-4 rounded-lg flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-3 min-w-0">
                                        <div className="w-10 h-10 rounded-full bg-surface-container-low flex items-center justify-center border border-border-subtle shrink-0">
                                            <span className="material-symbols-outlined text-on-surface-variant">
                                                content_paste_search
                                            </span>
                                        </div>
                                        <div className="min-w-0">
                                            <span className="font-body-sm text-body-sm font-semibold text-primary block truncate">
                                                Field Report Available
                                            </span>
                                            <span className="font-label-md text-[11px] text-outline font-normal">
                                                Last updated 2 days ago
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => alert('Downloading Hydrology & Soil field report (PDF)')}
                                        className="text-primary hover:bg-surface-container-low p-2 rounded-full transition-colors flex items-center justify-center shrink-0"
                                        aria-label="Download field report"
                                    >
                                        <span className="material-symbols-outlined">download</span>
                                    </button>
                                </div>
                            </div>

                            {/* Panel Footer */}
                            <div className="p-4 border-t border-border-subtle bg-surface-container-lowest">
                                <button
                                    onClick={() => alert('Opening deep-dive analysis for Sector 7G-Alpha')}
                                    className="w-full py-2.5 px-4 bg-primary-container text-on-primary font-label-md text-label-md rounded hover:bg-primary transition-colors flex items-center justify-center gap-2"
                                >
                                    <span className="material-symbols-outlined text-[18px]">travel_explore</span>
                                    Inspect Area Deep-Dive
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-surface-container-lowest border border-dashed border-border-subtle rounded-lg p-8 flex flex-col items-center justify-center text-center gap-3">
                            <span className="material-symbols-outlined text-outline text-[32px]">touch_app</span>
                            <p className="font-body-sm text-body-sm text-on-surface-variant">
                                No parcel selected. Pick a polygon on the canvas to inspect it.
                            </p>
                            <button
                                onClick={() => setParcelOpen(true)}
                                className="font-label-md text-label-md text-primary hover:underline"
                            >
                                Reopen Sector 7G-Alpha
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
