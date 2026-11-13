import { describe, expect, it } from 'vitest';
import { ConfigError, loadConfig } from '../src/config.js';

const base = { CODEFANZ_API_URL: 'https://codefanz.com/api/v1/mcp/', CODEFANZ_MCP_TOKEN: '1|abc' };

describe('loadConfig', () => {
    it('reads the API URL and token from the environment', () => {
        const config = loadConfig(base, []);

        expect(config.apiUrl).toBe('https://codefanz.com/api/v1/mcp');
        expect(config.token).toBe('1|abc');
        expect(config.http.enabled).toBe(false);
    });

    it('requires both variables', () => {
        expect(() => loadConfig({ CODEFANZ_MCP_TOKEN: 'x' }, [])).toThrow(ConfigError);
        expect(() => loadConfig({ CODEFANZ_API_URL: base.CODEFANZ_API_URL }, [])).toThrow(/CODEFANZ_MCP_TOKEN/);
    });

    it('allows plain http only for local development hosts', () => {
        expect(() => loadConfig({ ...base, CODEFANZ_API_URL: 'http://codefanz.com/api/v1/mcp' }, [])).toThrow(/https/);
        expect(loadConfig({ ...base, CODEFANZ_API_URL: 'http://codefanz.loc/api/v1/mcp' }, []).apiUrl).toBe('http://codefanz.loc/api/v1/mcp');
        expect(loadConfig({ ...base, CODEFANZ_API_URL: 'http://localhost:8001/api/v1/mcp' }, []).apiUrl).toBe('http://localhost:8001/api/v1/mcp');
    });

    it('needs a strong inbound token for the HTTP transport', () => {
        expect(() => loadConfig(base, ['--http'])).toThrow(/MCP_HTTP_AUTH_TOKEN/);

        const config = loadConfig({ ...base, MCP_TRANSPORT: 'http', MCP_HTTP_AUTH_TOKEN: 'x'.repeat(40), MCP_HTTP_PORT: '4000' }, []);

        expect(config.http).toMatchObject({ enabled: true, host: '127.0.0.1', port: 4000 });
    });
});
