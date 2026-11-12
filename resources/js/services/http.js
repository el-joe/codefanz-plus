/**
 * Fetch wrapper for Laravel endpoints: JSON, CSRF header, and normalised errors.
 * Business rules stay on the server; this only transports requests.
 */
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export class HttpError extends Error {
    constructor(status, payload) {
        super(payload?.message ?? `Request failed with status ${status}`);
        this.status = status;
        this.errors = payload?.errors ?? {};
    }
}

export async function request(url, { method = 'GET', body, headers = {} } = {}) {
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    });

    const payload = response.status === 204 ? null : await response.json().catch(() => null);

    if (response.status === 419) {
        window.location.reload();
    }

    if (!response.ok) {
        throw new HttpError(response.status, payload);
    }

    return payload;
}
