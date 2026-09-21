/**
 * Interactive spatial plan for turning open land into a usable farm.
 *
 * The plan stays deliberately honest: it is a first-pass allocation derived
 * from the recorded land area, not a survey-grade engineering drawing. Farmers
 * can compare three layout models, change the planning area, and inspect the
 * purpose and field notes for every zone.
 */
import React, { useEffect, useMemo, useState } from 'react';
import { formatHectares, formatNumber } from '@/react/lib/format';
import { Chip, MetricTile, Panel } from '@/react/components/farmer/primitives';

const PRESET_OPTIONS = [
    {
        id: 'paddy',
        label: 'Sawah irigasi',
        description: 'Blok budidaya bersebelahan dengan saluran air utama dan sekunder.',
        icon: 'water',
    },
    {
        id: 'agroforestry',
        label: 'Agroforestri',
        description: 'Teras kontur, tanaman tahunan, dan pengendalian limpasan lereng.',
        icon: 'forest',
    },
    {
        id: 'horticulture',
        label: 'Hortikultura',
        description: 'Bedengan intensif dengan area sortir, kemas, dan pendingin hasil.',
        icon: 'yard',
    },
];

const PRESET_ZONE_DEFINITIONS = {
    paddy: [
        {
            id: 'plot_a',
            category: 'crop',
            areaPct: 37.5,
            icon: 'potted_plant',
            shortLabel: 'PETAK A',
            title: 'Petak sawah / kebun A',
            subtitle: 'Zona budidaya utama',
            description: 'Blok produksi utama untuk tanaman yang paling sesuai dengan kondisi lahan.',
            specs: (crop) => `Prioritas ${crop}. Terapkan bedengan atau pematang stabil dan sisakan jalur inspeksi di tepi petak.`,
            tip: 'Olah tanah minimal dua minggu sebelum tanam agar lahan terbuka kembali memiliki aerasi dan struktur yang baik.',
        },
        {
            id: 'water_branch',
            category: 'water',
            areaPct: 4,
            icon: 'water_drop',
            shortLabel: 'SALURAN SEKUNDER',
            title: 'Saluran irigasi sekunder',
            subtitle: 'Pembagi air antar petak',
            description: 'Saluran vertikal membagi debit ke petak A dan B agar distribusi air lebih merata.',
            specs: 'Lebar rencana 1,2 m dengan pintu air manual dan kemiringan dasar yang mengikuti kontur.',
            tip: 'Tempatkan inlet di elevasi yang lebih tinggi dan sediakan titik inspeksi agar endapan mudah dibersihkan.',
        },
        {
            id: 'plot_b',
            category: 'crop',
            areaPct: 37.5,
            icon: 'agriculture',
            shortLabel: 'PETAK B',
            title: 'Petak sawah / kebun B',
            subtitle: 'Zona budidaya rotasi',
            description: 'Blok kedua untuk rotasi tanaman pangan dan menjaga siklus tanah tetap sehat.',
            specs: (crop) => `Rotasikan ${crop} dengan palawija pada musim berikutnya untuk memutus siklus hama dan menjaga produktivitas.`,
            tip: 'Catat tanggal tanam dan panen per blok agar rotasi tanaman bisa dievaluasi pada musim berikutnya.',
        },
        {
            id: 'water_main',
            category: 'water',
            areaPct: 6,
            icon: 'waves',
            shortLabel: 'SALURAN UTAMA',
            title: 'Saluran air / irigasi utama',
            subtitle: 'Tulang punggung distribusi',
            description: 'Saluran horizontal menerima air dari sumber atau embung lalu membaginya ke seluruh blok.',
            specs: 'Lebar rencana 1,8 m dan kedalaman 80 cm; gunakan lining batu kali atau tanah padat sesuai daya rembes.',
            tip: 'Sisakan ruang sempadan di kedua sisi agar saluran aman saat debit naik dan mudah dirawat.',
        },
        {
            id: 'facility',
            category: 'facility',
            areaPct: 8,
            icon: 'warehouse',
            shortLabel: 'GUDANG & FASILITAS',
            title: 'Gudang dan fasilitas tani',
            subtitle: 'Alsintan, pupuk, dan pascapanen',
            description: 'Pusat operasional untuk gudang alsintan, benih, pupuk, lantai jemur, dan saung tani.',
            specs: 'Tempatkan di sisi jalan dengan lantai lebih tinggi dari tanah sekitar; pisahkan bahan kimia dari hasil panen.',
            tip: 'Dekatkan bangunan ke akses kendaraan agar bongkar muat tidak melewati petak budidaya.',
        },
        {
            id: 'road',
            category: 'infrastructure',
            areaPct: 7,
            icon: 'local_shipping',
            shortLabel: 'JALAN USAHA TANI',
            title: 'Jalan usaha tani',
            subtitle: 'Akses traktor dan panen',
            description: 'Koridor kendaraan untuk traktor, gerobak, dan mobil pengangkut hasil panen.',
            specs: 'Lebar rencana 3 m dengan bahu jalan dan parit kecil supaya akses tetap dapat digunakan saat hujan.',
            tip: 'Pastikan jalan terhubung ke pintu masuk lahan dan setiap blok memiliki titik putar atau titik bongkar.',
        },
    ],
    agroforestry: [
        {
            id: 'plot_a',
            category: 'crop',
            areaPct: 38,
            icon: 'forest',
            shortLabel: 'TERAS A',
            title: 'Blok agroforestri utama',
            subtitle: 'Teras kontur dan tanaman tahunan',
            description: 'Zona tajuk bertingkat untuk tanaman tahunan dengan pohon pelindung penahan erosi.',
            specs: (crop) => `Tanam ${crop} sejajar kontur bersama gamal atau lamtoro sebagai pelindung dan pemasok biomassa.`,
            tip: 'Jaga garis tanam mengikuti kontur; jangan membuat jalur air lurus menuruni lereng.',
        },
        {
            id: 'water_branch',
            category: 'water',
            areaPct: 6,
            icon: 'water_drop',
            shortLabel: 'RORAK KONTUR',
            title: 'Rorak dan parit kontur',
            subtitle: 'Resapan pengendali erosi',
            description: 'Titik resapan menangkap limpasan dan sedimen sebelum masuk ke area budidaya.',
            specs: 'Rorak awal 200 × 50 × 50 cm dengan jarak mengikuti kontur dan kondisi kemiringan setempat.',
            tip: 'Periksa rorak setelah hujan besar dan keluarkan sedimen bila kapasitas tampung berkurang.',
        },
        {
            id: 'plot_b',
            category: 'crop',
            areaPct: 32,
            icon: 'yard',
            shortLabel: 'TUMPANG SARI B',
            title: 'Blok kebun tumpang sari',
            subtitle: 'Tanaman sela dan pangan',
            description: 'Ruang bawah tajuk untuk pisang, jahe, kacang-kacangan, atau tanaman sela bernilai.',
            specs: 'Pilih tanaman sela dengan perakaran dangkal agar tidak berebut air dengan tanaman tahunan utama.',
            tip: 'Gunakan mulsa organik dari pangkasan untuk menjaga kelembapan dan menambah bahan organik tanah.',
        },
        {
            id: 'water_main',
            category: 'water',
            areaPct: 6,
            icon: 'water',
            shortLabel: 'EMBUNG & RESAPAN',
            title: 'Embung penampung air',
            subtitle: 'Cadangan musim kering',
            description: 'Tampungan air hujan untuk irigasi tetes dan kebutuhan pemeliharaan saat curah hujan turun.',
            specs: 'Embung awal dengan kedalaman sekitar 2,5 m; ukuran final harus mengikuti kontur dan volume limpasan.',
            tip: 'Pasang saluran masuk berjeruji dan spillway agar tampungan tidak jebol saat hujan ekstrem.',
        },
        {
            id: 'facility',
            category: 'facility',
            areaPct: 10,
            icon: 'storefront',
            shortLabel: 'PENGOLAHAN',
            title: 'Gudang pengolahan hasil',
            subtitle: 'Pengeringan dan penyimpanan',
            description: 'Fasilitas fermentasi, pengeringan, dan penyimpanan hasil kebun sebelum dipasarkan.',
            specs: 'Sediakan rumah pengering surya, ruang fermentasi, dan area alsintan yang terlindung dari hujan.',
            tip: 'Pisahkan alur bahan mentah dan hasil jadi supaya area pengolahan tetap bersih dan mudah diawasi.',
        },
        {
            id: 'road',
            category: 'infrastructure',
            areaPct: 8,
            icon: 'alt_route',
            shortLabel: 'JALUR KONTUR',
            title: 'Jalur panen dan sabuk hijau',
            subtitle: 'Akses lereng yang aman',
            description: 'Jalur setapak, tangga batu, dan sabuk hijau vetiver untuk menjaga akses di area miring.',
            specs: 'Gunakan kemiringan melintang kecil, pijakan bertekstur, dan tanaman pengikat tanah pada tepi jalur.',
            tip: 'Hindari memadatkan seluruh permukaan; sisakan area resapan di sisi bawah jalur.',
        },
    ],
    horticulture: [
        {
            id: 'plot_a',
            category: 'crop',
            areaPct: 38,
            icon: 'view_week',
            shortLabel: 'BEDENGAN A',
            title: 'Blok bedengan intensif',
            subtitle: 'Produksi hortikultura utama',
            description: 'Bedengan teratur untuk sayuran daun dan buah dengan jadwal tanam bertahap.',
            specs: (crop) => `Atur bedengan untuk ${crop} dengan lebar sekitar 1 m, tinggi 30 cm, dan ruang servis di antaranya.`,
            tip: 'Buat jadwal tanam bergilir agar panen tidak menumpuk dan penggunaan air lebih stabil.',
        },
        {
            id: 'water_branch',
            category: 'water',
            areaPct: 5,
            icon: 'water_drop',
            shortLabel: 'DRAINASE',
            title: 'Drainase keliling lahan',
            subtitle: 'Mencegah genangan',
            description: 'Parit drainase mengarahkan kelebihan air menjauh dari bedengan dan area akar.',
            specs: 'Lebar awal 60 cm dengan outlet aman; kedalaman final perlu disesuaikan dengan tekstur tanah.',
            tip: 'Pasang saringan di outlet agar tanah halus dan sampah tidak menyumbat saluran.',
        },
        {
            id: 'plot_b',
            category: 'crop',
            areaPct: 30,
            icon: 'roofing',
            shortLabel: 'NAUNGAN B',
            title: 'Blok pembibitan dan naungan',
            subtitle: 'Bibit unggul dan tanaman rentan',
            description: 'Area pembibitan dan tanaman yang membutuhkan perlindungan dari hujan deras atau panas berlebih.',
            specs: 'Gunakan rangka bambu atau baja ringan dengan plastik UV dan akses air tetes yang terukur.',
            tip: 'Jaga sirkulasi udara di bawah naungan agar kelembapan tidak memicu jamur.',
        },
        {
            id: 'water_main',
            category: 'water',
            areaPct: 7,
            icon: 'waves',
            shortLabel: 'TANDON & TETES',
            title: 'Tandon dan irigasi tetes',
            subtitle: 'Pengairan presisi',
            description: 'Tandon, pipa induk, dan jaringan tetes untuk mengantar air langsung ke pangkal tanaman.',
            specs: 'Pipa induk sekitar 2 inci dengan filter, katup pembagi, dan tandon nutrisi yang mudah dirawat.',
            tip: 'Bilas filter secara berkala dan ukur debit setiap zona sebelum menambah panjang selang tetes.',
        },
        {
            id: 'facility',
            category: 'facility',
            areaPct: 12,
            icon: 'inventory',
            shortLabel: 'SORTIR & DINGIN',
            title: 'Gudang sortir dan pendingin',
            subtitle: 'Pascapanen hortikultura',
            description: 'Area pencucian, sortir, pengemasan, penimbangan, dan penyimpanan singkat hasil panen.',
            specs: 'Letakkan dekat jalan loading; sediakan lantai mudah dicuci, timbangan, rak, dan pendingin sementara.',
            tip: 'Jaga jalur hasil panen satu arah dari area kotor ke area kemas agar kualitas tetap konsisten.',
        },
        {
            id: 'road',
            category: 'infrastructure',
            areaPct: 8,
            icon: 'local_shipping',
            shortLabel: 'LOADING',
            title: 'Jalan loading dan kompos',
            subtitle: 'Akses pick-up dan bahan organik',
            description: 'Jalur kendaraan untuk mengangkut sayur, pupuk kandang, media tanam, dan kompos.',
            specs: 'Lebar rencana 3,5 m di sisi fasilitas dengan area putar dan permukaan yang tidak mudah becek.',
            tip: 'Pisahkan titik bongkar kompos dari pintu gudang hasil agar tidak terjadi kontaminasi silang.',
        },
    ],
};

const BLUEPRINT_LAYOUT = {
    plot_a: { placement: 'col-start-1 row-start-1', compact: false },
    water_branch: { placement: 'col-start-2 row-start-1', compact: true },
    plot_b: { placement: 'col-start-3 row-start-1', compact: false },
    water_main: { placement: 'col-span-3 row-start-2', compact: true },
    facility: { placement: 'col-start-1 row-start-3', compact: false },
    road: { placement: 'col-start-2 col-span-2 row-start-3', compact: false },
};

const ZONE_STYLES = {
    crop: {
        idle: 'border-emerald-500/60 bg-emerald-950/50 hover:border-emerald-300 hover:bg-emerald-950/75',
        active: 'border-emerald-300 bg-emerald-900/85 ring-2 ring-emerald-300/50 shadow-[0_0_24px_rgba(52,211,153,0.24)]',
        badge: 'bg-emerald-950/85 text-emerald-200',
        title: 'text-emerald-50 group-hover:text-emerald-200',
        meta: 'text-emerald-200/80',
        legend: 'border-emerald-300 bg-emerald-500',
        accent: '#2f9e62',
    },
    water: {
        idle: 'border-sky-400/70 bg-sky-950/55 hover:border-sky-200 hover:bg-sky-900/75',
        active: 'border-sky-200 bg-sky-800/85 ring-2 ring-sky-200/50 shadow-[0_0_24px_rgba(56,189,248,0.28)]',
        badge: 'bg-sky-950/85 text-sky-100',
        title: 'text-sky-50 group-hover:text-sky-100',
        meta: 'text-sky-200/85',
        legend: 'border-sky-200 bg-sky-500',
        accent: '#2f8fb8',
    },
    facility: {
        idle: 'border-amber-500/65 bg-amber-950/55 hover:border-amber-300 hover:bg-amber-950/75',
        active: 'border-amber-300 bg-amber-900/85 ring-2 ring-amber-300/50 shadow-[0_0_24px_rgba(251,191,36,0.24)]',
        badge: 'bg-amber-950/85 text-amber-100',
        title: 'text-amber-50 group-hover:text-amber-100',
        meta: 'text-amber-200/85',
        legend: 'border-amber-200 bg-amber-600',
        accent: '#c98529',
    },
    infrastructure: {
        idle: 'border-stone-400/65 bg-stone-900/70 hover:border-stone-200 hover:bg-stone-800/80',
        active: 'border-stone-200 bg-stone-700/85 ring-2 ring-stone-200/50 shadow-[0_0_24px_rgba(214,211,209,0.2)]',
        badge: 'bg-stone-800/90 text-stone-100',
        title: 'text-stone-50 group-hover:text-stone-100',
        meta: 'text-stone-200/80',
        legend: 'border-stone-200 bg-stone-500',
        accent: '#8b7355',
    },
};

function BlueprintZone({ zone, active, onSelect }) {
    const layout = BLUEPRINT_LAYOUT[zone.id];
    const tone = ZONE_STYLES[zone.category];

    if (!layout) {
        return null;
    }

    return (
        <button
            type="button"
            aria-pressed={active}
            aria-label={`Pilih ${zone.title}, ${formatHectares(zone.areaHa)}`}
            onClick={() => onSelect(zone.id)}
            className={`group relative min-h-0 min-w-0 overflow-hidden rounded-lg border-2 p-2 text-left transition-colors duration-200 motion-reduce:transition-none focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 ${layout.placement} ${
                zone.id === 'water_branch' ? 'flex flex-col items-center justify-center gap-3' : layout.compact ? 'flex items-center justify-between gap-2' : 'flex flex-col justify-between gap-2 sm:p-3'
            } ${active ? tone.active : tone.idle}`}
        >
            {layout.compact ? (
                <>
                    <div className={`flex min-w-0 items-center gap-1.5 ${zone.id === 'water_branch' ? 'flex-col' : ''}`}>
                        {zone.id !== 'water_branch' && (
                            <span className="material-symbols-outlined shrink-0 text-[16px] text-white" aria-hidden="true">
                                {zone.icon}
                            </span>
                        )}
                        <span className={`font-label-md text-[11px] font-bold text-white ${zone.id === 'water_branch' ? '[writing-mode:vertical-rl]' : ''}`}>
                            {zone.shortLabel}
                        </span>
                    </div>
                    <span className={`shrink-0 text-[11px] font-bold text-white ${zone.id === 'water_branch' ? '[writing-mode:vertical-rl]' : ''}`}>
                        {formatHectares(zone.areaHa)}
                    </span>
                </>
            ) : (
                <>
                    <div className="flex min-w-0 flex-col items-start gap-2 lg:flex-row lg:justify-between">
                        <span className={`inline-flex min-w-0 flex-wrap items-center gap-1 rounded px-1.5 py-0.5 font-label-md text-[11px] font-bold ${tone.badge}`}>
                            <span className="material-symbols-outlined shrink-0 text-[14px]" aria-hidden="true">
                                {zone.icon}
                            </span>
                            <span className="break-words">{zone.shortLabel}</span>
                        </span>
                        <span className={`shrink-0 text-xs font-semibold tabular-nums ${tone.meta}`}>
                            {formatHectares(zone.areaHa)}
                        </span>
                    </div>
                    <div className="hidden min-w-0 sm:block">
                        <p className={`font-headline-sm text-sm font-bold ${tone.title}`}>{zone.title}</p>
                        <p className={`mt-0.5 text-xs ${tone.meta}`}>{zone.subtitle}</p>
                    </div>
                </>
            )}
        </button>
    );
}

function PresetButton({ option, selected, onSelect }) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={() => onSelect(option.id)}
            className={`inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-left font-body-sm text-xs font-semibold transition-colors motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 ${
                selected
                    ? 'border-primary bg-primary text-on-primary'
                    : 'border-border-subtle bg-surface-container-lowest text-on-surface-variant hover:border-primary/40 hover:bg-surface-container-low hover:text-on-surface'
            }`}
        >
            <span className="material-symbols-outlined text-[17px]" aria-hidden="true">
                {option.icon}
            </span>
            {option.label}
        </button>
    );
}

export default function FarmZoningPlanner({ land, latestAnalysis = null, className = '' }) {
    const storedAreaHa = Number(land?.area_ha);
    const defaultAreaHa = Number.isFinite(storedAreaHa) && storedAreaHa > 0 ? String(storedAreaHa) : '';
    const selectedCrop = land?.selected_crop?.name ?? 'tanaman yang sesuai hasil uji tanah dan iklim';
    const rawSlope = latestAnalysis?.terrain?.profile?.slope_deg;
    const slopeDeg = rawSlope != null && Number.isFinite(Number(rawSlope)) ? Number(rawSlope) : null;

    const [preset, setPreset] = useState('paddy');
    const [activeZoneId, setActiveZoneId] = useState('plot_a');
    const [areaInput, setAreaInput] = useState(String(defaultAreaHa));
    const [showSpecs, setShowSpecs] = useState(true);

    useEffect(() => {
        setAreaInput(String(defaultAreaHa));
    }, [land?.id, defaultAreaHa]);

    const typedAreaHa = Number(String(areaInput).replace(',', '.'));
    const validArea = Number.isFinite(typedAreaHa) && typedAreaHa > 0 && Number.isFinite(typedAreaHa * 10000);
    const totalAreaHa = validArea ? typedAreaHa : 0;

    const zones = useMemo(
        () =>
            PRESET_ZONE_DEFINITIONS[preset].map((definition) => ({
                ...definition,
                areaHa: totalAreaHa * (definition.areaPct / 100),
                specs: typeof definition.specs === 'function' ? definition.specs(selectedCrop) : definition.specs,
            })),
        [preset, selectedCrop, totalAreaHa],
    );

    const activeZone = zones.find((zone) => zone.id === activeZoneId) ?? zones[0];
    const cropArea = zones.filter((zone) => zone.category === 'crop').reduce((sum, zone) => sum + zone.areaHa, 0);
    const waterArea = zones.filter((zone) => zone.category === 'water').reduce((sum, zone) => sum + zone.areaHa, 0);
    const facilityArea = zones
        .filter((zone) => zone.category === 'facility' || zone.category === 'infrastructure')
        .reduce((sum, zone) => sum + zone.areaHa, 0);
    const allocatedPct = zones.reduce((sum, zone) => sum + zone.areaPct, 0);
    const activePreset = PRESET_OPTIONS.find((option) => option.id === preset) ?? PRESET_OPTIONS[0];

    return (
        <Panel
            title="Blueprint tata ruang lahan"
            subtitle="Ubah lahan kosong menjadi rencana awal budidaya, air, fasilitas, dan akses yang mudah dibaca petani."
            icon="architecture"
            className={`farm-zoning-planner ${className}`}
            action={<Chip label="Rencana awal · interaktif" />}
        >
            <div className="space-y-5">
                <div className="grid gap-4 rounded-xl border border-border-subtle bg-surface-container-low p-4 lg:grid-cols-[minmax(0,1fr)_minmax(300px,0.8fr)]">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">
                                tune
                            </span>
                            <h3 className="font-headline-sm text-headline-sm text-on-surface">Pilih model tata ruang</h3>
                        </div>
                        <p className="mt-1 max-w-2xl font-body-sm text-xs text-on-surface-variant">{activePreset.description}</p>
                        <div className="mt-3 flex flex-wrap gap-2" role="group" aria-label="Model tata ruang">
                            {PRESET_OPTIONS.map((option) => (
                                <PresetButton key={option.id} option={option} selected={preset === option.id} onSelect={setPreset} />
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border border-border-subtle bg-surface-container-lowest p-3">
                        <label htmlFor="planning-area" className="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">
                            Luas lahan untuk simulasi
                        </label>
                        <div className="mt-2 flex items-center gap-2">
                            <input
                                id="planning-area"
                                type="number"
                                min="0"
                                step="any"
                                inputMode="decimal"
                                value={areaInput}
                                onChange={(event) => setAreaInput(event.target.value)}
                                aria-invalid={!validArea}
                                aria-describedby={validArea ? 'planning-area-hint' : 'planning-area-hint planning-area-error'}
                                className="w-full rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2 font-headline-sm text-headline-sm text-on-surface outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/15"
                            />
                            <span className="font-body-sm text-body-sm text-on-surface-variant">ha</span>
                        </div>
                        <p id="planning-area-hint" className="mt-1 font-body-sm text-[11px] text-on-surface-variant">
                            {defaultAreaHa
                                ? `Simulasi tidak mengubah luas tersimpan: ${formatHectares(storedAreaHa)}.`
                                : 'Luas lahan belum tersedia. Masukkan luas untuk membuat simulasi.'}
                        </p>
                    </div>
                </div>
                {!validArea && (
                    <p id="planning-area-error" role="alert" className="font-body-sm text-sm text-critical">
                        Masukkan luas lahan berupa angka lebih besar dari nol.
                    </p>
                )}
                <div hidden={!validArea} className="space-y-5">

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3" aria-live="polite">
                    <MetricTile
                        label="Zona budidaya"
                        value={formatHectares(cropArea)}
                        unit={`(${formatNumber((cropArea / (totalAreaHa || 1)) * 100, 1)}%)`}
                        icon="eco"
                        tone="success"
                        hint="Petak sawah, kebun, atau bedengan"
                    />
                    <MetricTile
                        label="Zona air"
                        value={formatHectares(waterArea)}
                        unit={`(${formatNumber((waterArea / (totalAreaHa || 1)) * 100, 1)}%)`}
                        icon="water"
                        hint="Saluran, embung, atau drainase"
                    />
                    <MetricTile
                        label="Fasilitas & akses"
                        value={formatHectares(facilityArea)}
                        unit={`(${formatNumber((facilityArea / (totalAreaHa || 1)) * 100, 1)}%)`}
                        icon="warehouse"
                        hint="Gudang, pascapanen, dan jalan tani"
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-700 bg-[#0d1820] p-3 shadow-[0_16px_40px_rgba(15,23,42,0.18)] sm:p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-700/80 pb-3">
                        <div>
                            <div className="flex items-center gap-2 text-slate-100">
                                <span className="material-symbols-outlined text-[20px] text-emerald-300" aria-hidden="true">
                                    grid_4x4
                                </span>
                                <h3 className="font-label-md text-xs font-bold uppercase tracking-[0.12em]">
                                    Blueprint spasial lahan
                                </h3>
                            </div>
                            <p className="mt-1 pl-7 font-body-sm text-[11px] text-slate-400">
                                {land?.name ?? 'Lahan petani'} · {formatHectares(totalAreaHa)} · {activePreset.label}
                            </p>
                        </div>
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-slate-800 px-2.5 py-1 font-body-sm text-[11px] text-slate-300">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true" />
                            Pilih petak untuk melihat spesifikasi
                        </span>
                    </div>

                    <div
                        className="relative mx-auto mt-4 w-full max-w-5xl rounded-xl border-[3px] border-slate-600 bg-[#111f29] px-2 py-6 sm:px-3"
                        style={{
                            backgroundImage:
                                'linear-gradient(rgb(148 163 184 / 0.08) 1px, transparent 1px), linear-gradient(90deg, rgb(148 163 184 / 0.08) 1px, transparent 1px)',
                            backgroundSize: '32px 32px',
                        }}
                        role="group"
                        aria-label={`Blueprint pembagian ${formatHectares(totalAreaHa)} untuk ${activePreset.label}`}
                    >
                        <div className="pointer-events-none absolute left-3 top-1 z-10 font-mono text-[9px] uppercase tracking-[0.12em] text-slate-400 sm:text-[10px]">
                            Skema zonasi · tidak berskala
                        </div>
                        <div className="pointer-events-none absolute bottom-1 right-3 z-10 font-mono text-[9px] uppercase tracking-[0.12em] text-slate-400 sm:text-[10px]">
                            {slopeDeg === null ? 'Kemiringan belum tersedia' : `Kemiringan · ${formatNumber(slopeDeg, 1)}°`}
                        </div>

                        <div className="grid w-full grid-cols-[minmax(0,1fr)_44px_minmax(0,1fr)] grid-rows-[200px_56px_156px] gap-1.5 sm:grid-rows-[260px_56px_180px] sm:gap-2">
                            {zones.map((zone) => (
                                <BlueprintZone
                                    key={zone.id}
                                    zone={zone}
                                    active={activeZone?.id === zone.id}
                                    onSelect={(zoneId) => {
                                        setActiveZoneId(zoneId);
                                        setShowSpecs(true);
                                    }}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[11px] font-medium text-slate-300">
                        {[
                            { category: 'crop', label: 'Petak budidaya' },
                            { category: 'water', label: 'Air & irigasi' },
                            { category: 'facility', label: 'Gudang & pascapanen' },
                            { category: 'infrastructure', label: 'Jalan usaha tani' },
                        ].map((item) => (
                            <span key={item.category} className="inline-flex items-center gap-1.5">
                                <span className={`h-3 w-3 rounded-sm border ${ZONE_STYLES[item.category].legend}`} aria-hidden="true" />
                                {item.label}
                            </span>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border-subtle bg-surface-container-low px-3 py-2.5">
                    <div className="flex items-center gap-2">
                        <span className="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">
                            data_usage
                        </span>
                        <p className="font-body-sm text-xs text-on-surface-variant">
                            Total alokasi <strong className="text-on-surface">{formatNumber(allocatedPct, 1)}%</strong> dari luas simulasi.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowSpecs((visible) => !visible)}
                        aria-expanded={showSpecs}
                        aria-controls="planning-zone-details"
                        className="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-border-subtle bg-surface-container-lowest px-3 py-2 font-body-sm text-xs font-semibold text-on-surface transition-colors motion-reduce:transition-none hover:border-primary/40 hover:bg-surface-container-high focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        <span className="material-symbols-outlined text-[17px]" aria-hidden="true">
                            {showSpecs ? 'expand_less' : 'expand_more'}
                        </span>
                        {showSpecs ? 'Sembunyikan spesifikasi' : `Lihat detail ${activeZone?.shortLabel ?? 'petak'}`}
                    </button>
                </div>

                {activeZone && (
                    <section id="planning-zone-details" hidden={!showSpecs} className="rounded-xl border border-border-subtle bg-surface-container-low p-4 sm:p-5" aria-live="polite">
                        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border-subtle pb-3">
                            <div className="flex items-start gap-3">
                                <span
                                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white"
                                    style={{ backgroundColor: ZONE_STYLES[activeZone.category].accent }}
                                    aria-hidden="true"
                                >
                                    <span className="material-symbols-outlined text-[22px]">{activeZone.icon}</span>
                                </span>
                                <div>
                                    <h3 className="font-headline-sm text-headline-sm text-on-surface">{activeZone.title}</h3>
                                    <p className="mt-0.5 font-body-sm text-xs text-on-surface-variant">
                                        {formatHectares(activeZone.areaHa)} · {formatNumber(activeZone.areaPct, 1)}% dari luas lahan
                                    </p>
                                </div>
                            </div>
                            <Chip label={`${formatNumber(activeZone.areaHa * 10000, 0)} m²`} />
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="font-label-md text-xs font-bold uppercase tracking-wider text-on-surface-variant">Fungsi peruntukan</p>
                                <p className="mt-1 font-body-sm text-body-sm text-on-surface">{activeZone.description}</p>
                            </div>
                            <div>
                                <p className="font-label-md text-xs font-bold uppercase tracking-wider text-on-surface-variant">Spesifikasi awal</p>
                                <p className="mt-1 font-body-sm text-body-sm text-on-surface">{activeZone.specs}</p>
                            </div>
                        </div>

                        <div className="mt-4 flex items-start gap-2.5 rounded-lg bg-surface-container-highest/55 p-3.5">
                            <span className="material-symbols-outlined text-[19px] text-primary" aria-hidden="true">
                                lightbulb
                            </span>
                            <p className="font-body-sm text-xs text-on-surface-variant">
                                <strong className="text-on-surface">Tips pelaksanaan: </strong>
                                {activeZone.tip}
                            </p>
                        </div>
                    </section>
                )}

                <p className="font-body-sm text-[11px] leading-5 text-on-surface-variant">
                    Catatan: ukuran dan posisi pada blueprint adalah estimasi awal untuk diskusi. Verifikasi kemiringan, sumber air,
                    batas kepemilikan, akses kendaraan, dan aturan setempat sebelum pembangunan fisik.
                </p>
                </div>
            </div>
        </Panel>
    );
}
