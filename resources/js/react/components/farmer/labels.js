/**
 * Indonesian wording for the farmer module.
 *
 * `lib/i18n.js` already owns the vocabulary the engines share (issues, actions,
 * steps, severities, land cover). The dictionaries here cover the keys only the
 * farmer screens show — crop suitability, market value, input provenance,
 * terrain/soil/climate profile values — plus the wording for API failures, so
 * no English engine prose has to be printed to explain a value.
 *
 * Where the API already sends a translated label (severity, land status, carbon
 * eligibility, vegetation class, submission status) that label wins; these maps
 * are for the raw machine keys.
 */
import { ApiError } from '@/react/lib/api';

export const CLASSIFICATION_LABELS = {
    very_suitable: 'Sangat sesuai',
    suitable: 'Sesuai',
    moderately_suitable: 'Cukup sesuai',
    not_suitable: 'Tidak sesuai',
};

export const MARKET_VALUE_LABELS = {
    High: 'Tinggi',
    'Medium-High': 'Sedang–tinggi',
    Medium: 'Sedang',
    Low: 'Rendah',
};

/** How a scored parameter reached the engine. */
export const INPUT_SOURCE_LABELS = {
    sampled: 'Diukur dari dataset',
    measured: 'Diukur dari analisis foto',
    declared: 'Diisi petani',
    missing: 'Belum diketahui',
};

export const SUITABILITY_LABELS = {
    high: 'Tinggi',
    moderate: 'Sedang',
    low: 'Rendah',
    unsuitable: 'Tidak sesuai untuk tanaman semusim',
};

export const RISK_LABELS = {
    low: 'Rendah',
    moderate: 'Sedang',
    high: 'Tinggi',
    very_high: 'Sangat tinggi',
};

export const LIMITING_FACTOR_LABELS = {
    slope: 'Kemiringan lereng',
    erosion: 'Risiko erosi',
    elevation: 'Ketinggian tempat',
    soil_ph: 'pH tanah',
    soil_texture: 'Tekstur tanah',
    dry_season: 'Musim kemarau',
    drainage: 'Drainase',
};

export const SLOPE_CLASS_LABELS = {
    flat: 'Datar',
    gentle: 'Landai',
    sloping: 'Miring',
    steep: 'Curam',
    very_steep: 'Sangat curam',
};

export const ELEVATION_BAND_LABELS = {
    lowland: 'Dataran rendah',
    lower_montane: 'Pegunungan bawah',
    upper_montane: 'Pegunungan atas',
    highland: 'Dataran tinggi',
    alpine: 'Pegunungan tinggi',
};

export const ASPECT_LABELS = {
    flat: 'Datar',
    N: 'Utara',
    NE: 'Timur laut',
    E: 'Timur',
    SE: 'Tenggara',
    S: 'Selatan',
    SW: 'Barat daya',
    W: 'Barat',
    NW: 'Barat laut',
};

/** Detector classes reported by the YOLOv8 (or heuristic) burn model. */
export const DETECTOR_LABELS = {
    unburned_vegetation: 'Vegetasi tidak terbakar',
    vegetation_regrowth: 'Vegetasi tumbuh kembali',
    bare_soil: 'Tanah terbuka',
    charred_soil: 'Tanah terbakar',
    water: 'Air',
    built_area: 'Area terbangun',
};

/** How the fire severity of one photo was produced. */
export const ANALYSIS_METHOD_LABELS = {
    yolov8: 'Detektor YOLOv8',
    'colour-heuristic': 'Heuristik warna',
    'cover-derived': 'Turunan komposisi tutupan lahan',
};

/** The monitoring period label the API sends, e.g. `2025-10` → "Okt 2025". */
export function formatPeriodKey(period) {
    if (!period || period === 'unknown') {
        return '—';
    }

    return new Date(`${period}-01T00:00:00`).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
}

export function tClassification(key, fallback = '—') {
    return CLASSIFICATION_LABELS[key] ?? fallback;
}

export function tMarketValue(value, fallback = '—') {
    return MARKET_VALUE_LABELS[value] ?? fallback;
}

export function tInputSource(value, fallback = '—') {
    return INPUT_SOURCE_LABELS[value] ?? fallback;
}

export function tSuitability(value, fallback = '—') {
    return SUITABILITY_LABELS[value] ?? fallback;
}

export function tRisk(value, fallback = '—') {
    return RISK_LABELS[value] ?? fallback;
}

export function tLimitingFactor(key, fallback = key) {
    return LIMITING_FACTOR_LABELS[key] ?? fallback;
}

export function tSlopeClass(key, fallback = '—') {
    return SLOPE_CLASS_LABELS[key] ?? fallback;
}

export function tElevationBand(key, fallback = '—') {
    return ELEVATION_BAND_LABELS[key] ?? fallback;
}

export function tAspect(key, fallback = '—') {
    return ASPECT_LABELS[key] ?? fallback;
}

export function tMethod(value, fallback = '—') {
    return ANALYSIS_METHOD_LABELS[value] ?? fallback;
}

export function tDetector(label, fallback = label) {
    return DETECTOR_LABELS[label] ?? fallback;
}

/**
 * A short, Indonesian description of a failed API call.
 *
 * The server's own message is English framework prose, so it is never shown;
 * the status code carries the same information without leaking it.
 */
export function describeError(error) {
    if (!error) {
        return null;
    }

    if (error instanceof ApiError) {
        return {
            401: 'Sesi berakhir. Masuk kembali untuk melanjutkan.',
            403: 'Akun ini tidak berhak membuka data tersebut.',
            404: 'Data yang diminta tidak ditemukan.',
            422: 'Data yang dikirim belum lolos pemeriksaan server.',
            500: 'Server gagal memproses permintaan ini.',
        }[error.status] ?? `Permintaan gagal dengan kode ${error.status}.`;
    }

    return 'Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.';
}
