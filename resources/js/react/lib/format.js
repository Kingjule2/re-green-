/** Small, dependency-free formatting helpers for the Drone Analysis UI. */

/** Human-readable byte size, e.g. 8_400_000 -> "8.4 MB". */
export function formatBytes(bytes) {
    if (bytes == null || Number.isNaN(bytes)) {
        return '—';
    }
    if (bytes === 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
    const value = bytes / 1024 ** i;
    const decimals = value >= 10 || i === 0 ? 0 : 1;
    return `${value.toFixed(decimals)} ${units[i]}`;
}

/** Locale date, defaulting to "02 Sep 2026". */
export function formatDate(value, options) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleDateString('en-GB', options ?? { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Pixel resolution label, e.g. "5472 × 3648 px". */
export function formatResolution(width, height) {
    if (!width || !height) {
        return 'Unknown';
    }
    return `${width} × ${height} px`;
}

/** Turn snake_case / kebab-case identifiers into "Title Case". */
export function titleCase(value) {
    if (!value) {
        return '';
    }
    return String(value)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

/** Round a numeric percentage for display, tolerating null. */
export function formatPercent(value, decimals = 0) {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${Number(value).toFixed(decimals)}%`;
}
