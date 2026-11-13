# CodeFanz MCP Server

> **Decision (2026-11-12):** standalone TypeScript server using the official MCP SDK, located in
> `mcp-server/`. It contains **no business logic and no database access**: every tool calls an authenticated
> Laravel `/api/v1/mcp/*` endpoint with a scoped Sanctum token, so Laravel's Actions, Policies and the
> approval engine remain the single source of truth (mandatory stack rule).
>
> **Implemented in M9 (2026-11-13).** Setup, every tool and client configuration examples: `mcp-server/README.md`.

## 1. Architecture

```
Claude Code / Claude Desktop ──stdio or Streamable HTTP──► mcp-server (TypeScript, @modelcontextprotocol/sdk + zod)
        ──HTTPS + Bearer token──► Laravel /api/v1/mcp/* (auth:sanctum → mcp:<scope> middleware → controller → Actions)
```

- **TypeScript side** (`mcp-server/src`): `config.ts` (env), `api.ts` (fetch client, error envelope → `ApiError`,
  path segments URL-encoded), `tools.ts` (one zod-validated tool per endpoint), `server.ts` (registers tools,
  maps errors to `isError` tool results), `http.ts` (optional Streamable HTTP), `index.ts` (entry). Runtime
  dependencies: `@modelcontextprotocol/sdk` and `zod` only. Not part of the Laravel Vite build.
- **Laravel side**: `routes/api.php` (prefix `api/v1/mcp`, route name `api.mcp.<tool>` = tool name),
  `App\Http\Middleware\AuthorizeMcpCall` (alias `mcp`), controllers in `App\Http\Controllers\Api\V1\Mcp`,
  resources in `App\Http\Resources\Mcp`, form requests in `App\Http\Requests\Api\Mcp`,
  `App\Services\Mcp\{McpErrors, McpArgumentSanitizer, McpContentTypes}`.

## 2. Transports
- **stdio** (default): Claude Code / Claude Desktop start `node mcp-server/dist/index.js` with
  `CODEFANZ_API_URL` (e.g. `https://codefanz.com/api/v1/mcp`, plain http only for local hosts) and `CODEFANZ_MCP_TOKEN`.
- **Streamable HTTP** (optional, `--http` or `MCP_TRANSPORT=http`): stateless, `POST /mcp`, binds to
  `127.0.0.1:3333` by default (`MCP_HTTP_HOST`, `MCP_HTTP_PORT`). Callers must send `MCP_HTTP_AUTH_TOKEN`
  (≥ 32 chars) as a Bearer token; the CodeFanz token never leaves the server. Put TLS + a reverse proxy in front
  (e.g. `https://codefanz.com/mcp`) for remote use. OAuth 2.1 for remote connectors is a possible later addition.

## 3. Clients, tokens & scopes
- Admin → **MCP** (B08, permission `mcp.manage`, Super Admin by default) manages `mcp_clients`: name, owner
  (a staff user), status (active/disabled), scopes, optional IP/CIDR allow-list, rate limit per minute (1–600),
  "include lead personal data" flag, optional expiry. Every change is audited (`mcp_client.*`).
- One Sanctum personal access token per client (`tokenable` = the client, morph alias `mcp_client`): shown **once**
  after create/rotate with a copy button, stored as SHA-256 only, abilities = scopes, `expires_at` = client expiry.
  Rotate (old token dies immediately) and revoke are audited without the token value. Editing scopes/expiry
  updates the existing token. Deleting a client revokes its token.
- **Scopes** (`App\Enums\McpScope`), each also requiring a permission on the owner, re-checked on every call:

| Scope | Owner permission | Write |
|---|---|---|
| `content:read` | `admin.access` | |
| `content:draft` | `content.manage` | ✔ |
| `leads:read` | `leads.view` | |
| `leads:write` | `leads.manage` | ✔ |
| `seo:read`, `analytics:read` | `seo.manage` | |
| `seo:propose` | `seo.manage` | ✔ |
| `approvals:read` | `admin.access` | |
| `approvals:apply` | `admin.access` + the change request's `required_permission` | ✔ |
| `social:draft` | `social.manage` | ✔ |

  The admin form refuses scopes the owner does not hold. No scope exists for finance, users, roles, settings,
  credentials or security.

## 4. Request pipeline (`auth:sanctum` → `mcp:<scope>`)
1. `mcp_access` kill switch active → 503 `mcp_disabled` (all calls, including reads).
2. Client expired → 401 `client_expired`; disabled → 403 `client_disabled` (Sanctum already rejects expired/revoked tokens with 401).
3. IP not in the allow-list → 403 `ip_not_allowed`.
4. Per-client rate limit (`RateLimiter`, key `mcp-client:{id}`) → 429 `rate_limited` + `Retry-After`.
5. Token lacks the scope → 403 `insufficient_scope` (`details.required_scope`).
6. Owner inactive or lost the scope's permission → 403 `owner_not_permitted`.
7. Write scope and `mcp_writes` kill switch active → 503 `mcp_writes_disabled`.
8. Every call — allowed or denied — is written to `mcp_tool_calls`: client, tool, method, **sanitized**
   arguments (keys like token/password/secret/api_key/authorization redacted; email/phone always redacted;
   name/company/free text redacted for lead tools; emails/bearer tokens inside text masked; strings ≤ 200 chars),
   result (`ok`/`denied`/`invalid`/`conflict`/`rate_limited`/`error`), HTTP status, error code, latency (ms),
   linked `change_request_id`, IP. `McpToolCall` is `Prunable` (`codefanz.mcp.tool_call_retention_days`, default 90,
   via the daily `model:prune`). Write actions additionally write `audit_logs` rows with actor type `mcp_client`.

**Error envelope** (all `/api/v1/mcp` failures): `{"error": {"code", "message", "details"?}}` — `unauthenticated`
401, `validation_failed` 422 (`details.fields`), `not_found` 404, `forbidden`/scope codes 403, governance codes 409
(`stale_version`, `not_approved`, `expired`, `kill_switch_active`, `missing_permission`, `disallowed_attributes`;
`details.retryable`), `rate_limited` 429, `server_error` 500. **Lists** use cursor pagination:
`{"data": [...], "meta": {"per_page", "next_cursor", "prev_cursor"}}` (`per_page` ≤ 50).

## 5. Tools

| Tool | Endpoint | Scope | Effect |
|---|---|---|---|
| `whoami` | `GET me` | — | Client name, owner, scopes, limits (never the token) |
| `list_content` | `GET content/{type}` | content:read | Pages, services, portfolio projects, products, posts; published only unless the client also has content:draft (`status=draft|all`); `search`, `locale` |
| `get_content` | `GET content/{type}/{id-or-slug}` | content:read | Every approvable field per language + `version` |
| `propose_content_change` | `POST content/{type}/{id}/proposals` | content:draft | `ProposeChange` (origin `mcp`) → pending change request; allowed fields = the target's approvable attributes; Markdown → sanitized HTML for post bodies; unchanged values dropped; `expected_version` → 409 if stale; new language needs a slug |
| `create_blog_draft` | `POST posts/drafts` | content:draft | New post, status Draft, origin `mcp`, author = owner → normal editorial workflow (author can never approve) |
| `list_leads` / `get_lead` | `GET leads`, `GET leads/{reference}` | leads:read | PII (name, email, phone, company, description) only when the client's "include lead personal data" flag is on |
| `create_lead` | `POST leads` | leads:write | `CreateLead` source `mcp`, creator = owner, `LeadCreated` event + team notifications, no auto-reply |
| `get_seo_audit` / `list_seo_issues` | `GET seo/audit`, `GET seo/issues` | seo:read | Latest completed audit only; nothing crawled on demand |
| `propose_seo_meta` | `POST seo/proposals` | seo:propose | `ProposeChange` with `seo_title`/`seo_description` → pending, needs `seo.approve` |
| `get_analytics_summary` | `GET analytics/summary?days=7|28` | analytics:read | `AnalyticsDashboard` over cached snapshots + CRM leads; honest `state` when not configured |
| `list_approvals` / `get_approval` | `GET approvals`, `GET approvals/{reference}` | approvals:read | All change requests if the owner may review approvals, otherwise only this client's (`mine=1` always filters to this client) |
| `apply_approved_change` | `POST approvals/{reference}/apply` | approvals:apply | Only MCP-origin requests already **approved by a human**; owner needs the request's `required_permission`; runs `ApplyApprovedChange` (status, expiry, `lock_version`, approver permission, `mcp_writes` kill switch) → stale → 409 `stale_version` and the request is marked Failed |
| `list_social_accounts` | `GET social/accounts` | social:draft | id, platform, name, status — never tokens |
| `create_social_draft` | `POST social/drafts` | social:draft | `SaveSocialPost` as Draft, origin `mcp`, `generated_by` = owner (so the owner can never approve/schedule it); composer platform limits enforced; no media |

**Deviations from the original design (deliberate):** no approve / reject / request-changes tools — humans decide
only in the admin approval queue (B04); the `mcp:read/draft/approve/execute` abilities became the finer scopes above;
change requests created through MCP carry `requested_by` = the client owner, so the owner cannot approve their own
client's proposals (four-eyes). Search Console / health / social history / AI draft reads are covered by
`get_analytics_summary`, `get_seo_audit` and `list_approvals`; dedicated tools can be added as thin endpoints later.
No tools exist for SQL, shell, filesystem, arbitrary URL fetch, payments/refunds, users/roles, settings or credentials.

## 6. Kill switches
- `mcp_access` — **All MCP access**: every call refused (new in M9).
- `mcp_writes` — draft, propose, lead, social and apply tools refused; reads keep working. `ProposeChange` and
  `ApplyApprovedChange` also enforce it for origin `mcp`.
Both are on Admin → Emergency controls and on the B08 screen (toggles need `automation.manage`).

## 7. Client configuration
See `mcp-server/README.md` for full details. Claude Code (stdio):
```bash
claude mcp add codefanz \
  --env CODEFANZ_API_URL=https://codefanz.com/api/v1/mcp \
  --env CODEFANZ_MCP_TOKEN=<token> \
  -- node /var/www/codefanz-plus/mcp-server/dist/index.js
```
Claude Desktop (`claude_desktop_config.json`):
```json
{
  "mcpServers": {
    "codefanz": {
      "command": "node",
      "args": ["/var/www/codefanz-plus/mcp-server/dist/index.js"],
      "env": { "CODEFANZ_API_URL": "http://codefanz.loc/api/v1/mcp", "CODEFANZ_MCP_TOKEN": "<token>" }
    }
  }
}
```

## 8. Tests
- Pest `tests/Feature/McpApiTest.php`: token auth, 403 per endpoint without its scope, disabled / expired /
  IP-blocked clients, expired/revoked tokens, owner without permission, `mcp_access` and `mcp_writes` kill switches,
  429 rate limit, published vs draft visibility, cursor pagination, every propose tool creates a pending `mcp`
  change request and leaves the target untouched, stale/unknown-field/unchanged proposals, blog draft origin,
  lead source + event + notifications, PII minimisation, social draft, apply refuses unapproved/stale/non-MCP and
  works for approved, sanitized tool-call log, pruning.
- Pest `tests/Feature/Admin/McpManagementTest.php`: B08 access (`mcp.manage`), EN/AR rendering, log filters,
  token shown once and hashed, scope/owner/IP validation, token sync, rotate/revoke/delete with audit.
- Vitest `mcp-server/test/*` (`cd mcp-server && npm test`): API client (headers, query, envelope, no token leaks,
  path encoding), config, every tool → endpoint mapping, schema validation, catalogue safety, MCP server via the
  SDK's in-memory transport, HTTP bearer check.
