import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { tools } from '../src/tools.js';
import { API_URL, mockFetch } from './helpers.js';

function tool(name: string) {
    const found = tools.find((candidate) => candidate.name === name);

    if (!found) {
        throw new Error(`Unknown tool ${name}`);
    }

    return found;
}

function parse(name: string, input: unknown) {
    return z.object(tool(name).inputSchema).safeParse(input);
}

describe('tool → API mapping', () => {
    it.each([
        ['whoami', {}, 'GET', '/me'],
        ['list_content', { type: 'service', status: 'published', locale: 'ar' }, 'GET', '/content/service?status=published&locale=ar'],
        ['get_content', { type: 'post', key: 'my-slug' }, 'GET', '/content/post/my-slug'],
        ['propose_content_change', { type: 'page', id: 4, locale: 'en', changes: { title: 'New' } }, 'POST', '/content/page/4/proposals'],
        ['create_blog_draft', { translations: { en: { title: 'T', body: 'B' } } }, 'POST', '/posts/drafts'],
        ['list_leads', { status: 'new' }, 'GET', '/leads?status=new'],
        ['get_lead', { reference: 'LD-2026-00042' }, 'GET', '/leads/LD-2026-00042'],
        ['create_lead', { name: 'Layla', email: 'layla@example.test', preferred_locale: 'ar' }, 'POST', '/leads'],
        ['get_seo_audit', {}, 'GET', '/seo/audit'],
        ['list_seo_issues', { severity: 'error' }, 'GET', '/seo/issues?severity=error'],
        ['propose_seo_meta', { type: 'service', id: 1, locale: 'en', seo_title: 'Title' }, 'POST', '/seo/proposals'],
        ['get_analytics_summary', { days: 7 }, 'GET', '/analytics/summary?days=7'],
        ['list_approvals', { mine: true }, 'GET', '/approvals?mine=1'],
        ['get_approval', { reference: 'CR-2026-0042' }, 'GET', '/approvals/CR-2026-0042'],
        ['apply_approved_change', { reference: 'CR-2026-0042' }, 'POST', '/approvals/CR-2026-0042/apply'],
        ['list_social_accounts', {}, 'GET', '/social/accounts'],
        ['create_social_draft', { title: 'Launch', locale: 'en', variants: [{ account_id: 3, text: 'Hello' }] }, 'POST', '/social/drafts'],
    ])('%s calls %s %s', async (name, input, method, path) => {
        const { api, calls } = mockFetch();
        const parsed = parse(name, input);

        expect(parsed.success).toBe(true);

        await tool(name).run(api, parsed.data as never);

        expect(calls).toHaveLength(1);
        expect(calls[0]?.method).toBe(method);
        expect(calls[0]?.url.toString()).toBe(API_URL + path);
    });

    it('sends the proposal body without the path parameters', async () => {
        const { api, calls } = mockFetch();

        await tool('propose_content_change').run(api, { type: 'service', id: 9, locale: 'en', changes: { summary: 'S' }, expected_version: 3 } as never);

        expect(calls[0]?.body).toEqual({ locale: 'en', changes: { summary: 'S' }, expected_version: 3 });
    });
});

describe('input schemas', () => {
    it('reject invalid input before calling the API', () => {
        expect(parse('list_content', { type: 'users' }).success).toBe(false);
        expect(parse('get_approval', { reference: '../../admin' }).success).toBe(false);
        expect(parse('get_lead', { reference: 'CR-2026-0001' }).success).toBe(false);
        expect(parse('propose_content_change', { type: 'page', id: 1, locale: 'english', changes: { title: 'x' } }).success).toBe(false);
        expect(parse('propose_content_change', { type: 'page', id: 1, locale: 'en', changes: {} }).success).toBe(false);
        expect(parse('propose_seo_meta', { type: 'page', id: 1, locale: 'en', seo_title: 'x'.repeat(71) }).success).toBe(false);
        expect(parse('create_lead', { name: 'A', email: 'not-an-email', preferred_locale: 'en' }).success).toBe(false);
        expect(parse('create_social_draft', { title: 'T', locale: 'en', variants: [] }).success).toBe(false);
        expect(parse('create_social_draft', { title: 'T', locale: 'en', link_url: 'http://insecure.test', variants: [{ account_id: 1, text: 'x' }] }).success).toBe(false);
        expect(parse('get_analytics_summary', { days: 30 }).success).toBe(false);
    });

    it('propose_seo_meta needs at least one field', async () => {
        const { api, calls } = mockFetch();

        await expect(tool('propose_seo_meta').run(api, { type: 'page', id: 1, locale: 'en' } as never)).rejects.toThrow(/seo_title/);
        expect(calls).toHaveLength(0);
    });
});

describe('tool catalogue', () => {
    it('has unique names and offers no dangerous tools', () => {
        const names = tools.map((candidate) => candidate.name);

        expect(new Set(names).size).toBe(names.length);
        expect(names.some((name) => /sql|shell|exec|file|fetch_url|payment|refund|user|role|credential|approve_change|reject/.test(name))).toBe(false);
    });

    it('says that proposals and drafts need human approval', () => {
        for (const name of ['propose_content_change', 'propose_seo_meta']) {
            expect(tool(name).description).toContain('proposes a change that a human must approve');
        }

        for (const name of ['create_blog_draft', 'create_social_draft']) {
            expect(tool(name).description).toContain('a human must review and approve');
        }
    });

    it('marks only read tools as read-only', () => {
        for (const candidate of tools) {
            expect(candidate.annotations.readOnlyHint).toBe(candidate.name.startsWith('list_') || candidate.name.startsWith('get_') || candidate.name === 'whoami');
        }
    });
});
