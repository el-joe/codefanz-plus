# AI & MCP Approval Workflow (Governance)

## 1. Rule

Any action originating from an AI agent, the AI Content Studio, automation, or MCP that **creates, modifies,
publishes, schedules or deletes public-facing website or social content** must pass through a
`change_request` and be approved by an authorized human before it takes effect. Enforcement is in the
backend; UI buttons are convenience only.

## 2. Scope

**Requires approval:** articles (create/edit/publish/schedule), services, portfolio projects, products,
page content and homepage sections, SEO metadata, redirects, navigation/menus, design settings, public
media uploads, social posts (create/edit/schedule/publish).

**No approval:** read-only operations; private draft generation (stored as `AiDraft`, not public).

**Separate authorization workflows (never available to AI):** refunds, money transfers, payment
credentials, user deletion, permission/role changes, security settings. AI/MCP tokens have no abilities
for these and the actions check `actor->isHuman()`.

## 3. States

```
Pending ──► UnderReview ──► Approved ──► Applied
   │             │             │
   │             ├──► Rejected └──► Failed (apply error / stale)
   │             └──► ChangesRequested ──► (new revision) ──► Pending
   └──► (expired / cancelled)
```

## 4. Flow

1. Proposer (AI/MCP/automation) calls `ProposeChange` action with target + proposed payload.
2. System snapshots `before`, records `target_version` (`lock_version`), computes diff, sets
   `required_permission` (e.g. `content.approve`, `seo.approve`, `social.approve`).
3. Notification to users holding that permission (database + mail/Telegram per preference).
4. Reviewer opens before/after preview (rendered with real Blade components in a sandboxed preview route).
5. Reviewer approves / rejects / requests changes with a note. Self-approval of one's own proposal is not allowed.
6. `ApplyApprovedChange` (the only write path for AI-originated public changes) runs in a DB transaction and verifies:
   1. request exists and status is `Approved`;
   2. not expired;
   3. target `lock_version` equals `target_version` (else → `Failed: stale`);
   4. approver still holds `required_permission`;
   5. action is within allowed scope for the origin;
   6. relevant kill switch is off.
7. Result recorded (`Applied` / `Failed` + error), `audit_logs` row written, cache invalidated.

## 4a. AI Content Studio (M8.1)

- Staff with `ai.use` generate from versioned prompt templates (`prompt_templates` + `prompt_template_versions`,
  managed under `ai.manage` together with the brand voice in `brand_profiles`). Every call goes through
  `AiGateway`, which checks the feature's kill switches (`ai_content_studio` / `ai_seo` / `ai_social` / `ai_radar`
  plus the `ai_automation` master; the public assistant only `ai_assistant`), the monthly budget (hard stop) and the
  per-user / per-feature caps in Settings → AI limits **before** calling the provider.
- The system prompt contains only admin-configured text (template, brand voice, glossary) and fixed safety rules.
  Staff input and existing content go into the user message inside `<user_input>` / `<current_content>` tags with
  `<` and `>` escaped, so they are data, never instructions.
- Each result is a private `ai_generations` row (template version, inputs, output, tokens, cost, author, target).
  Post-generation guardrails flag banned claims, numbers not found in the inputs, prices and links to unknown
  domains; flags are shown to the author and stored on the change request.
- What a generation can become: a new blog post (`origin = ai`, status Draft, editorial workflow; the author can
  never approve it) or, for an existing page/service/product/portfolio item/post, one pending change request per
  language with `origin = ai` and `ai_generation_id` — never a direct write. Social, email and ad copy stay private.
- Reviewers holding the required permission are notified (database + mail) when an AI/MCP proposal is created;
  the requester is notified of every decision.

## 4b. Approval queue UI (B04)

Filters (status tab, origin, content type, required permission), per-field before/after with a line diff, guardrail
flags, the checks re-verified at apply time (version, permission, not own proposal, kill switch, expiry), review note,
Reject / Request changes / Approve / Approve & apply, and a link to the generation record. Bulk approval skips
AI-origin requests with guardrail flags, the reviewer's own proposals and requests needing another permission.

## 5. Audit record (every operation)

actor identity (user / AI agent / MCP client) · operation · target resource · before state · proposed state ·
approver identity · execution result · timestamp · IP/user agent where applicable.

## 6. Emergency controls (Admin → Governance)

- Pause all AI automation (`ai_automation` kill switch)
- Disable social publishing (`social_publishing`)
- Disable MCP write operations (`mcp_writes`) — draft/execute tools return a clear error
- Revoke an individual integration or MCP token
- Cancel all scheduled content (bulk action, audited)
- View all pending change requests

Kill switches are checked inside actions and jobs (not only at the controller).

## 7. Tests (must exist)

Unapproved apply rejected · stale version rejected · approver without permission rejected · self-approval
rejected · kill switch blocks apply & publish jobs · scheduled post not published unless approved ·
AI token cannot call finance/permission endpoints · full audit trail written.
