# CodeFanz Digital Platform — Project Overview

> Status: Phase 0 (Discovery & Architecture) · Last updated: 2026-11-12
> Owner: Project Manager / Business Analyst · Product: codefanz.com

## 1. Product vision

CodeFanz is a software development agency. The platform is not a brochure site; it is the
operating system for the business:

| Pillar | What it does | Primary users |
|---|---|---|
| Corporate website (EN/AR, RTL) | Present services, technologies, portfolio, products, blog | Prospects, search engines |
| Customer acquisition & CRM | Contact/quote forms, AI project assistant, leads Kanban | Sales |
| Project delivery | Projects, milestones, documents, conversations, tickets | PMs, clients |
| Finance | Payment links, gateways, invoices, receipts, audit | Finance, clients |
| Content & SEO | Blog CMS, SEO editor, audits, GA4, Search Console | Content, SEO |
| AI marketing department | AI Content Studio, social publishing, calendar | Marketing |
| Governance | Mandatory human approval for every AI/MCP public change | Admins |
| MCP server | Controlled Claude access to the platform | Admins via Claude |
| Freelance Projects Radar | Collect, score and alert on freelance opportunities | Sales |

## 2. Source requirements & precedence

| Document | Role |
|---|---|
| `MANDATORY TECHNOLOGY STACK — STRICT REQUIREMENT.md` | **Binding.** Overrides any conflicting preference. |
| `MASTER DEVELOPMENT PROMPT — CODEFANZ DIGITAL PLATFORM.md` | Functional scope, phases, acceptance criteria. |
| `CLAUDE.md` (Laravel Boost guidelines) | Coding conventions, testing, tooling. |

### Resolved conflicts

| Topic | Master prompt | Strict stack | Decision |
|---|---|---|---|
| Frontend | Next.js + TS preferred, Livewire alternative | Blade + Tailwind + Vanilla JS only; Next/Livewire/Alpine/Vue/React/jQuery banned | **Blade + Tailwind v4 + Vanilla ES modules** |
| MCP server | Standalone TypeScript preferred | Laravel is source of truth for MCP integrations | **Standalone TypeScript** (owner decision 2026-11-12). It holds no business logic: every tool calls authenticated Laravel `/api/v1` endpoints, so Laravel remains the source of truth. |
| Charts | — | Chart.js permitted | Chart.js, loaded only on dashboard pages |

## 3. Current environment (discovered 2026-11-12)

| Item | Finding |
|---|---|
| Repository | Fresh Laravel skeleton, single commit `first commit`, branch `main` |
| PHP | 8.3.36 · extensions: `gd`, `imagick`, `intl`, `pdo_mysql`, `redis` |
| Laravel | v13.40.0 |
| Testing | Pest 4, Pint, Laravel Boost (MCP) installed |
| Frontend | Vite 8, Tailwind CSS v4 (`@tailwindcss/vite`), no JS framework — compliant |
| Node | v20.20.2 / npm 10.8.2 · `node_modules` not installed yet |
| Database | MySQL 8.0.46 installed. `.env` points to `codefanz_plus` as `root` with empty password → **access denied** (blocker) |
| Redis | Running (`PONG`) — usable for cache, queues, rate limiting |
| Queue/Cache/Session | `database` drivers (will move cache/queue to Redis) |
| Mail | `log` mailer |
| codefanz.com | Not reachable from this server during discovery — no existing brand assets inspected yet |
| Existing code | Only default `User` model, `welcome` view, default migrations. Nothing to preserve beyond the skeleton. |

### Connected MCP integrations

| Integration | Status |
|---|---|
| Figma MCP | Connected as Youssef Magdy. Team "Youssef Magdy's team" (Pro, **Full** seat) can create files. Team "Codefanz" (Starter, **View** seat only) cannot host new design files. |
| Canva MCP | Connected (asset generation, brand kits) |
| Laravel Boost MCP | Connected (schema, docs search, logs) |

## 4. Team & responsibilities (RACI summary)

| Role | Owns |
|---|---|
| Project Manager / BA | Scope, roadmap, acceptance, risk register, this documentation |
| UI/UX Designer | Figma design system and all screens (Milestone 2) |
| Senior Frontend Developer | Blade components, Tailwind tokens, Vanilla JS modules, RTL, accessibility |
| Senior Backend Developer | Domain modules, services, gateways, integrations, MCP tools |
| Database Designer / Optimizer | Schema, indexes, constraints, migrations, query performance |
| Senior QA Tester | Pest feature/unit tests, browser E2E for critical flows |
| Senior QC | Definition-of-done review per milestone, code/style/security gates |
| Problem Solver | Blockers, integration feasibility, platform API constraints |

## 5. External credentials & permissions required eventually

| Integration | Needed | Notes |
|---|---|---|
| MySQL | DB user + password for `codefanz_plus` | **Blocker for Milestone 3** |
| SMTP / transactional email | Host, user, password, from address | e.g. Postmark/SES/Mailgun |
| OpenAI | API key, org/project, model choice, monthly budget | Chatbot, Content Studio, Radar scoring |
| Stripe | Test + live keys, webhook secret | Verify account country eligibility |
| Paymob | API key, integration IDs, HMAC secret | Egypt market |
| PayTabs | Profile ID, server key, region | MENA |
| Fawry | Merchant code, security key | Egypt, where applicable |
| Google Analytics 4 | OAuth client (Cloud project), property ID | Analytics Data API |
| Google Search Console | Same OAuth client, verified property | Search Console API |
| Google Tag Manager | Container ID | Optional |
| Meta (Facebook Pages / Instagram Professional) | Meta app, App Review for `pages_manage_posts`, `instagram_content_publish` | App Review can take weeks |
| LinkedIn | Marketing/Community Management API access | Requires LinkedIn approval |
| X | Paid API tier with write access | |
| TikTok | Content Posting API approval | |
| YouTube | Google OAuth + YouTube Data API quota | |
| Pinterest | API v5 app approval | |
| Telegram | Bot token, chat ID(s) | Radar alerts |
| Freelance platforms | Upwork API (partner approval), RSS/email saved searches for others | See Radar feasibility in roadmap |
| n8n | Instance URL, shared webhook signing secret | |
| WhatsApp | Business number for click-to-chat link | No API needed for links |
| Production VPS | SSH access, domain DNS, SSL | Deployment is manual and approval-gated |

## 6. Decisions log

| Date | Decision | Status |
|---|---|---|
| 2026-11-12 | MCP server: standalone TypeScript (official MCP SDK) in `mcp-server/`, calling Laravel `/api/v1` | Decided |
| 2026-11-12 | Dependencies approved: `laravel/sanctum`, `laravel/fortify`, `spatie/laravel-permission`, `intervention/image`, `chart.js`, TipTap core (`laravel/mcp` approved but not needed after MCP decision) | Decided |
| 2026-11-12 | Figma: full design file in "Youssef Magdy's team" (Pro), built in reviewable batches | Decided |
| 2026-11-12 | Brand: design a new CodeFanz identity for owner approval | Decided |
| — | MySQL credentials for local `codefanz_plus` database | **Open — blocks M3** |
| — | Real portfolio projects, products, testimonials, statistics (no placeholder production content) | **Open — needed for M4** |

## 7. Guiding rules (non-negotiable)

- No fake functionality, no simulated integrations, no placeholder buttons.
- Every AI/MCP change to public content passes through the backend approval system.
- No AI agent initiates refunds, money transfers, credential changes or permission grants.
- No destructive DB operations or deployments without explicit approval.
- Each integration is either working with real credentials or explicitly marked "requires credentials".
