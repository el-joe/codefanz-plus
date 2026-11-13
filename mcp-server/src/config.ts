/**
 * Runtime configuration, read from the environment only. The token is never logged or echoed back.
 */
export interface Config {
    apiUrl: string;
    token: string;
    timeoutMs: number;
    http: {
        enabled: boolean;
        host: string;
        port: number;
        authToken: string | undefined;
    };
}

export class ConfigError extends Error {}

export function loadConfig(env: NodeJS.ProcessEnv = process.env, argv: string[] = process.argv.slice(2)): Config {
    const rawUrl = env.CODEFANZ_API_URL?.trim();
    const token = env.CODEFANZ_MCP_TOKEN?.trim();

    if (!rawUrl) {
        throw new ConfigError('CODEFANZ_API_URL is not set (for example https://codefanz.com/api/v1/mcp).');
    }

    if (!token) {
        throw new ConfigError('CODEFANZ_MCP_TOKEN is not set. Issue a token in Admin → MCP.');
    }

    let url: URL;

    try {
        url = new URL(rawUrl);
    } catch {
        throw new ConfigError('CODEFANZ_API_URL is not a valid URL.');
    }

    const isLocal = ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname) || url.hostname.endsWith('.loc') || url.hostname.endsWith('.test') || url.hostname.endsWith('.localhost');

    if (url.protocol !== 'https:' && !(url.protocol === 'http:' && isLocal)) {
        throw new ConfigError('CODEFANZ_API_URL must use https (plain http is only allowed for local development hosts).');
    }

    const httpEnabled = argv.includes('--http') || env.MCP_TRANSPORT === 'http';
    const port = Number.parseInt(env.MCP_HTTP_PORT ?? '3333', 10);

    if (!Number.isInteger(port) || port < 1 || port > 65535) {
        throw new ConfigError('MCP_HTTP_PORT must be a port number.');
    }

    const authToken = env.MCP_HTTP_AUTH_TOKEN?.trim() || undefined;

    if (httpEnabled && (!authToken || authToken.length < 32)) {
        throw new ConfigError('The HTTP transport needs MCP_HTTP_AUTH_TOKEN (at least 32 characters): callers must send it as a Bearer token.');
    }

    return {
        apiUrl: url.toString().replace(/\/+$/, ''),
        token,
        timeoutMs: Number.parseInt(env.CODEFANZ_API_TIMEOUT_MS ?? '30000', 10) || 30000,
        http: {
            enabled: httpEnabled,
            host: env.MCP_HTTP_HOST?.trim() || '127.0.0.1',
            port,
            authToken,
        },
    };
}
