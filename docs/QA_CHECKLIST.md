# QA Checklist

Manual and browser-driven checks for Milestone 11 (testing & optimization), kept up to date for the final QA in
Milestone 12. Automated coverage lives in the Pest suite; this file records what was exercised in a real browser.

**Environment (M11.2, 2026-11-13):** local dev server `http://localhost:8001` (`php artisan serve`), MySQL dev data
(`DemoContentSeeder`), queue worker `queue:listen` in the developer's terminal, `MAIL_MAILER=log`, no payment or AI
credentials configured. Sign-in accounts from `LocalDevAccountsSeeder` (see DEPLOYMENT_GUIDE §11). Browsers: the
Claude Code browser pane (Chromium) for public flows, and headless Google Chrome driven over the DevTools protocol for
signed-in flows (real login form + TOTP 2FA challenge, credentials read from `.env` and never printed) and file uploads.

## 1. Critical flows (end to end)

| # | Flow | Lang | Steps verified | Result |
|---|------|------|----------------|--------|
| 1 | Request a Quote with attachment | EN, AR | Filled every required field, attached a 1-page PDF, waited past the time trap, submitted → success alert (`role=status`) with reference LD-2026-00009 / 00010; lead stored with source `quote_form`, the right `preferred_locale`, 1 private attachment and a staff notification | ✅ Pass |
| 2 | Contact form | EN | Typed fields, ticked consent with Space, submitted with Enter → "Thank you — your message is on its way." (status message); lead LD-2026-00011 created | ✅ Pass |
| 3 | AI Assistant fallback questionnaire → lead | AR | No AI provider configured → guided questionnaire (project, platforms, features, timeline, budget, contact) → confirm → LD-2026-00012 (source `ai_assistant`) | ✅ Pass |
| 4 | Admin login + 2FA | EN | `/login` → `/two-factor-challenge` → TOTP code → `/admin` (Super Admin and Content Manager) | ✅ Pass |
| 5 | Lead appears with notification | EN | New lead listed on `/admin/notifications` and in the header bell | ✅ Pass |
| 6 | Kanban move (keyboard path) | EN | Card's "Move LD-… to" select + Move button (the keyboard / no-JS alternative to dragging) → optimistic move to Won, toast "Moved LD-2026-00012 to Won." announced in the live region | ✅ Pass |
| 7 | Convert to customer | EN | Won lead → "Convert to customer" (prefilled form) → customer page | ✅ Pass |
| 8 | Invite client (mail log) | EN → AR mail | Portal-user invitation sent; the invitation e-mail with the set-password link is in `storage/logs/laravel.log` | ✅ Pass |
| 9 | Client sets password via link | AR | Invitation link → reset form → new password accepted → `/login` | ✅ Pass |
| 10 | Portal login → project and document | AR, 390 px | Client signs in → `/portal` shows "QA E2E project" (created in admin for the new customer); `/portal/documents` lists the client-visible "QA brief" PDF uploaded in admin | ✅ Pass |
| 11 | Payment link page, no gateway credentials | AR | `/pay/{ulid}` shows amount, reference, project, masked payer and "Online payment is not available right now … nothing was charged" — no fake gateway buttons | ✅ Pass |
| 12 | Blog draft → review → publish | EN | Content Manager: new article (title, slug, excerpt, body typed into TipTap), Save draft → Submit for review → Approve with "Publish now" → "Article published." → public `/en/blog/qa-e2e-article-…` renders the h1 | ✅ Pass |
| 13 | SEO meta edit | EN | Admin → SEO → Meta: About (EN) title/description edited → public `/en/about` `<title>` updated | ✅ Pass |
| 14 | Media upload → optimized variants | EN | PNG 1600×900 uploaded in Media → queue worker marked it `ready` within ~1 s with AVIF + WebP at 320/640/960/1280/1600 | ✅ Pass |
| 15 | Language switch keeps the page | EN ↔ AR | Service detail and blog article: «عربي» → translated slug (`/ar/services/تطوير-المواقع`, `/ar/blog/تجريبي-…`), `lang`/`dir` switch; English link returns to the English slug | ✅ Pass |
| 16 | Light/dark toggle (admin only) | EN | "Dark theme" toggle sets `.dark` + `aria-pressed="true"`, persists to the next admin page; the public site stays light in the same browser | ✅ Pass |

Defect found while running the flows: the Content Manager (an approver) is sent to 2FA setup since M11.1, so the
local QA account now gets confirmed 2FA too (seeder + test). No application defects were found in these flows.

## 2. Accessibility (WCAG 2.2 AA)

Tools: axe-core 4.10.2 (tags wcag2a/aa, wcag21a/aa, wcag22aa, best-practice) injected over the DevTools protocol on
the local server only (not a project dependency; the CSP blocks third-party scripts), plus keyboard and reflow checks.

| Scope | Pages | Result after fixes |
|-------|-------|--------------------|
| Public, EN + AR | every template (home, services index/detail, technologies, industries, portfolio index/detail, products index/detail, blog index/article/category, careers index/detail, FAQ, testimonials, about, contact, quote, assistant, search, 404) | 0 axe violations |
| Auth | login, forgot password, register (404), 2FA challenge (driven) | 0 violations |
| Admin, light + dark | dashboard, approvals, leads table/board/detail/create, pages/services/posts lists and editors (TipTap), media, settings, users, customers, projects, SEO, SEO meta, profile, notifications, invoices, payment links, tickets, radar, social calendar, analytics | 0 violations |
| Portal, light + dark | dashboard, projects, project, documents, conversations + thread, support list/new/ticket, invoices, payments, notifications, profile, security, pay page | 0 violations |
| Reflow 320 px | all of the above | no page-level horizontal scroll (Kanban board and data tables scroll inside their own region, allowed by 1.4.10) |
| Keyboard | skip link first; Services menu opens on focus, ArrowDown/Escape, focus returns to the trigger; mobile drawer: `aria-expanded`, focus moves in, Escape closes and returns focus; assistant launcher reachable and named; cookie banner is a labelled non-modal region with two buttons; Kanban Move form; contact form submit with Enter | ✅ |
| Contrast | every semantic text/background token pair computed in light and dark (`resources/css/app.css`) | all text ≥ 4.5:1, form-control borders and focus ring ≥ 3:1 |

Fixes by criterion:

- **1.3.1 Info and relationships** — assistant conversation is a `role="log"` wrapper around a real list; quick replies are a labelled `role="group"`; blog callouts are `role="note"` instead of nested complementary landmarks; in-page side columns in admin/portal are plain containers; admin/portal sidebars are labelled.
- **1.4.3 Contrast (minimum)** — `--cf-text-tertiary` light #6a7389 → #5d667b (was 4.09:1 on muted), dark #959db1 → #b0b7c8; dark `--cf-text-brand` #a89bff → #bdb3ff; dark brand hover and danger buttons darkened so white labels stay ≥ 4.5:1; message timestamps on brand bubbles no longer faded; pagination dark text; quote stepper "done" badge (white on teal 1.8:1 → ink 900).
- **1.4.10 Reflow** — responsive grids get a shrinkable base column (`grid-cols-1` / `minmax(0,1fr)`), service tiles and contact channels no longer overflow at 320 px; toast region width; media upload input.
- **1.4.11 Non-text contrast** — `--cf-border-strong` light #c2c8d6 → #858ea3, dark #4d556a → #737c92, and every text input, select and textarea now uses it (was 1.3:1).
- **2.4.11 Focus not obscured** — `scroll-padding-top: 6rem` under the sticky headers; `scroll-padding-bottom` while the cookie banner is open.
- **2.5.8 Target size** — technology chips' links are at least 24 px tall.
- **4.1.2 Name, role, value** — theme toggles expose `aria-pressed` and are named "Dark theme"; disabled pagination arrows have a role for their label; logo SVG gradient ids are unique per render (the header and footer repeated `cf-mark-40`).
- **4.1.3 Status messages** — verified: toasts in `aria-live="polite"`, success alerts `role=status`, errors `role=alert`.
- **2.3.3 / reduced motion** — `scroll-behavior: auto` added to the existing reduced-motion rules.

Regression tests: `tests/Feature/AccessibilityStructureTest.php` (every public route type EN + AR: `lang`/`dir`, one
`main`, skip link first, one h1, no skipped heading levels, labels, `aria-describedby` targets, link/button names, img
alt, unique ids, live toast region; form errors; auth autocomplete; admin/portal landmarks and theme toggle).

## 3. SEO validation

| Check | Result |
|-------|--------|
| Unique title + description per route type and language | ✅ after fix: blog category and tag archives reused the blog index description (and tags reused the bare tag name as title) |
| Self canonical, `og:url`, `og:locale` | ✅ |
| hreflang en/ar/x-default, reciprocal | ✅ |
| Robots meta: indexable pages carry no `noindex`; search with a query is `noindex, follow` | ✅ |
| JSON-LD parses on every route type (Organization, WebSite, Breadcrumb, Service, Product, Article …) | ✅ |
| Sitemap index + children well-formed; every listed URL answers 200 and is indexable | ✅ |
| robots.txt: local/staging block everything, production allows and announces the sitemap, setting overrides both ways | ✅ |
| 404 for unknown pages/slugs/locales; changed slugs 301 (one hop); `/` → 302 language negotiation (deliberately not 301) | ✅ |

Tests: `tests/Feature/SeoValidationTest.php` (plus existing `TechnicalSeoTest`, `SeoMetaTest`).

## 4. Open items

- ~~Trailing-slash URLs (`/en/`) answer 200 on `php artisan serve`~~ — closed in M12: Nginx rewrite + the
  `RedirectTrailingSlash` middleware (301, query kept, tested in `tests/Feature/Operations/TrailingSlashRedirectTest.php`).
- The cookie banner was reviewed in markup only: it appears only once a GA4 measurement ID is configured.
- Screen-reader passes (NVDA/VoiceOver) were not run; checks above are automated + keyboard.

## 5. Go-live checklist (Milestone 12)

Run top to bottom on the production server; tick each line and note who/when. Commands are from
`{{APP_PATH}}/current` as the app user unless stated. Details and file locations: `DEPLOYMENT_GUIDE.md`.
🔑 = needs an owner credential, account or third-party approval.

### 5.1 Infrastructure
- [ ] DNS: `A`/`AAAA` for the canonical host and the alternate (`www`) host point to the server; low TTL during cutover.
- [ ] TLS: Certbot certificate covers both hosts; `nginx -t` clean; `http://` → `https://{canonical}` and
      `https://www.` → canonical (301, one hop); SSL Labs grade A; HSTS header present on HTML and `/build/*`.
- [ ] Rendered configs installed (Nginx http/site/tls, FPM pool + ini, Supervisor, cron, logrotate); `render.sh` reported
      no missing placeholders; `php -r 'var_dump(Imagick::queryFormats("AVIF"));'` lists AVIF.
- [ ] Firewall: only 22/80/443 open; MySQL/Redis bound to 127.0.0.1.
- [ ] `/en/` → 301 `/en`; `/.env`, `/.git/config`, `/storage/app/…` and `/storage/anything-but-media` → 404.

### 5.2 Application
- [ ] `shared/.env` from `.env.production.example`, every `CHANGE` filled, `chmod 640`; `APP_KEY` stored in the owner's
      password manager.
- [ ] First deploy with `deploy.sh deploy <tag> --approve-migrations --skip-backup --allow-check-failures`; later deploys
      without the last two flags.
- [ ] Migrations applied (`php artisan migrate:status` shows none pending); `php artisan db:seed --force` ran (idempotent
      system data: languages, roles/permissions, kill switches, AI studio, radar platforms, page sections, menus).
- [ ] **Admin bootstrap:** `php artisan app:create-super-admin` (password at the hidden prompt only); first sign-in
      completes **2FA setup**; recovery codes stored offline. Super Admin, Administrator and Finance Manager accounts
      all have 2FA; staff invited from Admin → Users (no shared accounts).
- [ ] Kill switches reviewed (Admin → Emergency `/admin/emergency`; all *off* = features enabled by default): decide for each of
      `ai_automation`, `ai_assistant`, `ai_content_studio`, `ai_seo`, `ai_social`, `ai_radar`, `social_publishing`,
      `mcp_access`, `mcp_writes`, `radar_collection`, `automation_webhooks` — switch on (disable) anything without credentials yet.
- [ ] Settings → General / Contact filled in **both languages** (company name, tagline, e-mail, phone, WhatsApp,
      address, hours, meeting link); `SUPPORT_EMAIL` correct on error pages.
- [ ] Legal pages (privacy policy, terms, cookies) reviewed and approved by the owner in EN and AR; footer links work.
- [ ] Real content entered for every published section (portfolio, testimonials, stats come from the owner); no demo
      data — `DemoContentSeeder` never runs on production.
- [ ] Error pages render branded, bilingual, without stack traces: 403, 404, 410, 419, 429, 500, 503 (`php artisan down`
      shows 503 with retry; `php artisan up`).
- [ ] Rate limits answer 429 after repeated failed logins / 2FA codes, and on webhook and MCP floods (Nginx + app).
- [ ] CSP: browse public site, portal and admin (TipTap editor, Chart.js, media picker, pay page) with the console
      open — no CSP violations; if `CSP_REPORT_ONLY=true` was used, switch it to `false` and re-check.
- [ ] `php artisan app:check-production` → **passes** (no FAIL; warnings understood).

### 5.3 Integrations (🔑)
- [ ] 🔑 **SMTP**: mail provider account, SPF/DKIM/DMARC records; invitation, password reset, lead notification and
      portal invitation e-mails arrive (EN and AR) and are not spam-flagged.
- [ ] 🔑 **Payments** (Admin → Settings → Payments, password confirmation): sandbox keys tested end to end
      (pay link → gateway → webhook → *Paid* → receipt) for each gateway in use — Stripe, Paymob, PayTabs, Fawry; then
      **switch mode test → live** with live keys and repeat one small real payment + refund (Finance approval).
- [ ] 🔑 **Webhooks registered** at each gateway: `https://{canonical}/webhooks/payments/{stripe|paymob|paytabs|fawry}`
      with the signing secret saved in Settings; duplicate delivery is ignored (idempotent).
- [ ] 🔑 **Social apps** (LinkedIn Community Management API, Meta app with Pages/Instagram permissions, X API): app
      review approved; redirect URIs `https://{canonical}/admin/social/oauth/{linkedin|meta|x}/callback` registered;
      accounts connected; one test post published per platform (or manual export fallback documented).
- [ ] 🔑 **n8n**: outbound workflows' URLs + secrets saved; inbound endpoints `https://{canonical}/webhooks/n8n/{key}`
      signed (HMAC) and tested; Mostaql / PeoplePerHour alert e-mails forwarded to the "Radar alert email" endpoint.
- [ ] 🔑 **AI provider** (OpenAI or Anthropic key, monthly budget): assistant, Content Studio and SEO assistant answer;
      budget caps set; the assistant's no-AI fallback questionnaire still works with the key removed.
- [ ] 🔑 **Freelance Radar**: Freelancer API token; Upwork API key (approved) — query fields re-checked against Upwork's
      schema; Telegram bot token + chat ID → test alert and digest.
- [ ] 🔑 **Google**: GA4 measurement ID (+ API secret for server events); **cookie consent banner** appears and GA4 loads
      only after consent; service account with GA4 property + Search Console access → `analytics:sync` succeeds.
- [ ] 🔑 **Search Console**: property verified; `robots.txt` allows crawling and announces the sitemap; Settings → SEO →
      indexing **Allow**; `sitemap.xml` submitted; no `X-Robots-Tag: noindex` on production responses.
- [ ] 🔑 **MCP**: client token issued with the minimum scopes; read tools work; a draft tool creates an approval
      request, not a change. Optional HTTP transport only behind TLS with `MCP_HTTP_AUTH_TOKEN`.

### 5.4 Operations
- [ ] Workers: `supervisorctl status 'codefanz:*'` all RUNNING; upload an image → AVIF/WebP variants within a minute.
- [ ] Scheduler: `php artisan schedule:list`; `/health` shows the heartbeat < 60 s.
- [ ] **Backups**: `/etc/codefanz/backup.key` (or GPG public key) installed and its copy stored off the server;
      first nightly run in `/var/backups/codefanz/daily`; off-site copy visible at the remote; **`restore-drill.sh`
      passed on the server** (record the output here).
- [ ] **Monitoring on**: external uptime check of `/health` (with `X-Health-Token`) + a keyword check of `/en` + TLS
      expiry; alerts reach the owner by e-mail and Telegram; Slack/Telegram error channel via `LOG_STACK=daily,slack`
      receives a test critical entry (`php artisan tinker --execute 'logger()->critical("go-live alert test");'`).
- [ ] Logs: `storage/logs/laravel-YYYY-MM-DD.log` rotating; no errors after a full click-through.
- [ ] Final regression on production (read-only where possible): the critical flows in §1 in EN and AR, desktop and
      390 px, then the master prompt's Phase 20 production checklist.
