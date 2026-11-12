# CodeFanz MCP Server

> **Decision (2026-11-12):** standalone TypeScript server using the official MCP SDK, located in
> `mcp-server/`. It contains **no business logic and no database access**: every tool calls an authenticated
> Laravel `/api/v1/mcp/*` endpoint with a scoped Sanctum token, so Laravel's Actions, Policies and the
> approval engine remain the single source of truth (mandatory stack rule).

## 1. Transports
- **Streamable HTTP** at `https://codefanz.com/mcp` for remote use (Claude Desktop / Claude Code).
- **stdio** for local development: `node mcp-server/dist/index.js` (env: `CODEFANZ_API_URL`, `CODEFANZ_MCP_TOKEN`).

## 2. Authentication & scopes
- Admin issues an MCP client token in Admin → MCP Management (Sanctum token, expiry, revocable). OAuth 2.1 support can be added for remote connectors.
- Abilities: `mcp:read`, `mcp:draft`, `mcp:approve`, `mcp:execute`. Each tool declares its required ability; the token's owner must also pass the underlying Policy.
- Rate limit per token; every call logged to `mcp_tool_calls` (inputs redacted) and `audit_logs`.
- `mcp_writes` kill switch disables draft/approve/execute tools instantly.

## 3. Tools

| Group | Tools | Ability | Effect |
|---|---|---|---|
| Read | `list_pages`, `get_page`, `list_services`, `list_portfolio_projects`, `list_products`, `list_posts`, `get_post`, `get_seo_meta`, `get_analytics_summary`, `get_search_console_report`, `list_social_accounts`, `list_social_history`, `list_pending_approvals`, `get_lead_summary`, `get_project_summary`, `list_ai_drafts`, `get_site_health` | `mcp:read` | Read-only, permission-filtered, PII minimised |
| Draft | `create_post_draft`, `create_seo_content_draft`, `propose_service`, `propose_portfolio_project`, `create_social_drafts`, `suggest_page_changes`, `suggest_seo_meta`, `suggest_internal_links`, `create_seo_audit_report`, `propose_content_change` | `mcp:draft` | Creates `change_requests` / private drafts only |
| Approval | `get_approval`, `approve_change`, `reject_change`, `request_changes` | `mcp:approve` + human admin token owner with MFA | Audited; cannot approve own proposals |
| Execute | `apply_approved_post`, `publish_approved_social_post`, `apply_approved_seo_meta`, `apply_approved_service_update` | `mcp:execute` | Calls `ApplyApprovedChange` (verifies approval, validity, version, approver permission, scope) |

No tools exist for: SQL, shell, filesystem, arbitrary URL fetch, payments/refunds, users/roles, credentials.

## 4. Schemas
Every tool defines a JSON input schema (validated server-side) and structured output. Errors return
`isError` with a safe message; secrets are never included in responses.

## 5. Client configuration examples (to be finalised in M9)

Claude Code:
```bash
claude mcp add --transport http codefanz https://codefanz.com/mcp --header "Authorization: Bearer <token>"
```

Claude Desktop (`claude_desktop_config.json`, local stdio during development):
```json
{
  "mcpServers": {
    "codefanz": {
      "command": "node",
      "args": ["/var/www/codefanz-plus/mcp-server/dist/index.js"],
      "env": { "CODEFANZ_API_URL": "http://localhost:8000/api/v1", "CODEFANZ_MCP_TOKEN": "<token>" }
    }
  }
}
```

## 6. Tests
Scope enforcement per ability · revoked/expired token rejected · draft tools never write public tables ·
execute rejects stale/unapproved/insufficient approver · kill switch · rate limit · audit rows written.
