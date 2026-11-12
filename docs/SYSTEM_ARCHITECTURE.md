# System Architecture

> Stack is governed by `MANDATORY TECHNOLOGY STACK — STRICT REQUIREMENT.md`.

## 1. High-level view

```
                    ┌──────────────────────────── Laravel 13 (single source of truth) ───────────────────────────┐
 Browser (Blade +   │  HTTP: web routes (SSR)  ·  /api/v1 (Sanctum)  ·  /webhooks/*  ·  /mcp (MCP transport)       │
 Vanilla JS) ──────►│                                                                                             │
                    │  Controllers → Form Requests → Actions/Services → Models (Eloquent) → MySQL 8               │
 Claude (MCP) ─────►│        │                 │            │                                                     │
                    │     Policies        Approval Engine   Events → Listeners → Jobs (Redis queues) → Notifications│
 Gateways ─────────►│  (signed webhooks)        │                          │                                       │
 n8n ◄────────────► │                     Audit Log                 Scheduler (cron)                             │
                    └───────────────────────────────────────┬──────────────────────────────────────────────────────┘
                                                            │ adapters
          OpenAI · Stripe/Paymob/PayTabs/Fawry · GA4 · Search Console · Social APIs · Telegram · Platform connectors
```

## 2. Layering

| Layer | Location | Responsibility |
|---|---|---|
| Routes | `routes/web.php`, `routes/admin.php`, `routes/portal.php`, `routes/api.php`, `routes/webhooks.php`, `routes/ai.php` (MCP) | Thin, named routes, middleware groups |
| Controllers | `app/Http/Controllers/{Public,Admin,Portal,Api,Webhooks}` | Orchestrate request → action → response. No business logic. |
| Form Requests | `app/Http/Requests/...` | Validation + `authorize()` |
| Actions | `app/Actions/{Domain}/...` | One use-case per class (e.g. `CreatePaymentLink`, `ApplyApprovedChange`). Wrapped in DB transactions where critical. |
| Services / Adapters | `app/Services/{Payments,AI,Social,Analytics,Radar,Media}` | Contracts in `app/Contracts`, provider adapters behind interfaces |
| Models | `app/Models` | Relations, casts, scopes. Enums in `app/Enums` (TitleCase keys). |
| Policies | `app/Policies` | Every model with a backend check; permission-based |
| Events / Listeners / Jobs / Notifications | standard Laravel dirs | Async side-effects |
| Views | `resources/views/{layouts,components,pages,admin,customer}` | Blade only |
| JS | `resources/js/{modules,services,components}` | Progressive enhancement only, no business rules |

Modules are grouped by domain inside these standard Laravel directories (no new top-level base folders):
`Cms`, `Catalog` (services, technologies, portfolio, products), `Crm`, `Chatbot`, `Delivery` (projects,
milestones, documents, tickets), `Billing`, `Blog`, `Seo`, `Analytics`, `Marketing` (AI studio, social),
`Governance` (approvals, audit, kill switches), `Mcp`, `Radar`, `Media`, `Automation` (n8n), `Identity`.

## 3. Frontend architecture

- **Server-side rendering first**: every public page is a full Blade render with correct status codes,
  canonical, hreflang, structured data.
- **Tailwind v4** with design tokens mirrored from Figma variables into `@theme` in `resources/css/app.css`
  (colors, spacing, radius, shadows, typography). Logical properties (`ms-*`, `pe-*`, `start-*`) everywhere
  for automatic RTL.
- **Blade components** (`resources/views/components`): `button`, `input`, `select`, `textarea`, `card`,
  `badge`, `alert`, `modal`, `drawer`, `dropdown`, `table`, `pagination`, `empty-state`, `skeleton`,
  `admin.sidebar`, `admin.header`, `chart`, `filters`, `seo.meta`, `seo.schema`, `picture` (AVIF/WebP srcset).
- **Vanilla JS** ES modules, initialised by `data-module="..."` attributes:
  - `services/http.js` — fetch wrapper (CSRF header, JSON, 422 → field errors, 419/401 handling)
  - `modules/forms.js`, `modules/validation.js`, `modules/toast.js`, `modules/modal.js`, `modules/drawer.js`,
    `modules/dropdown.js`, `modules/data-table.js`, `modules/filters.js`, `modules/pagination.js`,
    `modules/uploader.js`, `modules/kanban.js`, `modules/assistant.js`, `modules/charts.js` (lazy Chart.js),
    `modules/partial-nav.js` (Fetch + History API for filters/pagination only).
- Separate Vite entry points: `app.js` (public), `admin.js`, `portal.js` — keeps public bundle minimal.

## 4. Localization

- Locale prefix in URL: `/en/...`, `/ar/...`; default locale redirect from `/`.
- `languages` table drives available locales; adding a language = data + lang files, no code change.
- Translatable content stored in `*_translations` tables (one row per locale) — indexed, queryable, SEO-friendly.
- `<html lang dir>` set from locale; Arabic font pairing chosen in design system.
- Slugs are per-locale; hreflang alternates generated from translation rows.

## 5. Identity & authorization

- Session auth for admin and portal (Laravel Fortify headless backend: login, password reset, email
  verification, TOTP MFA — required for privileged roles). Views are our own Blade pages.
- Sanctum personal access tokens for `/api/v1` and MCP clients, with abilities (`mcp:read`, `mcp:draft`,
  `mcp:approve`, `mcp:execute`).
- RBAC: roles × granular permissions (`spatie/laravel-permission`, pending approval). Users may hold many roles.
- Clients are users with the `client` role linked to one or more `customers` through `customer_user`
  (role owner/member, `revoked_at`). Public registration is off; staff invite portal users from the customer page
  (`ClientInvitation`, password-broker link in the user's language). Fortify's home `/dashboard` sends staff to
  `/admin` and everyone else to `/portal`; clients have no permissions, so `can:admin.access` returns 403.

### 5.1 Client portal isolation

- `/portal` routes run `web, auth, active, locale.preference, portal`. The `portal` middleware
  (`EnsurePortalAccess`) redirects staff to `/admin` and returns 403 unless the user has a non-revoked membership
  in an **active** customer.
- `App\Services\CurrentCustomer` (scoped per request) holds the customer the user is acting for. It is read
  from the session (`portal.customer`, a ulid) and **re-validated against the memberships on every request**;
  a stale or forged value falls back to the user's first accessible customer. The account switcher
  (`POST /portal/account`) only accepts customers from `User::portalCustomers()` (404 otherwise).
- Client-owned models (projects, invoices, documents, tickets…) carry `customer_id` and use the
  `App\Models\Concerns\BelongsToCustomer` trait. Portal code always queries through
  `Model::query()->forCurrentCustomer()` (explicit scope, not global, so admin screens still see everything).
- Portal policies use `App\Policies\Concerns\ChecksCustomerOwnership::ownedByCurrentCustomer($user, $record)`
  for single records (record belongs to the current customer **and** the membership is still active).
- Switching portal access off for a user's last customer deactivates the user and deletes their sessions/tokens;
  the `active` middleware signs them out on the next request. Isolation is covered by
  `tests/Feature/Portal/ClientPortalTest.php`.
- Delivery modules (M6.1) take plain ulid/uuid route parameters and resolve them in the controller with
  `forCurrentCustomer()` (milestones through their already-scoped project), so a record of another customer is
  always a **404**, never a 403 that would confirm it exists. Portal queries only ever select client-visible
  notes (`Project::clientUpdates()`), client-visible documents (`Document::clientVisible()`) and public ticket
  replies (`SupportTicket::publicReplies()`); internal records are never loaded into portal views or JSON.
  Covered by `tests/Feature/Portal/PortalDeliveryTest.php` and `tests/Feature/SupportTicketTest.php`.
- Conversations refresh by polling (`resources/js/modules/conversation.js`, every `codefanz.projects.poll_seconds`
  = 20 s, paused while the tab is hidden) — no websockets. Messages are plain text rendered with `textContent`.

## 6. Approval engine (Governance)

See `AI_APPROVAL_WORKFLOW.md`. Core idea: AI/MCP never writes public tables directly. It creates a
`change_request` holding target type/id, target version hash, before snapshot and proposed payload. An
`ApplyApprovedChange` action is the **only** code path that applies AI-originated public changes; it
re-verifies approval, approver permission, scope, and the target's version before writing in a transaction.

## 7. Integrations — adapter pattern

| Contract | Adapters (initial) |
|---|---|
| `PaymentGateway` | Stripe (M6.2); Paymob, PayTabs, Fawry (M6.3) |
| `AiProvider` | OpenAI (Responses API); future adapters |
| `SocialPublisher` | Facebook Page, Instagram, LinkedIn, X, TikTok, YouTube, Pinterest, `ManualExport` |
| `RadarConnector` | Upwork API, RSS feed, IMAP saved-search email, Manual import |
| `AnalyticsSource` | GA4 Data API, Search Console API |
| `Notifier` channels | mail, database, Telegram |

**Payments (M6.2).** `App\Contracts\PaymentGateway` (`createCheckout`, `verifyPayment`, `parseWebhook`,
`refund`, plus `isConfigured`/`mode`/`supportsCurrency`) is resolved by key through
`App\Services\Payments\PaymentGatewayManager` from `config('codefanz.payments.gateways')`. A new provider is a
driver class + config entry + its credential fields in `codefanz.settings.payments`; routes
(`/webhooks/payments/{gateway}`, outside the web group), `StartCheckout`, `HandleGatewayWebhook` and
`VerifyPayment` stay unchanged. Drivers never write the ledger: `VerifyPayment` is the only path to Paid.
Optional driver contracts: `TestsGatewayConnection` (Settings → Payments "Test connection", a harmless authenticated
call; the card shows "Connected (verified …)" until a credential changes) and `RetrievesGatewayRefunds` (refund
webhooks are confirmed through the API). Drivers throw `ManualRefundRequired` when a payment cannot be refunded by
API (cash at Fawry/Paymob kiosks). Each driver declares its currencies (PayTabs per region); payment links and
checkouts only offer gateways that support the link currency.

**Refunds (M6.3).** Four-eyes: `RequestRefund` (payments.create-links or finance; capped under a payment lock) →
`DecideRefund` by a `payments.refund-approve` holder who is not the requester → `ProcessRefund` calls the gateway →
`CompleteRefund` (only path to Refunded/PartiallyRefunded: `transitionTo` + payment_events, gap-free credit note
CN-YYYY-NNNNN, milestone recompute, audit, notifications), or `manual_required` → finance records the external
reference. Refund webhooks go through `SyncGatewayRefund` and are no-ops once the refund is settled.

Every adapter declares `capabilities()` (e.g. supports refunds, supports scheduling) so UI shows only real
options. Credentials live in `integration_credentials` encrypted with Laravel's encrypter (`encrypted` cast).

## 8. Async & scheduling

- Queues (Redis): `default`, `payments` (high priority), `media`, `ai`, `social`, `radar`, `notifications`.
- Scheduler: publish scheduled posts/articles (only if approved), expire payment links, radar collection,
  daily digest, weekly SEO report, broken-link crawl, token refresh, pruning.
- Idempotency: `idempotency_keys` + unique constraints on `(gateway, event_id)` webhooks, `(platform, external_id)`
  radar projects.

## 9. Media pipeline

Upload → validate (mime sniffing, size, dimensions, decompression-bomb guard) → store original on private
disk → queued `OptimizeImage` job (Imagick) → AVIF + WebP responsive variants (e.g. 320/640/960/1280/1920)
+ thumbnail → strip EXIF, keep ICC → verify output format → `media_variants` rows. SVG sanitised and served as-is;
animated GIF/WebP not converted.

## 10. MCP server

Standalone TypeScript service (`mcp-server/`, official MCP SDK; Streamable HTTP + stdio). It is a thin
client: each tool calls `/api/v1/mcp/*` with a scoped Sanctum token; Laravel enforces abilities, Policies,
rate limits, kill switches and the approval engine. Draft/write tools only create change requests. See `MCP_DOCUMENTATION.md`.

## 11. Observability

Structured logs per channel (`payments`, `webhooks`, `ai`, `mcp`, `radar`), `audit_logs` table for business
events, `/health` endpoint (DB, Redis, queue lag, disk), failed-job alerts via Telegram/mail.
