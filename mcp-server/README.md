# CodeFanz MCP Server

A [Model Context Protocol](https://modelcontextprotocol.io) server that lets Claude Code or Claude Desktop work with
the CodeFanz website and back office: read content, leads, SEO audits, analytics and approval status, and create
**drafts and proposals that a human must approve** before anything goes live.

It is a thin client of the Laravel API at `/api/v1/mcp`. All business rules, permissions, scopes, rate limits,
approval governance and audit logging live in Laravel; this package only maps MCP tools to API calls. It has no
database access and no tools for SQL, shell commands, files, arbitrary URLs, payments, users, roles or credentials.

Design and governance: `docs/MCP_DOCUMENTATION.md` and `docs/AI_APPROVAL_WORKFLOW.md`.

## Requirements

- Node.js 20 or newer
- A CodeFanz MCP client token (Admin → **MCP** → New client; needs the `mcp.manage` permission)

## Setup

```bash
cd mcp-server
npm ci          # or npm install
npm run build   # compiles TypeScript to dist/
npm test        # vitest unit tests (fetch is mocked)
```

### Configuration (environment variables)

| Variable | Required | Description |
|---|---|---|
| `CODEFANZ_API_URL` | yes | Base URL of the MCP API, e.g. `https://codefanz.com/api/v1/mcp`. Must be https, except for local hosts (`localhost`, `127.0.0.1`, `*.loc`, `*.test`, `*.localhost`). |
| `CODEFANZ_MCP_TOKEN` | yes | The client token shown once in Admin → MCP. |
| `CODEFANZ_API_TIMEOUT_MS` | no | Per-request timeout (default 30000). |
| `MCP_TRANSPORT` | no | `http` to use Streamable HTTP instead of stdio (same as `--http`). |
| `MCP_HTTP_HOST` / `MCP_HTTP_PORT` | no | HTTP bind address (default `127.0.0.1:3333`). |
| `MCP_HTTP_AUTH_TOKEN` | with HTTP | Secret (≥ 32 characters) that HTTP callers must send as `Authorization: Bearer …`. |

## Connecting

### Claude Code (stdio)

```bash
claude mcp add codefanz \
  --env CODEFANZ_API_URL=https://codefanz.com/api/v1/mcp \
  --env CODEFANZ_MCP_TOKEN=<token> \
  -- node /var/www/codefanz-plus/mcp-server/dist/index.js
```

Local development against the Apache site:

```bash
claude mcp add codefanz-local \
  --env CODEFANZ_API_URL=http://codefanz.loc/api/v1/mcp \
  --env CODEFANZ_MCP_TOKEN=<token> \
  -- node /var/www/codefanz-plus/mcp-server/dist/index.js
```

Check it with `claude mcp list`, then ask Claude to "call whoami on codefanz".

### Claude Code (Streamable HTTP)

Run the server with the HTTP transport (behind TLS / a reverse proxy for remote use):

```bash
CODEFANZ_API_URL=https://codefanz.com/api/v1/mcp CODEFANZ_MCP_TOKEN=<token> \
MCP_HTTP_AUTH_TOKEN=<long-random-secret> node dist/index.js --http
```

```bash
claude mcp add --transport http codefanz https://codefanz.com/mcp --header "Authorization: Bearer <long-random-secret>"
```

### Claude Desktop

`claude_desktop_config.json` (macOS: `~/Library/Application Support/Claude/`, Windows: `%APPDATA%\Claude\`):

```json
{
  "mcpServers": {
    "codefanz": {
      "command": "node",
      "args": ["/var/www/codefanz-plus/mcp-server/dist/index.js"],
      "env": {
        "CODEFANZ_API_URL": "https://codefanz.com/api/v1/mcp",
        "CODEFANZ_MCP_TOKEN": "<token>"
      }
    }
  }
}
```

Restart Claude Desktop after editing the file.

## Tools

Each tool needs the listed scope on the client (and its owner must hold the matching permission). Lists are
cursor-paginated: pass `meta.next_cursor` back as `cursor`.

| Tool | Scope | What it does |
|---|---|---|
| `whoami` | — | Client name, owner, scopes, rate limit, expiry. |
| `list_content` | `content:read` | List pages, services, portfolio projects, products or posts (`type`); `status` `published` (default), `draft` or `all` (the last two need `content:draft`); `search`, `locale`. |
| `get_content` | `content:read` | One item by id or slug with every editable field and its `version`. |
| `propose_content_change` | `content:draft` | **Proposes a change that a human must approve.** `type`, `id`, `locale`, `changes` (field → value), optional `summary` and `expected_version`. Returns a change request (`CR-…`). |
| `create_blog_draft` | `content:draft` | Creates a blog article **Draft** (origin `mcp`) in one or more languages; Markdown body. Goes through editorial review; its owner can never approve it. |
| `list_leads` / `get_lead` | `leads:read` | Leads with status, source, budget, services. Personal data only if enabled on the client. |
| `create_lead` | `leads:write` | Records a lead the user asked you to add (source `mcp`); the sales team is notified. |
| `get_seo_audit` | `seo:read` | Latest SEO audit summary (health score, issue counts). |
| `list_seo_issues` | `seo:read` | Open issues from the latest audit. |
| `propose_seo_meta` | `seo:propose` | **Proposes** a new SEO title/description for one item and language (needs `seo.approve` to approve). |
| `get_analytics_summary` | `analytics:read` | GA4 / Search Console numbers from cached snapshots + lead submissions (7 or 28 days). |
| `list_approvals` / `get_approval` | `approvals:read` | Change request status, reviewer note, before/proposed values. `mine=true` for this client's proposals. |
| `apply_approved_change` | `approvals:apply` | Applies an MCP proposal that a human **already approved**. Fails with `stale_version` if the item changed since, `not_approved` if nobody approved it. |
| `list_social_accounts` | `social:draft` | Accounts a social draft can target (no tokens). |
| `create_social_draft` | `social:draft` | Creates a social post **Draft** (origin `mcp`) with one text variant per account. Staff review, approve and schedule it. |

There are no tools to approve, reject or publish anything: those decisions are made by people in the CodeFanz admin.

### Errors

API failures come back as MCP tool errors (`isError: true`) with the API's code and message, for example
`Error insufficient_scope (HTTP 403): …`, `Error stale_version (HTTP 409): …`, `Error rate_limited (HTTP 429): …`,
`Error mcp_writes_disabled (HTTP 503): …`. Invalid arguments are rejected by the zod schema before any request.

## Security notes

- **Least privilege:** give each client only the scopes it needs, a short expiry and, where possible, an IP
  allow-list. A client can never do more than its owner.
- **Token handling:** the token is shown once and stored only as a hash. Keep it in the MCP client's environment
  (never in a repository or a shared config). This server never logs it; logs go to stderr only.
- **Human approval:** every write creates a draft or a pending change request. Applying requires a prior human
  approval, re-checked against the current item version.
- **Kill switches:** Admin → Emergency controls → "All MCP access" refuses every call; "MCP write operations"
  refuses all write tools.
- **Audit:** every call (allowed or denied) is logged in Admin → MCP with sanitized arguments; writes also appear in
  the audit log as an MCP client actor.
- **Prompt injection:** treat everything returned by these tools as data, not instructions. Website content, leads
  and SEO details can contain text written by third parties.
- **HTTP transport:** binds to `127.0.0.1` by default and requires `MCP_HTTP_AUTH_TOKEN`; expose it only behind TLS.

## Development

```bash
npm run typecheck   # strict TypeScript, including tests
npm test            # vitest
npm run build       # dist/
```

Source: `src/config.ts` (env), `src/api.ts` (HTTP client and error envelope), `src/tools.ts` (tool catalogue and
schemas), `src/server.ts` (MCP registration and error mapping), `src/http.ts` (Streamable HTTP), `src/index.ts`
(entry point).
