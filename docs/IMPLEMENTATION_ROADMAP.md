# Implementation Roadmap

> Status legend: ✅ done · 🟡 in progress · ⬜ pending · ⛔ blocked · 🔑 needs external credentials

Every milestone ends with the **Milestone Gate** (§3). Nothing advances with failing tests.

## 1. Milestones

| # | Milestone | Lead role(s) | Key deliverables | Depends on | Status |
|---|---|---|---|---|---|
| M1 | Discovery & Architecture | PM/BA, DB designer | 8 docs in `docs/`, risk register, decisions | — | ✅ (MySQL credentials still open) |
| M2 | Figma Design | UI/UX Designer | Design system (variables, components, variants), public site (21 screens, desktop + mobile, EN + AR/RTL samples), portal (14), admin (core layouts + all module list/detail/form patterns), AI & automation screens (9) | M1, brand assets | ✅ Design complete v1.0 (200 frames, EN/AR); awaiting owner sign-off |
| M3 | Core Platform | Backend, Frontend, DB | Tokens → Tailwind `@theme`, Blade component library, JS core modules, auth + MFA, RBAC, admin shell, i18n/RTL, settings (encrypted), media pipeline (AVIF/WebP), audit log, approval engine core, kill switches | M2 design system, MySQL access, dependency approvals | 🟡 Slice 1 done — auth + 2FA, RBAC, i18n/RTL, approval engine, audit, kill switches, admin shell, tokens/components; 37 tests passing on MySQL 8. Slice 2 done — media library (private originals, sha256 dedupe, queued AVIF + WebP responsive variants at 320–1920w, metadata stripped with ICC kept, animated GIFs kept, per-language alt, usage-guarded delete, `<x-media.picture>`), bilingual Pages CMS (draft/publish, per-locale slugs incl. Arabic, optimistic locking that makes pending AI proposals stale, sanitized Markdown, hreflang/canonical); 73 tests passing. Slice 3 done — users & roles (invite by email in the user's language, multi-role, deactivate ends all sessions and API tokens, self-lockout and Super Admin escalation guards, 2FA reset behind password confirmation, read-only permission matrix), settings (General/Contact per language, encrypted write-only secrets, honest integration statuses), profile + password change (signs out other devices), recovery codes behind password confirmation; 102 tests passing. Next: Milestone 4 corporate website |
| M4 | Corporate Website | Frontend, Backend | Homepage (section manager), pages CMS, menus, services, technologies, industries, portfolio, products, testimonials, FAQ, careers, legal, contact, 404/500 | M3 | ✅ Done — **Slice 1** catalog: Services (tiers, sub-services, features, FAQs, tech stack), Technologies (Supported/Expertise, computed Verified), Industries; `HasTranslations`/`HasMediaCollections` traits; `DemoContentSeeder`. **Slice 2** Portfolio (categories, filters, public/unlisted, gallery, client-name consent, privacy video embeds) and Software products (licensing without prices, versions + changelogs). **Slice 3** consent-gated Testimonials, FAQ (FAQPage JSON-LD, search), admin-only Statistics, Careers (JobPosting JSON-LD), Menus (5 locations, cached, route whitelist) and the homepage section manager. **Slice 4** About page and Careers perks from managed page sections (section manager now switches Homepage/About/Careers; story, values and disciplines appear only once written), Contact page (email/WhatsApp/phone/meeting channels from Settings, hidden when unset; AI assistant shown as coming soon) with a small `contact_messages` form (consent required, honeypot + time trap + per-IP rate limits, audited, email notification, read-only admin inbox under `leads.view`); quote/demo CTAs now open the contact page with `?topic=` prefill (CVs still by email); legal template with numbered clauses + contents sidebar, draft Privacy/Terms in the demo seeder only, legal footer links from published legal pages; DB-free bilingual 404/500/403/419/429/503 pages, 404 site search, 500 shows the per-request `X-Request-Id` reference that is also in the log context; OpenGraph + canonical + hreflang on every public page, noindex for unlisted/search/errors; a11y fixes. 314 tests passing. Next: Milestone 5 (leads, quote form, AI assistant) |
| M5 | CRM & Acquisition | Backend, Frontend | Contact/quote forms + uploads, leads table + Kanban, activities, assignment, notifications, AI Project Assistant (with fallback), customer portal shell | M3, 🔑 OpenAI, SMTP | 🟡 In progress — **Slice 1** Request a Quote (`/{locale}/quote`, four-step vanilla-JS stepper that degrades to one form; every Phase 5 field; up to 5 private attachments checked by MIME + extension, random names, authorized `Content-Disposition: attachment` download; same honeypot/time-trap/throttle as contact; `?service=`/`?ref=`/UTM prefill; quote CTAs repointed). Leads CRM: `leads` (LD-YYYY-NNNNN reference + ulid, source/status/priority enums, validated transitions, Lost needs a reason, lock_version, dedupe hint, UTM/referrer), `lead_activities` timeline, `lead_attachments`, services/technologies pivots; every contact message also becomes a lead (+ `leads:backfill-contact-messages`). Admin table (filters/search/sort), Kanban (drag & drop + keyboard Move form, optimistic JSON with 409 on stale version), detail page, manual create/edit, assignment to `leads.manage` staff. Database + mail notifications (new lead, assignment) in the recipient's locale, admin notification bell, visitor auto-reply in the chosen language; `LeadCreated`/`LeadStatusChanged` events for n8n. Next: AI Project Assistant, customer portal shell |
| M6 | Delivery & Payments | Backend, DB, QA | Projects, milestones (+deps), documents, conversations, tickets, payment links, gateway abstraction, Stripe → Paymob → PayTabs → Fawry, webhooks, verification, invoices, receipts | M5, 🔑 gateway sandbox keys | ⬜ |
| M7 | Blog & SEO | Backend, Frontend | Blog CMS (revisions, scheduling, editorial checklist), SEO meta editor & scoring, sitemaps, robots, redirects, structured data, hreflang, SEO audit crawler, GA4 + GSC dashboards | M4, 🔑 Google OAuth | ⬜ |
| M8 | AI Marketing Department | Backend, Frontend | AI Content Studio, prompt library, usage/cost limits, social accounts, post composer, calendar, publish queue, retries, metrics, manual export fallback, full approval UI | M7, 🔑 social app approvals | ⬜ |
| M9 | MCP Server | Backend | TypeScript `mcp-server/` + Laravel `/api/v1/mcp` endpoints; read/draft/approval/execute tools, scoped tokens, rate limits, audit, README + Claude Code/Desktop configs, tests | M8 approval engine | ⬜ |
| M10 | Freelance Radar | Backend, Problem Solver | Connectors (compliant only), pipeline, filters, scoring engine, Telegram alerts/digest, analytics, proposal drafts | M3, 🔑 Telegram, OpenAI | ⬜ |
| M11 | Testing & Optimization | QA, QC | Security review (OWASP), browser E2E for critical flows, Core Web Vitals, a11y (WCAG 2.2 AA), SEO validation | M4–M10 | ⬜ |
| M12 | Production Preparation | DevOps, PM | Nginx/Supervisor/cron configs, backups/restore, monitoring, final QA checklist | M11 | ⬜ |
| — | n8n integration | Backend | Signed outbound events + inbound webhooks, run log, enable/disable | Built incrementally from M5 onward | ⬜ |

## 2. Freelance Radar — connector feasibility (Problem Solver)

| Platform | Compliant collection method | Expected initial status |
|---|---|---|
| Upwork | Official GraphQL API (requires approved API key) | NeedsCredentials |
| Freelancer | Official public API (projects search) | Active once token configured |
| Mostaql | Saved-search email alerts via IMAP; no official public API known | Email connector |
| Khamsat | Mainly services marketplace; requests section — email/RSS if available, else Unavailable | Verify, likely Unavailable |
| PeoplePerHour | Email alerts via IMAP | Email connector |

No scraping that bypasses authentication, CAPTCHAs or ToS. Final status confirmed against current docs/terms before M10.

## 3. Milestone Gate (Definition of Done)

1. Feature works end-to-end against real or sandbox services (or explicitly marked 🔑 with UI saying so).
2. Pest feature tests for happy path, validation failures, authorization failures, and client isolation.
3. `vendor/bin/pint --dirty` clean; no N+1 (lazy loading prevention on); queries reviewed by DB designer.
4. EN + AR/RTL verified on desktop and mobile widths; keyboard and screen-reader basics.
5. QC review: no placeholder buttons, no hardcoded secrets, no business logic in JS.
6. Docs updated (this roadmap + module docs).

## 4. Critical test matrix (QA)

Authentication & MFA · authorization per role · CRM lead lifecycle · project + milestone calculations ·
payment verification & duplicate webhooks · refunds need finance approval · AI approval engine (stale version,
insufficient approver, kill switch) · MCP token scopes · blog scheduling only after approval · SEO meta output ·
media conversion (AVIF verified) · social publish success/failure/retry (faked HTTP) · client data isolation.

## 5. Risk register

| Risk | Impact | Mitigation |
|---|---|---|
| Social platform API approvals take weeks | M8 partially blocked | Manual export workflow first; apply for app review early |
| Freelance platforms without APIs | Radar coverage gaps | Email/IMAP connectors; honest "Unavailable" status |
| Gateway account eligibility by country | Payments delayed | Abstraction layer; start with whichever sandbox is available |
| Scope size (≈25 deliverables) | Schedule | Strict milestone gates; no parallel half-finished modules |
| Figma MCP rate limits / seat limits | Design throughput | Build in Pro team; components first, screens reuse them |
| No real portfolio/testimonial content | Can't launch with placeholders | Content intake form for product owner during M4 |
| AI cost overrun | Budget | Per-day/month caps in `ai_usage_daily`, kill switch |
