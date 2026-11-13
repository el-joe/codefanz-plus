import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import type { IncomingMessage } from 'node:http';
import { describe, expect, it } from 'vitest';
import { ApiError } from '../src/api.js';
import { isAuthorized } from '../src/http.js';
import { createServer, toolError } from '../src/server.js';
import { tools } from '../src/tools.js';
import { TOKEN, mockFetch } from './helpers.js';

async function connect(status = 200, payload: unknown = { data: { name: 'claude-code' } }) {
    const { api, calls } = mockFetch(status, payload);
    const server = createServer(api);
    const client = new Client({ name: 'test-client', version: '1.0.0' });
    const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair();

    await Promise.all([server.connect(serverTransport), client.connect(clientTransport)]);

    return { client, calls };
}

function text(result: Awaited<ReturnType<Client['callTool']>>): string {
    const [first] = result.content as Array<{ type: string; text: string }>;

    return first?.text ?? '';
}

describe('MCP server', () => {
    it('lists every tool with its input schema and annotations', async () => {
        const { client } = await connect();
        const listed = await client.listTools();

        expect(listed.tools.map((tool) => tool.name).sort()).toEqual(tools.map((tool) => tool.name).sort());

        const propose = listed.tools.find((tool) => tool.name === 'propose_content_change');

        expect(propose?.inputSchema.required).toEqual(expect.arrayContaining(['type', 'id', 'locale', 'changes']));
        expect(propose?.description).toContain('content:draft');
        expect(propose?.annotations?.readOnlyHint).toBe(false);
    });

    it('returns the API response as JSON text', async () => {
        const { client, calls } = await connect();
        const result = await client.callTool({ name: 'whoami', arguments: {} });

        expect(result.isError).toBeFalsy();
        expect(JSON.parse(text(result))).toEqual({ data: { name: 'claude-code' } });
        expect(calls).toHaveLength(1);
    });

    it('maps API errors to MCP tool errors without leaking the token', async () => {
        const { client } = await connect(409, { error: { code: 'stale_version', message: 'The target changed since the proposal.' } });
        const result = await client.callTool({ name: 'apply_approved_change', arguments: { reference: 'CR-2026-0001' } });

        expect(result.isError).toBe(true);
        expect(text(result)).toContain('stale_version');
        expect(text(result)).toContain('HTTP 409');
        expect(text(result)).not.toContain(TOKEN);
    });

    it('rejects invalid arguments without calling the API', async () => {
        const { client, calls } = await connect();
        const result = await client.callTool({ name: 'get_approval', arguments: { reference: 'DROP TABLE' } });

        expect(result.isError).toBe(true);
        expect(calls).toHaveLength(0);
    });

    it('hides unexpected errors behind a generic message', () => {
        const result = toolError(new Error(`boom ${TOKEN}`));

        expect(JSON.stringify(result)).not.toContain(TOKEN);
        expect(result.isError).toBe(true);
        expect(JSON.stringify(toolError(new ApiError('rate_limited', 'Too many requests.', 429)))).toContain('Wait before calling again.');
    });
});

describe('HTTP transport auth', () => {
    const request = (authorization?: string) => ({ headers: authorization ? { authorization } : {} }) as IncomingMessage;

    it('accepts only the configured bearer token', () => {
        const secret = 'x'.repeat(40);

        expect(isAuthorized(request(`Bearer ${secret}`), secret)).toBe(true);
        expect(isAuthorized(request('Bearer wrong'), secret)).toBe(false);
        expect(isAuthorized(request(), secret)).toBe(false);
    });
});
