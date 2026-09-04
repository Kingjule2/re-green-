/**
 * Thin client for the Drone Analysis JSON API (same-origin `/api/v1`).
 *
 * API routes are stateless (no session/CSRF), so plain `fetch` is enough.
 * Responses are wrapped in a top-level `data` key by Laravel API resources.
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
}

async function toJson(response) {
    const text = await response.text();
    let json = null;
    if (text) {
        try {
            json = JSON.parse(text);
        } catch {
            json = null;
        }
    }
    if (!response.ok) {
        throw new ApiError(response.status, json);
    }
    return json;
}

/**
 * Create a new analysis from a `FormData` payload (image + optional metadata).
 * Returns the created analysis resource ({ id, status, ... }).
 */
export async function createAnalysis(formData) {
    const response = await fetch(`${BASE}/drone-analyses`, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: formData,
    });
    return (await toJson(response)).data;
}

/** Fetch a single analysis by id (used for polling and detail views). */
export async function getAnalysis(id, { signal } = {}) {
    const response = await fetch(`${BASE}/drone-analyses/${id}`, {
        headers: { Accept: 'application/json' },
        signal,
    });
    return (await toJson(response)).data;
}

/** List recent analyses for the history view. */
export async function listAnalyses({ signal } = {}) {
    const response = await fetch(`${BASE}/drone-analyses`, {
        headers: { Accept: 'application/json' },
        signal,
    });
    return (await toJson(response)).data;
}

/** Delete an analysis (and its stored image) by id. */
export async function deleteAnalysis(id) {
    const response = await fetch(`${BASE}/drone-analyses/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
        throw new ApiError(response.status, null);
    }
}
