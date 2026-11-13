import { vi } from 'vitest';
import { CodefanzApi, type FetchLike } from '../src/api.js';

export const TOKEN = '12|supersecretplaintexttokenvalue0123456789';
export const API_URL = 'https://codefanz.test/api/v1/mcp';

export interface RecordedCall {
    url: URL;
    method: string;
    headers: Record<string, string>;
    body: unknown;
}

/**
 * A fetch mock that records every request and answers with the given status and JSON body.
 */
export function mockFetch(status = 200, payload: unknown = { data: { ok: true } }) {
    const calls: RecordedCall[] = [];
    const fetchMock = vi.fn<FetchLike>(async (input, init) => {
        calls.push({
            url: new URL(input),
            method: init?.method ?? 'GET',
            headers: (init?.headers ?? {}) as Record<string, string>,
            body: typeof init?.body === 'string' ? JSON.parse(init.body) : undefined,
        });

        return new Response(JSON.stringify(payload), { status, headers: { 'Content-Type': 'application/json' } });
    });

    return { calls, fetchMock, api: new CodefanzApi({ apiUrl: API_URL, token: TOKEN, fetch: fetchMock }) };
}
