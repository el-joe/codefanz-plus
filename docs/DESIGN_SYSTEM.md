# CodeFanz Design System

> Figma file: https://www.figma.com/design/gZIo9Cvoi4CNAB2iUYVBMo (team "Youssef Magdy's team")
> Status: **Milestone 2 design complete (v1.0)** — foundations, component library, website (88 frames), client portal (58), admin + AI (54) · 2026-11-12 · awaiting owner sign-off

These tokens are mirrored into Tailwind v4 `@theme` in `resources/css/app.css` during Milestone 3.
Figma variables already carry these CSS names as their WEB code syntax, so Dev Mode shows the matching `var(--…)`.

## Brand
- **Mark:** rounded tile, gradient `brand/600 → accent/500`, white code chevron + "f" glyph, cyan cursor dot.
- **Wordmark:** `codefanz`, Plus Jakarta Sans ExtraBold, "code" in ink, "fanz" in brand (light) / accent-300 (dark).
- Logo component set: `Logo` (Theme = Light | Dark × Type = Full | Mark). Minimum mark size 24px.

## Typography
| Role | EN | AR | Code |
|---|---|---|---|
| Family | Plus Jakarta Sans | Cairo | JetBrains Mono |

Scale (EN size/line-height): Display XL 72/80, Display LG 56/64, H1 44/52, H2 36/44, H3 28/36, H4 22/30, H5 18/26,
Body LG 18/30, MD 16/26, SM 14/22, XS 12/18, Label LG 16/24, MD 14/20, SM 12/16, Overline 12/16.
The Arabic styles use slightly smaller sizes with taller line heights (e.g. H1 42/60, Body MD 16/28) because Cairo's letterforms are taller.

## Color primitives (`--color-{family}-{step}`)
| Family | Key steps |
|---|---|
| brand (electric indigo) | 50 `#F0EEFF` · 100 `#E2DEFF` · 300 `#A89BFF` · 500 `#6E56FF` · **600 `#5A3DF5`** · 700 `#4A2FD6` · 950 `#1B1350` |
| accent (signal cyan) | 50 `#E8FFFC` · 300 `#4CEBDD` · **400 `#1AD5C8`** · 500 `#08B8AE` · 700 `#087571` · 900 `#0E4D4B` |
| ink (neutral) | 0 `#FFFFFF` · 25 `#FBFBFE` · 50 `#F5F6FA` · 100 `#ECEEF4` · 200 `#DCE0EA` · 500 `#6A7389` · 700 `#363D50` · 900 `#141826` · 950 `#0A0D17` |
| green / amber / red / blue | 50, 100, 300, 500, 600, 700, 900 (status colors) |

## Semantic color (Light / Dark) — `--color-{group}-{name}`
- **bg:** canvas, surface, surface-raised, subtle, muted, inverse, brand, brand-hover, brand-subtle, accent, accent-subtle, success-subtle, warning-subtle, danger, danger-hover, danger-subtle, info-subtle, disabled
- **text:** primary, secondary, tertiary, disabled, inverse, on-brand, on-accent, brand, accent, success, warning, danger, info
- **border:** default, strong, subtle, brand, focus, danger, success, warning, info
- **icon:** primary, secondary, brand, on-brand, success, warning, danger, info

Components and Blade views use semantic tokens only, never primitives.

## Dimension
- Space (`--space-*`): 0, 0-5 = 2, 1 = 4, 1-5 = 6, 2 = 8, 2-5 = 10, 3 = 12, 4 = 16, 5 = 20, 6 = 24, 8 = 32, 10 = 40, 12 = 48, 16 = 64, 20 = 80, 24 = 96, 32 = 128
- Radius (`--radius-*`): none 0, xs 4, sm 6, md 10, lg 14, xl 20, 2xl 28, full
- Border: 1, 2
- Effects: Shadow XS/SM/MD/LG/XL, Shadow/Brand Glow, Focus/Ring (4px brand at 28%)

## Bilingual rule (EN / AR)

Every component with text or direction-dependent layout has a `Dir` variant: **`Dir=LTR`** (English, Plus Jakarta Sans) and **`Dir=RTL`** (Arabic, Cairo, mirrored).
The Arabic (RTL) versions follow these rules:
- Row order is reversed and alignment moves to the right; text styles switch to the matching Arabic style (`EN/x` becomes `AR/x`).
- Directional icons mirror: arrow-right ↔ arrow-left, chevron-right ↔ chevron-left. Non-directional icons (mail, search, globe…) never mirror.
- Toggles turn on toward the left, progress bars fill from the right, drawers slide in from the left, and toasts stack bottom-left.
- Emails, phone numbers, amounts, IDs and code stay LTR inside RTL layouts. In code these get `dir="ltr"`/`<bdi>`.
- Chart time axes stay chronological left→right in both directions. Titles, legends and controls follow the page direction.
- The logo never mirrors.
- In code, a single Blade component serves both directions. It uses Tailwind logical utilities (`ms-*`, `ps-*`, `start-*`, `text-start`) and `rtl:` variants for icon mirroring, with `<html dir>` set from the locale. The two Figma variants are a spec, not two implementations.
- Component property note: Arabic variants keep their show/hide toggles, but their sample text is plain text, not tied to a text property. Edit it directly on the instance.

## Components

| Page | Component | Variants (each × Dir LTR/RTL unless noted) |
|---|---|---|
| Icons | Icon (48) | 24px stroke icons, 1.75px; color via `icon/*` (no Dir) |
| Brand | Logo | Theme Light/Dark × Type Full/Mark (no Dir) |
| Core | Button | Size SM/MD/LG × Style Primary/Secondary/Ghost/Danger × State Default/Hover/Disabled (72) |
| Core | Input · Badge · Alert · Card/Service | States / 7 tones / 4 tones / Default+Hover |
| Forms | Select (with open listbox), Textarea, Checkbox, Radio, Toggle, File Upload (idle/dragging/uploaded with progress) | |
| Navigation & Feedback | Tabs (Underline/Pill), Breadcrumb, Pagination, Tooltip (Top/Bottom), Toast (Success/Danger/Info), Avatar (SM/MD/LG × status, no Dir) | |
| Data & Overlays | Table Row (Default/Hover/Selected), Data Table (toolbar + header + rows + footer), Modal (Confirm/Danger), Drawer (lead details) | |
| Layout | Navbar (Desktop 1440 / Mobile 390, with language switch), Footer (dark), Admin Sidebar (permission-filtered nav with counts), Admin Header (search, language, theme, notifications) | |
| Dashboard | Stat Card (Up/Down), Chart Card (Chart.js area), Kanban Card (High/Normal), Chat Bubble (Assistant/Visitor), State Panel (Empty/Error/Loading skeleton) | |
| Marketing | Section Header (Center/Start), Card/Project, Card/Article, Card/Testimonial, Card/Product, Card/Industry, Accordion Item (Open/Closed), Process Step, Chip/Tech (Verified / Expertise / Supported, no Dir) | |
| Layout | Footer now also has `Device=Mobile` (390) | |
| Layout (portal) | Portal Sidebar (nav items `nav:<key>`), App Header (crumb-1/crumb-2/page-title/search-placeholder), Mobile App Bar, Bottom Tab Bar (`tab:<key>`), Progress Bar (Brand/Success; fill anchors to inline-start) | |

## Website screens (batch 3) — page "Website — Screens"

Each screen is a Figma Section containing four frames: **Desktop EN (1440)**, **Desktop AR (1440, RTL)**, **Mobile EN (390)** and **Mobile AR (390, RTL)**.

| # | Screen | Key content |
|---|---|---|
| 01 | Home | Hero with product visual, services (6), technologies (Verified/Supported), featured projects, products, industries, process, why CodeFanz + delivery promise, stats, testimonials, latest articles, FAQ, CTA, footer |
| 02 | About Us | Story, mission, values, team disciplines |
| 03 | Services | 9 core services + 7 specialist services (all 16 required categories) |
| 04 | Service Detail | Deliverables, tech stack, delivery phases, sticky quote card, related projects, FAQ |
| 05 | Technologies | Three-level legend (Verified / Expertise / Supported), grouped chips, auto-calculated note |
| 06 | Portfolio | Category tabs, search, industry filter, featured project, grid, pagination, "Request a similar solution" CTA |
| 07 | Project Detail | Badges, live demo / store links, gallery, challenge / solution / results, facts sidebar, features, testimonial, related projects |
| 08 | Products | Four product cards, "why a product", contact sales |
| 09 | Product Detail | Version / licensing badges, features, licensing models (no fixed prices, preliminary wording), changelog, docs & license, demo request form |
| 10 | Industries | Six industries with typical solutions |
| 11 | Blog | Search, category tabs, featured article, grid, pagination |
| 12 | Blog Article | Byline, share, cover, table of contents, rich body (callout, list, code block), author bio, related articles |
| 13 | Contact | Five channels (email, WhatsApp, phone, meeting, AI assistant), contact form with consent |
| 14 | Request a Quote | Four-step form covering every Phase 5 field (incl. attachments, contact method, language), "What happens next" panel |
| 15 | AI Project Assistant | Free / no-payment badge, conversation, confirm-summary card, quick replies, composer, brief progress panel (mobile: collapsible strip) |
| 16 | Careers | Perks, open positions, send CV |
| 17 | FAQ | Search + grouped accordions |
| 18 | Testimonials | Six testimonials (consent note) |
| 19–20 | Privacy Policy, Terms & Conditions | Contents sidebar + numbered clauses |
| 21–22 | 404, 500 | Search + popular links; error reference ID + support contact |

### Content status (must be replaced before launch)
All project names, products, testimonials, statistics, job openings, phone numbers and legal copy in the designs are **sample content for layout only**. They must be replaced with real, approved content from the admin dashboard. Legal pages need review by counsel. No fabricated testimonials or stats may ship to production.

### Figma build notes
- Cloning a variant drops its links to component properties. Re-link them after cloning (this happened to the Expertise chip and the Arabic Button icons).
- Screenshots of instances can show stale renders after swaps. Check node data before "fixing" a correct layout.

## Client portal screens (batch 4) — page "Portal — Screens"

Desktop frames are 1440 wide (portal sidebar plus content, min height 1024). Mobile frames are 390 wide (top app bar, content and bottom tab bar). Every screen comes in EN and AR (RTL).

| # | Screen | Key content |
|---|---|---|
| P01 | Login | Brand panel, email/password, remember me, forgot password, 2FA note, "Request access" (accounts are invite-based) |
| P02 | Registration | Invitation banner, account fields, password policy hint, consent |
| P03 | Payment Link Checkout (+ Success) | Locked amount ("set by CodeFanz, cannot be changed"), reference, project, milestone, expiry; gateway choice (Stripe / Paymob / PayTabs); notes on gateway verification and no stored card data; success screen with receipt |
| P04 | Dashboard | Payment-due alert, KPI tiles, active project with milestone status, recent invoices with Pay, messages |
| P05 | My Projects | Status tabs, project cards with progress, next milestone, paid / total |
| P06 | Project Details | Header and tabs, value / paid / remaining / delivery, progress segments, PM update, activity, team, contract docs |
| P07 | Milestones | M1–M5 with status, due date, amount, payment status (incl. partial), deliverables, dependency ("starts after M3"), Approve / Request changes / Pay remaining |
| P08 | Invoices | Filters, table (mobile: cards), Pay now on unpaid invoices |
| P09 | Payment History | Totals, table with method/gateway, failed attempt shown honestly, receipt links |
| P10 | Documents | Type filters, upload, document cards (type, version, size, shared by) |
| P11 | Conversations | Project threads, thread view with attachments, composer (mobile: thread only) |
| P12 | Support Tickets | Status tabs, ticket list with priority/status, ticket detail and reply |
| P13 | Notifications | Grouped Today / Earlier, unread markers, inline actions (Pay now, Review, Receipt) |
| P14 | Profile & Settings | Profile, language, notification preferences (marketing off by default), password, 2FA, active sessions, export data / request deletion |

### Additional Figma build notes
- Bound colour paints need a real fallback colour (the resolved light-mode value). Otherwise previews show the fallback (grey/black) for overrides.
- Nodes can't be appended inside an instance (for example a trailing icon in Input). Expose a slot or property in the component instead.

## Admin & AI screens (batch 5) — page "Admin — Screens"

Admin is desktop-first: every screen is designed at 1440 in EN and AR. Overview, Approval Queue and Freelance Radar also have mobile (390) EN/AR versions. Each screen uses the shared admin shell (Admin Sidebar, App Header, content area) and the shared list, detail and form layouts.

| # | Screen | Highlights |
|---|---|---|
| A01 | Overview | KPIs, revenue chart, approval queue, newest leads, integration health (honest statuses) |
| A02 | Leads — Kanban | Status columns (New → Won), drag with keyboard support, activity logging note |
| A03 | Leads — Table & detail | Data table plus lead drawer over a backdrop |
| A04 | Project — Milestones & Payment Link | Milestone table (amount/paid/status/payment), internal vs client-visible notes, Create Payment Link dialog (amount capped at the milestone's remaining balance, expiry, single use, gateways, security note) |
| A05 | Payments & Transactions | Gateway, fee, net, verification column, failed payment, refund awaiting finance approval, financial-controls notice |
| A06 | Users & Roles | Users with multiple roles, MFA status, server-side permission matrix |
| A07 | Audit Logs | Actor types (user / AI agent / MCP client / system), change-request link, blocked publish without approval, before/after diff |
| A08 | Pages — Homepage Sections | Reorder, show/hide and edit sections in EN/AR, statuses incl. "AI proposal pending" |
| A09 | Content form — Service | EN / AR / Features & FAQ / SEO tabs, rich-text editor, tech chips, publishing panel, translation status, AVIF media, search preview |
| A10 | Media Library | AVIF/WebP/SVG badges, usage counts, unused detection, alt text EN/AR, original vs delivered size, responsive variants |
| A11 | Blog Editor | AI-draft warning, editorial workflow states, scheduling, editorial checklist, SEO score |
| A12 | SEO Management & Audit | Health score with "doesn't guarantee rankings", issue categories, issues table, manual fix vs AI fix (needs approval) |
| A13 | Analytics — GA4 & Search Console | KPIs, sessions chart, traffic sources, conversion events, GSC metrics with expired-token state (cached data, nothing invented) |
| B01 | AI Content Studio | Provider and monthly cost cap, template gallery, recent generations with status |
| B02 | AI Article Generator | Brief (languages, audience, tone, keywords, length), outline + draft, internal links, verify-before-submit, "Send to approval" |
| B03 | SEO Assistant | Current vs suggested meta with character counts, keyword opportunities, internal links & schema, "Propose changes" |
| B04 | Approval Queue | Request list (AI/MCP origin) and review with before/after diff, re-verified checks, review note, Approve & apply / Request changes / Reject |
| B05 | AI Suggestions | SEO / content / social / sales suggestions; public changes route to approval |
| B06 | Social Media Publisher | Per-platform text, media, schedule, campaign, preview, account statuses (paid API tier, manual export) |
| B07 | Content Calendar | Month view with published / scheduled / pending / failed, "scheduled ≠ approved" rule |
| B08 | MCP Management & Activity Logs | Clients with scopes, safety switches, rate limit, connection snippet, tool-call log incl. denied stale execution |
| B09 | Freelance Projects Radar | Connector statuses (incl. Unavailable / Needs approval), filter profiles, AI score with explanation and breakdown, Telegram alert badge, draft proposals (never auto-submitted) |
| B10 | Automation & Emergency Controls | Kill switches, cancel scheduled content, n8n workflows (signed, retries, failed run) |
| B11 | System Settings & Integrations | Masked encrypted credentials, honest statuses (Live / Sandbox / Requires credentials / Reconnect), test connection |

### Admin module coverage (33 modules → screens)
Overview A01 · Pages A08 · Navigation & Menus A08 pattern · Services A09 · Technologies A09 pattern + Chip/Tech · Portfolio A09 pattern · Software Products A09 pattern · Leads & CRM A02/A03 · Customers A03 pattern · Projects A04 · Milestones A04 · Payments A05 · Invoices A05 (tab) · Transactions A05 · Blog A11 · Categories & Tags A03 table pattern · Media Library A10 · SEO Management A12 + B03 · SEO Audits A12 · Google Analytics A13 · Search Console A13 · Social Accounts B06/B11 · Social Calendar B07 · AI Content Studio B01/B02 · AI Approval Queue B04 · MCP Management B08 · Freelance Radar B09 · n8n Integrations B10 · Notifications (portal P13 pattern) · Support Tickets (portal P12 pattern) · Users & Roles A06 · Audit Logs A07 · System Settings B11.

## Consistency review (end of Milestone 2)
- **Bilingual:** every screen and component exists in EN/LTR and AR/RTL. Checked visually and against node data. Some preview renders lag behind instance swaps, so layout data was confirmed separately.
- **Governance:** every AI/MCP path in the designs ends at the Approval Queue. No screen shows AI publishing directly, AI-initiated refunds, or invented integration data.
- **Known gaps to resolve in build:**
  - The Admin Sidebar component lists 16 primary items. Analytics, Users & Roles, Audit Logs and Automation are reached through SEO / Settings. In code, use collapsible groups containing all 33 modules.
  - Mobile admin is designed for 3 key screens only; other admin screens use the responsive table → card pattern.
- **Content:** all names, figures, testimonials, legal copy and statistics are sample content and must be replaced before launch.

## Next
Owner reviews the Figma file and signs off. Then Milestone 3 (core platform build) starts, which needs MySQL access.
