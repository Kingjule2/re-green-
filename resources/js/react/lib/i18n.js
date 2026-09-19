/**
 * Indonesian vocabulary for the machine keys the API returns.
 *
 * The analysis payload keeps stable machine keys (`burn_scar`, `soil_exposure`,
 * `native_reforestation`, `improving`, …) and English prose, because that
 * payload is also the evidence record a partner or an auditor reads. The UI
 * renders the keys through this dictionary, so the farmer-facing text is
 * Indonesian while the data underneath stays comparable across versions.
 */

export const COVER_LABELS = {
    dense_vegetation: 'Vegetasi Rapat',
    sparse_vegetation: 'Vegetasi Jarang',
    bare_soil: 'Tanah Terbuka',
    charred_soil: 'Tanah Terbakar',
    water: 'Air',
    built_area: 'Area Terbangun',
    other: 'Lainnya',
};

export const ISSUE_LABELS = {
    burn_scar: 'Bekas Bakaran',
    vegetation_loss: 'Kehilangan Vegetasi',
    soil_exposure: 'Tanah Terbuka',
    fragmented_vegetation: 'Vegetasi Terfragmentasi',
    slope_erosion_risk: 'Risiko Erosi Lereng',
    slope_instability: 'Lereng Tidak Stabil',
};

export const SEVERITY_LABELS = {
    unburned: 'Tidak Terbakar',
    low: 'Ringan',
    moderate: 'Sedang',
    high: 'Berat',
};

export const PRIORITY_LABELS = {
    low: 'Rendah',
    medium: 'Sedang',
    high: 'Tinggi',
};

export const ACTION_LABELS = {
    native_reforestation: 'Reboisasi',
    soil_restoration: 'Restorasi Tanah',
    slope_soil_conservation: 'Konservasi Tanah Lereng',
    slope_bioengineering: 'Bioengineering Lereng',
    crop_recommendation: 'Rekomendasi Tanaman',
    rainfall_management: 'Pengelolaan Air Hujan',
    vegetation_monitoring: 'Monitoring Vegetasi',
};

export const DIRECTION_LABELS = {
    improving: 'Membaik',
    stable: 'Stabil',
    declining: 'Menurun',
};

export const POTENTIAL_LABELS = {
    low: 'Rendah',
    medium: 'Sedang',
    high: 'Tinggi',
};

/** Parameter names the crop engine reports as limiting. */
export const PARAMETER_LABELS = {
    elevation: 'ketinggian',
    temperature: 'suhu',
    rainfall: 'curah hujan',
    soil_ph: 'pH tanah',
    soil_texture: 'tekstur tanah',
    slope: 'kemiringan',
    soil_moisture: 'kelembapan tanah',
    land_health: 'kondisi lahan',
};

export const CAPTURE_SOURCE_LABELS = {
    phone: 'Kamera ponsel',
    camera: 'Kamera',
    drone: 'Drone',
    other: 'Lainnya',
};

export const SOIL_TEXTURES = ['Clay', 'Silty Clay', 'Silty Clay Loam', 'Clay Loam', 'Silt Loam', 'Loam', 'Sandy Clay Loam', 'Sandy Loam', 'Loamy Sand', 'Sand', 'Silt', 'Sandy Clay'];

export const ROLES = [
    { id: 'farmer', label: 'Petani / Pengelola Lahan', description: 'Saya mengelola lahan dan mengunggah foto kondisi lahan.' },
    { id: 'institution', label: 'Pemerintah Daerah / NGO', description: 'Kami memantau program restorasi lintas lahan dan butuh laporan.' },
    { id: 'corporate', label: 'Korporasi (CSR/ESG)', description: 'Kami butuh bukti terverifikasi untuk laporan keberlanjutan.' },
];

/** Steps the recommendation engine suggests, in English, mapped to Indonesian. */
const STEP_LABELS = {
    'Native tree planting': 'Tanam pohon asli',
    'Assisted natural regeneration': 'Permudaan alami terbantu',
    'Vegetation corridor development': 'Bangun koridor vegetasi',
    Mulching: 'Mulsa permukaan tanah',
    'Ground cover planting': 'Tanam penutup tanah',
    'Erosion control': 'Kendalikan erosi',
    'Soil stabilization': 'Stabilkan tanah',
    'Contour terracing': 'Terastering mengikuti kontur',
    'Contour strip cropping': 'Tanam berjalur mengikuti kontur',
    'Cover crops between rows': 'Tanaman penutup di antara baris',
    'Silt pits and check dams': 'Lubang lumpur dan cek dam',
    'Vetiver grass hedgerows': 'Pagar hidup akar wangi',
    'Bamboo and Gliricidia planting': 'Tanam bambu dan gamal',
    'Live poles and brush layering': 'Tancap batang hidup dan lapis ranting',
    'Gabion or woven-bamboo check dams': 'Cek dam gabion atau anyaman bambu',
    'Store wet-season water for the dry months': 'Simpan air musim hujan untuk musim kemarau',
    'Plan planting so the growing period clears the dry season': 'Atur jadwal tanam agar masa tumbuh melewati kemarau',
    'Keep the soil covered between harvests': 'Jaga tanah tetap tertutup antar panen',
    'Direct runoff with contour drains': 'Arahkan aliran permukaan ke saluran kontur',
    'Cut drainage channels before planting': 'Buat saluran drainase sebelum menanam',
    'Plant on raised beds': 'Tanam di bedengan tinggi',
    'Use terrace or agroforestry rows instead of open-field cropping': 'Pakai teras atau baris agroforestri, bukan tanam terbuka',
    'Grow it as an agroforestry canopy with a ground crop': 'Tanam sebagai tajuk agroforestri bersama tanaman bawah',
    'Repeat drone survey': 'Ulangi pemotretan lahan',
    'Monitor vegetation recovery': 'Pantau pemulihan vegetasi',
    'Compare imagery over time': 'Bandingkan foto antar periode',
};

export function tCover(key, fallback = key) {
    return COVER_LABELS[key] ?? fallback;
}

export function tIssue(type, fallback = type) {
    return ISSUE_LABELS[type] ?? fallback;
}

export function tSeverity(level, fallback = level) {
    return SEVERITY_LABELS[level] ?? fallback;
}

export function tPriority(priority, fallback = priority) {
    return PRIORITY_LABELS[priority] ?? fallback;
}

export function tAction(action, fallback = action) {
    return ACTION_LABELS[action] ?? fallback;
}

export function tDirection(direction, fallback = direction) {
    return DIRECTION_LABELS[direction] ?? fallback;
}

export function tPotential(value, fallback = value) {
    return POTENTIAL_LABELS[value] ?? fallback;
}

export function tParameter(parameter, fallback = parameter) {
    return PARAMETER_LABELS[parameter] ?? fallback;
}

export function tCaptureSource(source, fallback = '—') {
    return CAPTURE_SOURCE_LABELS[source] ?? fallback;
}

/**
 * One suggested step, in Indonesian.
 *
 * Two steps carry a value the engine fills in — the crop to plant, and which
 * measured parameter is holding that crop back — so those are handled by shape
 * rather than by a literal lookup.
 */
export function tStep(step) {
    if (STEP_LABELS[step]) {
        return STEP_LABELS[step];
    }

    const plant = /^Plant (.+)$/.exec(step);
    if (plant) {
        return `Tanam ${plant[1]}`;
    }

    const limiting = /^Address the limiting factor: (.+)$/.exec(step);
    if (limiting) {
        return `Perbaiki faktor pembatas: ${tParameter(limiting[1].replace(/\s+/g, '_'))}`;
    }

    return step;
}
