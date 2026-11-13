#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CodefanzApi } from './api.js';
import { ConfigError, loadConfig } from './config.js';
import { startHttpServer } from './http.js';
import { createServer, SERVER_VERSION } from './server.js';

/**
 * Entry point. stdio by default (Claude Code / Claude Desktop launch it as a subprocess); `--http` or
 * MCP_TRANSPORT=http starts the Streamable HTTP transport instead. Logs go to stderr (stdout is the MCP channel)
 * and never contain the token.
 */
async function main(): Promise<void> {
    const config = loadConfig();
    const api = new CodefanzApi({ apiUrl: config.apiUrl, token: config.token, timeoutMs: config.timeoutMs, userAgent: `codefanz-mcp-server/${SERVER_VERSION}` });

    if (config.http.enabled) {
        startHttpServer(api, { host: config.http.host, port: config.http.port, authToken: config.http.authToken as string });
        console.error(`CodeFanz MCP server (Streamable HTTP) listening on http://${config.http.host}:${config.http.port}/mcp → ${config.apiUrl}`);

        return;
    }

    await createServer(api).connect(new StdioServerTransport());
    console.error(`CodeFanz MCP server (stdio) ready → ${config.apiUrl}`);
}

main().catch((error: unknown) => {
    console.error(error instanceof ConfigError ? `Configuration error: ${error.message}` : 'CodeFanz MCP server failed to start.');
    process.exit(1);
});
