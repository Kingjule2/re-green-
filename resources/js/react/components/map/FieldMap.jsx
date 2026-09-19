import React, { useCallback, useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Real basemaps for the field map.
 *
 * Every endpoint below is keyless and was verified from this machine to serve a
 * real 256x256 tile for Indonesian coordinates (checked at zoom 10 over Mount
 * Merapi, `10/826/533`). Attribution is required by each provider and is passed
 * to Leaflet so the active layer credits itself.
 *
 * @type {Array<{id: string, label: string, url: string, options: object}>}
 */
export const BASE_LAYERS = [
    {
        id: 'satellite',
        label: 'Satellite',
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        options: {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, GIS User Community',
        },
    },
    {
        id: 'relief',
        label: 'Terrain relief',
        url: 'https://services.arcgisonline.com/arcgis/rest/services/Elevation/World_Hillshade/MapServer/tile/{z}/{y}/{x}',
        options: {
            maxZoom: 16,
            attribution: 'Tiles &copy; Esri &mdash; World Hillshade',
        },
    },
    {
        id: 'topographic',
        label: 'Topographic',
        url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
        options: {
            maxZoom: 17,
            subdomains: 'abc',
            attribution: 'Map data &copy; OpenStreetMap contributors, SRTM | Style &copy; OpenTopoMap (CC-BY-SA)',
        },
    },
    {
        id: 'streets',
        label: 'Streets',
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        options: {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        },
    },
    {
        id: 'dark',
        label: 'Dark',
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        options: {
            maxZoom: 20,
            subdomains: 'abcd',
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
        },
    },
];

/** Elevation lookup for a clicked point (Copernicus DEM GLO-90, keyless, CORS-open). */
const ELEVATION_API = 'https://api.open-meteo.com/v1/elevation';

/** Indonesia, used until a surveyed point takes over. */
const DEFAULT_CENTER = [-2.5, 118];
const DEFAULT_ZOOM = 5;
const FOCUS_ZOOM = 13;

/**
 * The field map: real basemaps, one marker per surveyed point, and an elevation
 * probe so "tinggi rendahnya dataran" can be read straight off the map.
 *
 * @param {object} props
 * @param {Array<{id: number, latitude: number, longitude: number, label: string, tone?: string}>} props.points
 * @param {number|null} props.selectedId
 * @param {(id: number) => void} [props.onSelect]
 * @param {[number, number]|null} [props.focus] - [lat, lng] to fly to
 * @param {string} [props.className]
 */
export default function FieldMap({ points = [], selectedId = null, onSelect, focus = null, className = '' }) {
    const containerRef = useRef(null);
    const mapRef = useRef(null);
    const tileRef = useRef(null);
    const markersRef = useRef(null);
    const probeRequestRef = useRef(null);
    const [activeLayer, setActiveLayer] = useState('satellite');
    const [probe, setProbe] = useState(null);

    /** Ask the elevation API what the ground is at a coordinate. */
    const handleProbe = useCallback(async ({ latlng }) => {
        probeRequestRef.current?.abort();
        const controller = new AbortController();
        probeRequestRef.current = controller;

        setProbe({ latitude: latlng.lat, longitude: latlng.lng, loading: true, elevation: null, error: null });

        try {
            const response = await fetch(
                `${ELEVATION_API}?latitude=${latlng.lat.toFixed(5)}&longitude=${latlng.lng.toFixed(5)}`,
                { signal: controller.signal },
            );
            const payload = await response.json();

            setProbe({
                latitude: latlng.lat,
                longitude: latlng.lng,
                loading: false,
                elevation: payload?.elevation?.[0] ?? null,
                error: null,
            });
        } catch (error) {
            if (error.name === 'AbortError') return;
            setProbe({
                latitude: latlng.lat,
                longitude: latlng.lng,
                loading: false,
                elevation: null,
                error: 'Elevation lookup failed',
            });
        }
    }, []);

    // Create the map once; Leaflet owns the DOM below this node from here on.
    useEffect(() => {
        if (!containerRef.current || mapRef.current) return undefined;

        const map = L.map(containerRef.current, {
            center: DEFAULT_CENTER,
            zoom: DEFAULT_ZOOM,
            zoomControl: true,
            worldCopyJump: true,
        });

        mapRef.current = map;
        markersRef.current = L.layerGroup().addTo(map);
        L.control.scale({ imperial: false, position: 'bottomright' }).addTo(map);
        map.on('click', handleProbe);
        // The container is often sized after construction (grid/flex layout).
        window.setTimeout(() => map.invalidateSize(), 0);

        return () => {
            probeRequestRef.current?.abort();
            map.remove();
            mapRef.current = null;
            markersRef.current = null;
            tileRef.current = null;
        };
    }, [handleProbe]);

    // Swap the basemap when the layer changes.
    useEffect(() => {
        const map = mapRef.current;
        if (!map) return;

        const layer = BASE_LAYERS.find((entry) => entry.id === activeLayer) ?? BASE_LAYERS[0];

        if (tileRef.current) map.removeLayer(tileRef.current);
        tileRef.current = L.tileLayer(layer.url, layer.options).addTo(map);
    }, [activeLayer]);

    // Re-draw the surveyed points whenever they or the selection change.
    useEffect(() => {
        const group = markersRef.current;
        if (!group) return;

        group.clearLayers();

        points.forEach((point) => {
            const selected = point.id === selectedId;

            const marker = L.marker([point.latitude, point.longitude], {
                icon: L.divIcon({
                    className: 'regreen-map-pin',
                    html: `<span class="regreen-map-pin__dot${selected ? ' regreen-map-pin__dot--active' : ''}"></span>`,
                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                }),
                title: point.label,
                keyboard: true,
                alt: point.label,
                riseOnHover: true,
            });

            marker.on('click', (event) => {
                // Selecting a point must not also fire the elevation probe.
                L.DomEvent.stopPropagation(event);
                onSelect?.(point.id);
            });

            marker.bindTooltip(point.label, { direction: 'top', offset: [0, -10] });
            marker.addTo(group);
        });
    }, [points, selectedId, onSelect]);

    // Fly to the focused point (or out to the default view when cleared).
    useEffect(() => {
        const map = mapRef.current;
        if (!map) return;

        if (focus) {
            map.flyTo(focus, FOCUS_ZOOM, { duration: 0.8 });
        }
    }, [focus]);

    return (
        <div className={`relative overflow-hidden rounded-xl border border-border-subtle bg-surface-container-lowest ${className}`}>
            <div ref={containerRef} className="h-full w-full" role="application" aria-label="Survey map" />

            {/* Basemap switcher */}
            <div className="absolute left-3 top-3 z-[500] flex flex-wrap gap-1 rounded-lg border border-border-subtle bg-surface/95 p-1 card-shadow backdrop-blur-sm">
                {BASE_LAYERS.map((layer) => (
                    <button
                        key={layer.id}
                        type="button"
                        onClick={() => setActiveLayer(layer.id)}
                        aria-pressed={activeLayer === layer.id}
                        className={`rounded-md px-2.5 py-1 font-label-md text-label-md transition-colors ${
                            activeLayer === layer.id
                                ? 'bg-primary text-on-primary'
                                : 'text-on-surface-variant hover:bg-surface-container-high'
                        }`}
                    >
                        {layer.label}
                    </button>
                ))}
            </div>

            {/* Elevation probe readout */}
            <div className="absolute bottom-3 left-3 z-[500] max-w-[280px] rounded-lg border border-border-subtle bg-surface/95 px-3 py-2 font-body-sm text-body-sm card-shadow backdrop-blur-sm">
                {probe === null ? (
                    <span className="text-on-surface-variant">
                        Click the map to read the elevation of that point.
                    </span>
                ) : (
                    <span className="text-on-surface-variant">
                        <span className="font-medium text-on-surface">
                            {probe.loading
                                ? 'Reading elevation…'
                                : probe.error
                                  ? probe.error
                                  : `${probe.elevation?.toLocaleString('en-US')} m`}
                        </span>
                        {!probe.loading && !probe.error && (
                            <>
                                {' '}
                                at {probe.latitude.toFixed(5)}, {probe.longitude.toFixed(5)} — Copernicus DEM via
                                Open-Meteo
                            </>
                        )}
                    </span>
                )}
            </div>

            {points.length === 0 && (
                <div className="pointer-events-none absolute inset-x-0 top-16 z-[400] flex justify-center">
                    <p className="rounded-full border border-border-subtle bg-surface/95 px-4 py-1.5 font-body-sm text-body-sm text-on-surface-variant card-shadow">
                        No surveyed points yet — run a drone analysis with coordinates and it will appear here.
                    </p>
                </div>
            )}
        </div>
    );
}
