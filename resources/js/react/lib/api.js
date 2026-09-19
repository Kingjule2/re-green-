/**
 * Client for the re:green JSON API (same-origin `/api/v1`).
 *
 * The API authenticates with the session cookie the Blade page already has, so
 * requests must be same-origin and mutating requests must carry the CSRF token
 * that Laravel rendered into the page. Responses are unwrapped from the `data`
 * key Laravel API resources add, so callers see the resource itself.
 */

const BASE = '/api/v1';

/** Error thrown for non-2xx API responses, carrying validation details. */
export class ApiError extends Error {
    constructor(status, payload) {
        super(payload?.message || `Request failed with status ${status}`);
        this.name = 'ApiError';
        this.status = status;
        /** @type {Record<string, string[]> | null} */
        this.errors = payload?.errors ?? null;
        this.payload = payload ?? null;
    }

    /** First validation message for a field, if any. */
    fieldError(field) {
        return this.errors?.[field]?.[0] ?? null;
    }

    /** True when the request had no valid session (or the session expired). */
    get isUnauthenticated() {
        return this.status === 401;
    }

    /** True when the account is signed in but not allowed to do this. */
    get isForbidden() {
        return this.status === 403;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Unwrap a Laravel API-resource response.
 *
 * Resource collections send `{ data, meta }` (totals, scope, how many lands have
 * no coordinates). The callers want the rows themselves, so `meta` is attached
 * to the returned value as a non-enumerable property: an array stays an array
 * that spreads, maps and serialises normally, and a page that needs the totals
 * reads `result.meta`.
 */
function unwrap(payload) {
    if (payload === null || typeof payload !== 'object' || !('data' in payload)) {
        return payload;
    }

    const { data, meta } = payload;

    if (meta === undefined || data === null || typeof data !== 'object') {
        return data;
    }

    Object.defineProperty(data, 'meta', { value: meta, enumerable: false, configurable: true });

    return data;
}

async function request(method, path, options = {}) {
    const { body, signal, multipart = false } = options;

    const headers = { Accept: 'application/json' };
    if (!multipart && body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (method !== 'GET') {
        headers['X-CSRF-TOKEN'] = csrfToken();
    }

    const response = await fetch(`${BASE}${path}`, {
        method,
        headers,
        credentials: 'same-origin',
        signal,
        body: multipart ? body : body === undefined ? undefined : JSON.stringify(body),
    });

    if (response.status === 204) {
        return null;
    }

    const text = await response.text();
    let payload = null;
    if (text) {
        try {
            payload = JSON.parse(text);
        } catch {
            payload = null;
        }
    }

    if (!response.ok) {
        throw new ApiError(response.status, payload);
    }

    return unwrap(payload);
}

export const api = {
    get: (path, options) => request('GET', path, options),
    post: (path, body, options) => request('POST', path, { ...options, body }),
    patch: (path, body, options) => request('PATCH', path, { ...options, body }),
    delete: (path, options) => request('DELETE', path, options),
    /** Multipart POST (photo upload, asset upload). */
    postForm: (path, formData, options) => request('POST', path, { ...options, multipart: true, body: formData }),
};

export const auth = {
    me: (options) => api.get('/auth/me', options),
    login: (credentials) => api.post('/auth/login', credentials),
    register: (account) => api.post('/auth/register', account),
    logout: () => api.post('/auth/logout'),
};

export const lands = {
    list: (options) => api.get('/lands', options),
    get: (id, options) => api.get(`/lands/${id}`, options),
    create: (payload) => api.post('/lands', payload),
    update: (id, payload) => api.patch(`/lands/${id}`, payload),
    remove: (id) => api.delete(`/lands/${id}`),
    progress: (id, options) => api.get(`/lands/${id}/progress`, options),
    selectCrop: (id, cropId) => api.post(`/lands/${id}/crop-selection`, { crop_id: cropId }),
};

export const analyses = {
    list: (landId, options) => api.get(`/lands/${landId}/analyses`, options),
    /** @param {FormData} formData image + captured_at/notes/capture_source */
    upload: (landId, formData) => api.postForm(`/lands/${landId}/analyses`, formData),
    get: (id, options) => api.get(`/analyses/${id}`, options),
    remove: (id) => api.delete(`/analyses/${id}`),
};

export const carbon = {
    show: (landId, options) => api.get(`/lands/${landId}/carbon`, options),
    submit: (landId, payload) => api.post(`/lands/${landId}/carbon/submit`, payload),
    portfolio: (options) => api.get('/carbon/portfolio', options),
};

export const reports = {
    list: (options) => api.get('/reports', options),
    get: (id, options) => api.get(`/reports/${id}`, options),
    create: (payload) => api.post('/reports', payload),
    remove: (id) => api.delete(`/reports/${id}`),
    /** Direct download links (the browser handles the response headers). */
    pdfUrl: (id) => `${BASE}/reports/${id}/pdf`,
    csvUrl: (id) => `${BASE}/reports/${id}/csv`,
};

export const overview = {
    dashboard: (options) => api.get('/dashboard', options),
    map: (options) => api.get('/map/lands', options),
    publicSummary: (options) => api.get('/public/summary', options),
};

/** Polling interval for an analysis that is still queued or running. */
export const ANALYSIS_POLL_MS = 1500;
