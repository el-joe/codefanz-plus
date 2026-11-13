import { createServer as createHttpServer, type IncomingMessage, type Server, type ServerResponse } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import type { CodefanzApi } from './api.js';
import { createServer } from './server.js';

const MAX_BODY_BYTES = 1_000_000;

/**
 * Optional Streamable HTTP transport (stateless: a fresh MCP server per request). Callers must send
 * MCP_HTTP_AUTH_TOKEN as a Bearer token; the CodeFanz API token stays on this server. Binds to 127.0.0.1 unless
 * MCP_HTTP_HOST says otherwise — put TLS and a reverse proxy in front for remote use.
 */
export function startHttpServer(api: CodefanzApi, options: { host: string; port: number; authToken: string }): Server {
    const server = createHttpServer(async (req, res) => {
        try {
            if (req.url?.split('?')[0] !== '/mcp') {
                return send(res, 404, { error: 'not_found' });
            }

            if (!isAuthorized(req, options.authToken)) {
                return send(res, 401, { error: 'unauthorized' }, { 'WWW-Authenticate': 'Bearer' });
            }

            if (req.method !== 'POST') {
                return send(res, 405, { jsonrpc: '2.0', error: { code: -32000, message: 'Method not allowed.' }, id: null }, { Allow: 'POST' });
            }

            const body = await readJson(req);
            const mcp = createServer(api);
            const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined, enableJsonResponse: true });

            res.on('close', () => {
                void transport.close();
                void mcp.close();
            });

            await mcp.connect(transport);
            await transport.handleRequest(req, res, body);
        } catch {
            if (!res.headersSent) {
                send(res, 400, { jsonrpc: '2.0', error: { code: -32700, message: 'Invalid request.' }, id: null });
            }
        }
    });

    server.listen(options.port, options.host);

    return server;
}

export function isAuthorized(req: IncomingMessage, expected: string): boolean {
    const header = req.headers.authorization ?? '';
    const given = Buffer.from(header.startsWith('Bearer ') ? header.slice(7) : '');
    const wanted = Buffer.from(expected);

    return given.length === wanted.length && timingSafeEqual(given, wanted);
}

async function readJson(req: IncomingMessage): Promise<unknown> {
    const chunks: Buffer[] = [];
    let size = 0;

    for await (const chunk of req) {
        size += (chunk as Buffer).length;

        if (size > MAX_BODY_BYTES) {
            throw new Error('Body too large.');
        }

        chunks.push(chunk as Buffer);
    }

    return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

function send(res: ServerResponse, status: number, payload: unknown, headers: Record<string, string> = {}): void {
    res.writeHead(status, { 'Content-Type': 'application/json', ...headers });
    res.end(JSON.stringify(payload));
}
