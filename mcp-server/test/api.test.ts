import { describe, expect, it, vi } from 'vitest';
import { ApiError, CodefanzApi, segment } from '../src/api.js';
import { API_URL, TOKEN, mockFetch } from './helpers.js';

describe('CodefanzApi', () => {
    it('sends the bearer token and JSON headers', async () => {
        const { api, calls } = mockFetch();

        await api.post('/leads', { name: 'Layla' });

        expect(calls[0]?.url.toString()).toBe(`${API_URL}/leads`);
        expect(calls[0]?.method).toBe('POST');
        expect(calls[0]?.headers.Authorization).toBe(`Bearer ${TOKEN}`);
        expect(calls[0]?.headers.Accept).toBe('application/json');
        expect(calls[0]?.headers['Content-Type']).toBe('application/json');
        expect(calls[0]?.body).toEqual({ name: 'Layla' });
    });

    it('serialises query parameters and skips empty ones', async () => {
        const { api, calls } = mockFetch();

        await api.get('/approvals', { mine: true, status: 'pending', cursor: undefined, search: '' });

        expect(calls[0]?.url.searchParams.get('mine')).toBe('1');
        expect(calls[0]?.url.searchParams.get('status')).toBe('pending');
        expect(calls[0]?.url.searchParams.has('cursor')).toBe(false);
        expect(calls[0]?.url.searchParams.has('search')).toBe(false);
    });

    it('turns the Laravel error envelope into an ApiError', async () => {
        const { api } = mockFetch(403, { error: { code: 'insufficient_scope', message: 'Needs leads:read.', details: { required_scope: 'leads:read' } } });

        const error = await api.get('/leads').catch((caught: unknown) => caught);

        expect(error).toBeInstanceOf(ApiError);
        expect(error).toMatchObject({ code: 'insufficient_scope', status: 403, details: { required_scope: 'leads:read' } });
    });

    it('falls back to the HTTP status when the body is not the envelope', async () => {
        const fetchMock = vi.fn(async () => new Response('<html>Bad gateway</html>', { status: 502 }));
        const api = new CodefanzApi({ apiUrl: API_URL, token: TOKEN, fetch: fetchMock });

        await expect(api.get('/me')).rejects.toMatchObject({ code: 'http_502', status: 502 });
    });

    it('never puts the token in network error messages', async () => {
        const fetchMock = vi.fn(async () => {
            throw new TypeError(`connect ECONNREFUSED with Bearer ${TOKEN}`);
        });
        const api = new CodefanzApi({ apiUrl: API_URL, token: TOKEN, fetch: fetchMock });

        const error = (await api.get('/me').catch((caught: unknown) => caught)) as ApiError;

        expect(error.code).toBe('network_error');
        expect(error.message).not.toContain(TOKEN);
    });

    it('encodes path segments so input cannot change the path', () => {
        expect(segment('../../admin')).toBe('..%2F..%2Fadmin');
        expect(segment('تطوير-الويب')).toBe(encodeURIComponent('تطوير-الويب'));
    });
});
