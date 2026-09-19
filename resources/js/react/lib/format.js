/** Small, dependency-free formatting helpers. */

/** Human-readable byte size, e.g. 8_400_000 -> "8,4 MB". */
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
    return `${value.toFixed(decimals).replace('.', ',')} ${units[i]}`;
}

/** Locale date, e.g. "02 Sep 2026". */
export function formatDate(value, options) {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleDateString('id-ID', options ?? { day: '2-digit', month: 'short', year: 'numeric' });
}

/** Date and time, e.g. "02 Sep 2026 14:05". */
export function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return `${formatDate(value)} ${new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
}

/** Month label for a monitoring period, e.g. "Sep 2025". */
export function formatPeriod(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
}

/** Pixel resolution label, e.g. "5472 × 3648 px". */
export function formatResolution(width, height) {
    if (!width || !height) {
        return 'Ukuran tidak diketahui';
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
    return `${Number(value).toFixed(decimals).replace('.', ',')}%`;
}

/** Number with Indonesian separators. */
export function formatNumber(value, decimals = 0) {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    return Number(value).toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

/** Hectares with two decimals, e.g. "4,50 ha". */
export function formatHectares(value) {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${formatNumber(value, 2)} ha`;
}

/** A signed change, e.g. "+7,5 pp" for percentage points. */
export function formatDelta(value, unit = ' pp') {
    if (value == null || Number.isNaN(Number(value))) {
        return '—';
    }
    const number = Number(value);
    const sign = number > 0 ? '+' : '';

    return `${sign}${formatNumber(number, 1)}${unit}`;
}

/** "12 hari lalu" from a timestamp. */
export function formatRelativeDays(value) {
    if (!value) {
        return '—';
    }
    const days = Math.round((Date.now() - new Date(value).getTime()) / 86_400_000);

    if (days <= 0) {
        return 'hari ini';
    }
    if (days === 1) {
        return 'kemarin';
    }

    return `${days} hari lalu`;
}
