/**
 * Minimal client for the CodeFanz Laravel MCP API (/api/v1/mcp). All business rules, scopes, rate limits, approval
 * governance and auditing live in Laravel; this only sends authenticated JSON requests and normalises errors.
 */
export type FetchLike = (input: string, init?: RequestInit) => Promise<Response>;

export type Query = Record<string, string | number | boolean | undefined | null>;

export class ApiError extends Error {
    constructor(
        public readonly code: string,
        message: string,
        public readonly status: number,
        public readonly details?: unknown,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

export interface ApiClientOptions {
    apiUrl: string;
    token: string;
    timeoutMs?: number;
    fetch?: FetchLike;
    userAgent?: string;
}

export class CodefanzApi {
    private readonly apiUrl: string;
    private readonly token: string;
    private readonly timeoutMs: number;
    private readonly fetchImpl: FetchLike;
    private readonly userAgent: string;

    constructor(options: ApiClientOptions) {
        this.apiUrl = options.apiUrl.replace(/\/+$/, '');
        this.token = options.token;
        this.timeoutMs = options.timeoutMs ?? 30000;
        this.fetchImpl = options.fetch ?? ((input, init) => fetch(input, init));
        this.userAgent = options.userAgent ?? 'codefanz-mcp-server/1.0';
    }

    get(path: string, query?: Query): Promise<unknown> {
        return this.request('GET', path, { query });
    }

    post(path: string, body?: unknown): Promise<unknown> {
        return this.request('POST', path, { body: body ?? {} });
    }

    async request(method: 'GET' | 'POST', path: string, options: { query?: Query; body?: unknown } = {}): Promise<unknown> {
        const url = new URL(this.apiUrl + path);

        for (const [key, value] of Object.entries(options.query ?? {})) {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
            }
        }

        let response: Response;

        try {
            response = await this.fetchImpl(url.toString(), {
                method,
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${this.token}`,
                    'User-Agent': this.userAgent,
                    ...(options.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
                },
                body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
                signal: AbortSignal.timeout(this.timeoutMs),
            });
        } catch (error) {
            const reason = error instanceof Error && error.name === 'TimeoutError' ? 'timed out' : 'could not be reached';

            throw new ApiError('network_error', `The CodeFanz API ${reason}.`, 0);
        }

        const text = await response.text();
        let payload: unknown = null;

        if (text !== '') {
            try {
                payload = JSON.parse(text);
            } catch {
                payload = null;
            }
        }

        if (!response.ok) {
            const error = isRecord(payload) && isRecord(payload.error) ? payload.error : undefined;

            throw new ApiError(
                typeof error?.code === 'string' ? error.code : `http_${response.status}`,
                typeof error?.message === 'string' ? error.message : `The CodeFanz API answered with HTTP ${response.status}.`,
                response.status,
                error?.details,
            );
        }

        return payload;
    }
}

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Encodes one path segment so tool input can never change the API path (no "../" or extra segments).
 */
export function segment(value: string | number): string {
    return encodeURIComponent(String(value));
}
