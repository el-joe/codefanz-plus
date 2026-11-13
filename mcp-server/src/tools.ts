import { z } from 'zod';
import { type CodefanzApi, segment } from './api.js';

/**
 * Every MCP tool maps to exactly one Laravel endpoint. Input schemas mirror the API validation so mistakes are
 * caught before a call; Laravel validates again and is the source of truth. There are deliberately no tools for
 * SQL, shell, files, arbitrary URLs, payments, refunds, users, roles, credentials or approving changes.
 */
export interface ToolAnnotations {
    readOnlyHint: boolean;
    destructiveHint: boolean;
    idempotentHint: boolean;
    openWorldHint: boolean;
}

export interface ToolDefinition<Shape extends z.ZodRawShape = z.ZodRawShape> {
    name: string;
    title: string;
    description: string;
    scope: string | null;
    inputSchema: Shape;
    annotations: ToolAnnotations;
    run: (api: CodefanzApi, input: z.infer<z.ZodObject<Shape>>) => Promise<unknown>;
}

const APPROVAL_NOTE = 'This proposes a change that a human must approve in the CodeFanz approval queue; nothing on the live website changes until then.';
const DRAFT_NOTE = 'This creates a private draft that a human must review and approve before anything is published.';

const read: ToolAnnotations = { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false };
const write: ToolAnnotations = { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false };

export const contentTypes = ['page', 'service', 'portfolio_project', 'product', 'post'] as const;

const contentType = z.enum(contentTypes).describe('Content type: page, service, portfolio_project, product or post.');
const locale = z.string().regex(/^[a-z]{2}$/, 'Use a two-letter language code such as "en" or "ar".').describe('Language code, e.g. "en" or "ar".');
const id = z.number().int().positive();
const perPage = z.number().int().min(1).max(50).optional().describe('Items per page (1–50, default 20).');
const cursor = z.string().max(500).optional().describe('The meta.next_cursor value from the previous page.');
const changeReference = z.string().regex(/^CR-\d{4}-\d+$/, 'Change request references look like CR-2026-0042.').describe('Change request reference, e.g. CR-2026-0042.');
const leadReference = z.string().regex(/^LD-\d{4}-\d+$/, 'Lead references look like LD-2026-00042.').describe('Lead reference, e.g. LD-2026-00042.');
const expectedVersion = z.number().int().positive().optional().describe('The "version" you read with get_content; the proposal is refused if the item changed since.');

function define<Shape extends z.ZodRawShape>(tool: ToolDefinition<Shape>): ToolDefinition {
    return tool as unknown as ToolDefinition;
}

export const tools: ToolDefinition[] = [
    define({
        name: 'whoami',
        title: 'Who am I',
        description: 'Show this MCP client\'s name, owner, scopes, rate limit and expiry. Use it to check the connection and which tools are allowed.',
        scope: null,
        inputSchema: {},
        annotations: read,
        run: (api) => api.get('/me'),
    }),
    define({
        name: 'list_content',
        title: 'List website content',
        description: 'List website pages, services, portfolio projects, software products or blog posts with their title, slug and summary per language. Published items only, unless the client also has content:draft and status is "draft" or "all". Paginated with cursors.',
        scope: 'content:read',
        inputSchema: {
            type: contentType,
            status: z.enum(['published', 'draft', 'all']).optional().describe('Default "published". "draft" and "all" need the content:draft scope.'),
            search: z.string().max(100).optional().describe('Search in titles.'),
            locale: locale.optional(),
            per_page: perPage,
            cursor,
        },
        annotations: read,
        run: (api, { type, ...query }) => api.get(`/content/${segment(type)}`, query),
    }),
    define({
        name: 'get_content',
        title: 'Read one content item',
        description: 'Read one page, service, portfolio project, product or blog post by numeric id or slug, with every field a change may modify and its current version (needed for proposals).',
        scope: 'content:read',
        inputSchema: {
            type: contentType,
            key: z.string().min(1).max(200).describe('Numeric id or URL slug.'),
            locale: locale.optional().describe('Only return this language (also used to resolve a slug).'),
        },
        annotations: read,
        run: (api, { type, key, locale: language }) => api.get(`/content/${segment(type)}/${segment(key)}`, { locale: language }),
    }),
    define({
        name: 'propose_content_change',
        title: 'Propose a content change',
        description: `Propose new text for fields of one existing page, service, portfolio project, product or blog post in one language (fields listed as editable_fields by get_content; blog post bodies are Markdown). ${APPROVAL_NOTE} Returns the change request reference to follow with get_approval.`,
        scope: 'content:draft',
        inputSchema: {
            type: contentType,
            id,
            locale,
            changes: z.record(z.string().regex(/^[a-z_]+$/), z.string().max(50000).nullable()).refine((value) => Object.keys(value).length >= 1 && Object.keys(value).length <= 10, 'Propose between 1 and 10 fields.').describe('Field name → new value, e.g. {"title": "…", "summary": "…"}.'),
            summary: z.string().max(255).optional().describe('One line for the reviewer explaining why.'),
            expected_version: expectedVersion,
        },
        annotations: write,
        run: (api, { type, id: itemId, ...body }) => api.post(`/content/${segment(type)}/${segment(itemId)}/proposals`, body),
    }),
    define({
        name: 'create_blog_draft',
        title: 'Create a blog article draft',
        description: `Create a new blog article as a Draft (origin "mcp") in one or more languages. The body is Markdown. ${DRAFT_NOTE} The client's owner can never approve it themselves.`,
        scope: 'content:draft',
        inputSchema: {
            translations: z.record(z.string().regex(/^[a-z]{2}$/), z.object({
                title: z.string().min(1).max(160),
                body: z.string().min(1).max(50000).describe('Markdown.'),
                excerpt: z.string().max(500).optional(),
                seo_title: z.string().max(70).optional(),
                seo_description: z.string().max(170).optional(),
                focus_keyword: z.string().max(100).optional(),
            })).refine((value) => Object.keys(value).length >= 1, 'Add at least one language.').describe('Language code → article fields, e.g. {"en": {...}, "ar": {...}}.'),
            post_category_id: id.optional(),
        },
        annotations: write,
        run: (api, body) => api.post('/posts/drafts', body),
    }),
    define({
        name: 'list_leads',
        title: 'List leads',
        description: 'List CRM leads (newest first) with status, source, budget band and services. Names, emails, phone numbers and free text are only included if an administrator enabled lead personal data for this client.',
        scope: 'leads:read',
        inputSchema: {
            status: z.enum(['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost']).optional(),
            source: z.enum(['quote_form', 'contact_form', 'ai_assistant', 'manual', 'mcp', 'radar']).optional(),
            per_page: perPage,
            cursor,
        },
        annotations: read,
        run: (api, query) => api.get('/leads', query),
    }),
    define({
        name: 'get_lead',
        title: 'Read one lead',
        description: 'Read one lead by reference (personal data minimised unless enabled for this client).',
        scope: 'leads:read',
        inputSchema: { reference: leadReference },
        annotations: read,
        run: (api, { reference }) => api.get(`/leads/${segment(reference)}`),
    }),
    define({
        name: 'create_lead',
        title: 'Create a lead',
        description: 'Record a new sales lead (source "mcp") that the user explicitly asked you to add. The sales team is notified; no email is sent to the person. Only use details the user gave you.',
        scope: 'leads:write',
        inputSchema: {
            name: z.string().min(1).max(120),
            email: z.email().max(255),
            phone: z.string().regex(/^\+?[0-9 ()-]{6,30}$/).optional(),
            company: z.string().max(160).optional(),
            country: z.string().regex(/^[A-Za-z]{2}$/).optional().describe('ISO country code, e.g. EG.'),
            preferred_locale: locale,
            description: z.string().max(5000).optional(),
            budget_range: z.enum(['under_5k', '5k_10k', '10k_25k', '25k_50k', '50k_100k', 'over_100k', 'not_sure']).optional(),
            timeline: z.enum(['asap', '1_3_months', '3_6_months', '6_plus_months', 'flexible']).optional(),
            priority: z.enum(['low', 'normal', 'high']).optional(),
            services: z.array(id).max(10).optional().describe('Service ids from list_content type=service.'),
        },
        annotations: write,
        run: (api, body) => api.post('/leads', body),
    }),
    define({
        name: 'get_seo_audit',
        title: 'SEO audit summary',
        description: 'Summary of the latest completed SEO audit: health score, pages crawled and issue counts by severity and type. Nothing is crawled on demand.',
        scope: 'seo:read',
        inputSchema: {},
        annotations: read,
        run: (api) => api.get('/seo/audit'),
    }),
    define({
        name: 'list_seo_issues',
        title: 'List SEO issues',
        description: 'Open issues from the latest SEO audit, with the affected URL and content item.',
        scope: 'seo:read',
        inputSchema: {
            severity: z.enum(['error', 'warning', 'notice']).optional(),
            type: z.string().max(60).optional(),
            locale: locale.optional(),
            per_page: perPage,
            cursor,
        },
        annotations: read,
        run: (api, query) => api.get('/seo/issues', query),
    }),
    define({
        name: 'propose_seo_meta',
        title: 'Propose SEO metadata',
        description: `Propose a new SEO title (max 70 characters) and/or meta description (max 170) for one content item in one language. ${APPROVAL_NOTE}`,
        scope: 'seo:propose',
        inputSchema: {
            type: contentType,
            id,
            locale,
            seo_title: z.string().min(1).max(70).optional(),
            seo_description: z.string().min(1).max(170).optional(),
            summary: z.string().max(255).optional(),
            expected_version: expectedVersion,
        },
        annotations: write,
        run: async (api, body) => {
            if (body.seo_title === undefined && body.seo_description === undefined) {
                throw new InputError('Give seo_title, seo_description or both.');
            }

            return api.post('/seo/proposals', body);
        },
    }),
    define({
        name: 'get_analytics_summary',
        title: 'Analytics summary',
        description: 'Website analytics from cached GA4 and Search Console snapshots plus lead submissions for the last 7 or 28 days. Sources that are not configured say so; numbers are never estimated.',
        scope: 'analytics:read',
        inputSchema: { days: z.union([z.literal(7), z.literal(28)]).optional().describe('7 or 28 (default 28).') },
        annotations: read,
        run: (api, query) => api.get('/analytics/summary', query),
    }),
    define({
        name: 'list_approvals',
        title: 'List approvals',
        description: 'List change requests and their status (pending, approved, rejected, changes_requested, applied, failed). Use mine=true for the proposals this client made.',
        scope: 'approvals:read',
        inputSchema: {
            status: z.enum(['pending', 'under_review', 'approved', 'rejected', 'changes_requested', 'applied', 'failed']).optional(),
            origin: z.enum(['human', 'ai', 'mcp']).optional(),
            mine: z.boolean().optional(),
            per_page: perPage,
            cursor,
        },
        annotations: read,
        run: (api, query) => api.get('/approvals', query),
    }),
    define({
        name: 'get_approval',
        title: 'Read one approval',
        description: 'Read one change request: status, reviewer note, before and proposed values, and any apply error.',
        scope: 'approvals:read',
        inputSchema: { reference: changeReference },
        annotations: read,
        run: (api, { reference }) => api.get(`/approvals/${segment(reference)}`),
    }),
    define({
        name: 'apply_approved_change',
        title: 'Apply an approved change',
        description: 'Apply a change request that a human reviewer has ALREADY approved and that was proposed through MCP. The server re-checks the approval, expiry, the item version (stale approvals fail), the approver\'s permission and the kill switches. It can never approve anything itself.',
        scope: 'approvals:apply',
        inputSchema: { reference: changeReference },
        annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false },
        run: (api, { reference }) => api.post(`/approvals/${segment(reference)}/apply`),
    }),
    define({
        name: 'list_social_accounts',
        title: 'List social accounts',
        description: 'Social media accounts a draft can target (id, platform, name, status). No tokens are ever returned.',
        scope: 'social:draft',
        inputSchema: {},
        annotations: read,
        run: (api) => api.get('/social/accounts'),
    }),
    define({
        name: 'create_social_draft',
        title: 'Create a social post draft',
        description: `Create a social media post draft (origin "mcp") with one text variant per account from list_social_accounts. Platform limits are enforced by the server. ${DRAFT_NOTE} Images are added by staff in the composer.`,
        scope: 'social:draft',
        inputSchema: {
            title: z.string().min(1).max(150).describe('Internal title.'),
            locale,
            campaign: z.string().max(80).optional(),
            link_url: z.url({ protocol: /^https$/ }).max(500).optional(),
            variants: z.array(z.object({
                account_id: id,
                text: z.string().min(1).max(63206),
                hashtags: z.string().max(1000).optional(),
                first_comment: z.string().max(1000).optional(),
            })).min(1).max(10),
        },
        annotations: write,
        run: (api, body) => api.post('/social/drafts', body),
    }),
];

export class InputError extends Error {}
