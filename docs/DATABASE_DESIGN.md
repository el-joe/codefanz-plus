# Database Design (MySQL 8, InnoDB, utf8mb4_unicode_ci)

> Owner: Database Designer / Optimizer. Conventions below apply to every migration.

## 1. Conventions

- `id` BIGINT UNSIGNED PK; public-facing references use a separate `ulid`/`reference` column (never expose sequential IDs in URLs for private records).
- Foreign keys always declared; `cascadeOnDelete` only for pure child rows (translations, variants); otherwise `restrictOnDelete` / `nullOnDelete`.
- Soft deletes on business entities (content, CRM, projects, customers). **Never** on financial ledgers — those are immutable.
- Money: `amount_minor` BIGINT (minor units) + `currency` CHAR(3). No floats.
- Status columns: VARCHAR backed by PHP enums (`app/Enums`), indexed.
- Translatable entities: base table (non-language data) + `{entity}_translations` (`locale`, text fields, `slug`, SEO fields) with UNIQUE(`{entity}_id`,`locale`) and UNIQUE(`locale`,`slug`).
- Optimistic concurrency: `lock_version` INT on any entity the approval engine can modify.
- Every table gets `created_at`/`updated_at`; audit-sensitive tables add `created_by`/`updated_by`.
- JSON columns only for genuinely schemaless data (gateway payloads, AI metadata, source data).

## 2. Entity groups

### 2.1 Identity & access
| Table | Key columns / notes |
|---|---|
| `users` | name, email UNIQUE, password, locale, timezone, two_factor_secret (encrypted), two_factor_confirmed_at, last_login_at, is_active, soft deletes |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie/laravel-permission schema |
| `personal_access_tokens` | Sanctum; abilities used for API/MCP scopes; `expires_at` set |
| `login_attempts` | throttling forensics (ip, email hash, success, at) |

### 2.2 Settings, localization, integrations
| Table | Notes |
|---|---|
| `languages` | code UNIQUE, name, native_name, direction (ltr/rtl), is_default, is_active, sort |
| `settings` | group, key, value JSON, is_encrypted; UNIQUE(group,key) |
| `currencies` | code PK-ish UNIQUE, symbol, minor_unit, is_active |
| `integrations` | type (payment/ai/social/analytics/radar/n8n/telegram), provider, name, status, is_enabled, capabilities JSON, last_checked_at, last_error |
| `integration_credentials` | integration_id FK, key, value (encrypted), expires_at, refresh metadata |
| `oauth_tokens` | integration_id, access_token (enc), refresh_token (enc), scopes, expires_at |
| `kill_switches` | key UNIQUE (`ai_automation`, `social_publishing`, `mcp_writes`), is_active, changed_by, reason |

### 2.3 CMS & catalog
| Table | Notes |
|---|---|
| `pages` + `page_translations` | template, status, published_at, lock_version |
| `page_sections` + `page_section_translations` | `page` key (`home`, `about`, `careers` — see `PageSection::PAGES`), type (`App\Enums\PageSectionType`, UNIQUE per page), sort_order, is_visible, settings JSON (`limit`, `cta_url`); translations: eyebrow, heading, subheading, cta_label, `points` JSON list, `items` JSON list aligned across locales. A language without a row uses the built-in default wording. Rows are structural (`PageSectionSeeder`) |
| `menus`, `menu_items` (+translations) | `menus.location` UNIQUE (`App\Enums\MenuLocation`); items: parent_id (two levels, header only), link_type `route` (whitelisted public route + `route_params` query JSON) / `linkable` morph (page, service, product, portfolio_project, career → translated slug per locale) / `url` (https only), is_visible, open_in_new_tab, sort_order; translations: label. Resolved per locale by `App\Services\MenuResolver` into cached plain arrays (versioned key bumped on menu or linked-content writes) |
| `redirects` | see §2.9 |
| `services` + `service_translations` | parent_id (one level of sub-services), tier (`core`/`specialist`), icon (curated key, `App\Enums\ContentIcon`), status, is_featured, sort_order, lock_version; cover via `mediables` collection `cover` (no cover_media_id column) |
| `service_features` (+translations), `service_faqs` (+translations) | |
| `technology_categories`, `technologies` (+translations) | key UNIQUE, icon, website_url, is_visible, sort_order; `proficiency` stores only `supported`/`expertise` (admin-set). `VerifiedInProjects` is never stored: `Technology::proficiency()` computes it from relations registered with `Technology::verifiedThrough()` (portfolio registers its published-projects relation); load with `withProficiency()` |
| `industries` (+translations) | icon, status, sort_order; translations carry name, slug, summary, `typical_solutions` JSON list, SEO fields |
| `portfolio_projects` + translations | portfolio_category_id, industry_id, type (`App\Enums\ProjectType`), client_name (shown publicly only when is_client_public), project_date, live/android/ios/github URLs (https only), video_url (YouTube/Vimeo only, embedded via youtube-nocookie / Vimeo dnt), is_featured, visibility (`public`/`unlisted` — unlisted is link-only, noindex and never verifies technologies), status, sort_order, lock_version (Approvable), soft deletes; cover + ordered gallery via `mediables` collections `cover`/`gallery`. Translations: title, slug, summary, challenge, solution, results (Markdown), `key_facts` JSON list index-aligned across locales (`null` where a language is blank), SEO fields |
| `portfolio_categories` (+translations) | sort_order; translations carry name + slug (used as `?category=` tab) |
| `products` + translations | icon, `pricing_models` JSON list of `App\Enums\PricingModel` (subscription / one_time_license / custom_quote; first is highlighted; no prices stored), availability (available/beta/coming_soon), demo_enabled, docs_url, status, sort_order, lock_version (Approvable), soft deletes; cover + `screenshots` via `mediables`. Translations: name, slug, tagline, description (Markdown), `features` JSON list (index-aligned), licensing_notes, SEO fields |
| `product_versions` (+`product_version_translations`) | product_id, version UNIQUE per product, released_at; translatable Markdown changelog |
| Pivots | `service_technology`, `portfolio_project_technology`, `portfolio_project_service`, `product_technology` |
| `testimonials` (+translations) | author_name, company, rating 1–5 (nullable), portfolio_project_id (nullable), `consent_confirmed` (+ at/by) required to publish, status, is_featured, sort_order, soft deletes; avatar via `mediables` collection `avatar`. Translations: quote, author_role |
| `faq_categories` (+translations: name), `faqs` (+translations: question, answer Markdown) | faq_category_id (nullable), status, is_featured (homepage first), sort_order, soft deletes |
| `statistics` (+translations: label, suffix) | value DECIMAL(12,2), status, sort_order |
| `careers` (+translations), `job_applications` | careers: employment_type (`App\Enums\EmploymentType`), is_remote, status, closes_at (inclusive; open = published and not past), sort_order, soft deletes. Translations: title, slug, department, location (translated for visitors), summary, `responsibilities`/`requirements` JSON lists, body Markdown, SEO fields. `job_applications` arrives with the M5 forms |

### 2.4 Media
| Table | Notes |
|---|---|
| `media` | uuid, disk, original_path (private), mime, size, width, height, checksum UNIQUE-ish, alt (translatable via `media_translations`), folder/category, uploaded_by, status (processing/ready/failed) |
| `media_variants` | media_id, format (avif/webp/original), width, path, size; UNIQUE(media_id, format, width) |
| `mediables` | morph pivot: media_id, mediable_type/id, collection (cover, gallery, screenshots), sort — enables usage references & unused detection |

### 2.5 CRM & chatbot
| Table | Notes |
|---|---|
| `customers` | ulid UNIQUE (admin/portal URLs), reference UNIQUE (`CU-2026-00042`, from the id after insert), type (`CustomerType`: company/individual), status (`CustomerStatus`: active/inactive, indexed; only active customers can use the portal), company_name (account name; the person's name for individuals), contact_name, email, phone, country (ISO-2), tax_id, billing_address_line1/line2/city/state/postal_code, preferred_locale, notes (staff only), created_from_lead_id UNIQUE (won lead it was converted from, nullOnDelete), created_by/updated_by, soft deletes |
| `customer_user` | Portal membership: customer_id, user_id (role `client`), role (`CustomerUserRole`: owner/member), invited_at, revoked_at (access switched off; row kept for audit). UNIQUE(customer_id, user_id), index (user_id, revoked_at). One company can have several portal users; one user can belong to several customers (account switcher) |
| `contacts` | customer_id, name, email, phone, role |
| `contact_messages` | Website contact-form intake (M4): name, email, phone, topic (`App\Enums\ContactTopic`, indexed), message, preferred_locale, locale, consented_at, ip_address, user_agent, soft deletes. Read-only admin inbox (`leads.view`); every message also becomes a `leads` row (source `contact_form`, linked by `leads.contact_message_id`; older messages via `php artisan leads:backfill-contact-messages`) |
| `leads` | ulid UNIQUE (admin URLs), reference UNIQUE (`LD-2026-00042`, from the id after insert), source (`App\Enums\LeadSource`: quote_form, contact_form, ai_assistant, manual, mcp, radar), status (`LeadStatus`: new → contacted → qualified → proposal → negotiation → won / lost; open statuses move freely, won is final, lost may reopen), priority, assigned_to, name, email, phone, company, country (ISO-2), preferred_locale, preferred_contact (email/whatsapp/phone), platforms JSON, description, features, goals, existing_url, budget_range, timeline, deadline, brief JSON (structured form/AI data), estimated_value_minor + currency, follow_up_at, lost_reason, contact_message_id UNIQUE (contact-form leads), possible_duplicate_of_id (dedupe hint: open lead with the same email; never merged automatically), locale, utm_source/medium/campaign, referrer, ip_address, user_agent, consent_at, lock_version, created_by/updated_by, soft deletes. Indexes: (status, assigned_to), follow_up_at, email, created_at. Pivots `lead_service`, `lead_technology` |
| `lead_activities` | lead_id, type (note, call, email, meeting, status_change, assignment, system), body, meta JSON, user_id — append-only; status/assignment/system rows are written by the application only |
| `lead_attachments` | uuid, lead_id, disk (private), path (random name), original_name, mime, size, uploaded_by. Downloaded only via the authorized admin route |
| `notifications` | Laravel database notifications (admin bell); data stores keys (kind, lead ulid, reference, name) so text renders in the reader's language |
| `tags`, `taggables` | shared tagging |
| `attachments` | morph attachable; private disk; original_name, mime, size, scanned_at |
| `chat_sessions` | AI Project Assistant conversations: ulid, token_hash (sha256 of the browser's secret token), locale, mode (ai/guided), status (active/completed), fallback_reason, brief JSON (normalized), user_turns, ip_hash (HMAC), user_agent, lead_id, last_activity_at, completed_at. `MassPrunable`: unconverted after `codefanz.assistant.retention_days` (30), converted after `lead_retention_days` (365) |
| `chat_messages` | chat_session_id (cascade), role (user/assistant), content (plain text; model output stripped of HTML), created_at |
| `meeting_requests` | lead_id, preferred slots JSON, status |

### 2.6 Delivery & portal
| Table | Notes |
|---|---|
| `projects` | ulid UNIQUE, reference UNIQUE (`PR-2026-00042`, from the id after insert), customer_id (restrict), lead_id (nullOnDelete), name, description (client-visible), type (`ProjectType`), status (`ProjectStatus`: planning, in_progress, on_hold, review, completed, cancelled), start_date, due_date, project_manager_id, budget_minor + currency, show_budget_to_client, show_team_to_client, lock_version, created_by/updated_by, soft deletes. Index (customer_id, status), (status, due_date). A project never moves to another customer |
| `project_members` | project_id, user_id (staff), role (free-text label); UNIQUE pair |
| `milestones` | ulid, project_id (cascade), sort_order, title, description, due_date, status (`MilestoneStatus`: pending, in_progress, awaiting_approval, approved, completed), amount_minor + currency, paid_minor (denormalised cache, recomputed from the ledger in M6.2; never written from forms), client_feedback, started_at/submitted_at/approved_at/approved_by/completed_at, lock_version |
| `milestone_dependencies` | milestone_id, depends_on_id; UNIQUE pair; same-project + cycle check in `SaveMilestone`; `ChangeMilestoneStatus` refuses to start a milestone until every dependency is completed |
| `project_notes` | project_id, user_id, visibility (`ClientVisibility`: internal / client), body. Internal notes are never queried by portal code |
| `documents` | uuid (download URLs), project_id, customer_id (portal scope), conversation_message_id (message attachments), title, category (`DocumentCategory`), visibility (internal/client), version (same title in the same project = next version; UNIQUE(project_id, title, version)), disk (private), path (random name), original_name, mime, size, uploaded_by, uploaded_by_client |
| `conversations`, `conversation_messages`, `conversation_reads` | Project threads (ulid, customer_id, subject — null = the general thread, last_message_at); plain-text messages with `from_client`; per-user read marker (`last_read_message_id`) for unread counts |
| `support_tickets`, `ticket_replies` | ulid, reference UNIQUE (`TK-2026-00042`), customer_id, project_id (nullOnDelete), opened_by, subject, category, priority, status (`TicketStatus`: open, awaiting_customer, awaiting_support, resolved, closed), assigned_to (support.manage), first_response_at, resolved_at, closed_at, last_activity_at, soft deletes. Replies: from_client, is_internal (staff notes, never shown in the portal). `tickets:close-resolved` closes tickets resolved for `SUPPORT_AUTO_CLOSE_DAYS` (7) |

### 2.7 Billing (immutable ledger)
| Table | Notes |
|---|---|
| `payment_links` | ulid UNIQUE (the unguessable public token in `/pay/{ulid}`), reference UNIQUE (PL-YYYY-NNNNN), customer_id, project_id, milestone_id NULL, amount_minor, currency, description, expires_at, single_use, gateways JSON (allowed keys), status (active/used/expired/cancelled), uses, created_by, cancelled_at/by. Milestone links are single use and capped at amount − paid − open links (checked under a milestone row lock). Never deleted |
| `payments` | ulid, reference UNIQUE (PAY-YYYY-NNNNN), payment_link_id, customer_id, project_id, milestone_id, gateway, gateway_payment_id UNIQUE(gateway, gateway_payment_id), amount_minor, fee_minor, net_minor, refunded_minor, currency, status (pending/paid/failed/cancelled/refunded/partially_refunded), verified_via, failure_reason, paid_at, verified_at, metadata JSON (gateway ids/statuses only, never card data). No soft deletes; status not mass assignable, changed only through `Payment::transitionTo()` inside payment actions |
| `payment_events` | append-only status transitions (payment_id, from_status, to_status, source: checkout/webhook/return_url/admin/reconciliation, webhook_event_id, user_id, payload JSON); model blocks update/delete |
| `refunds` | ulid, reference UNIQUE (RF-YYYY-NNNNN), payment_id, customer_id, gateway, amount_minor, currency, status (requested/approved/rejected/processing/succeeded/failed/manual_required), reason, requested_by, decided_by/decided_at/decision_note (four-eyes: never the requester), gateway_refund_id UNIQUE(gateway, gateway_refund_id), failure_reason, manual_reference + completed_by (refunds done outside the gateway API), processed_at, completed_at. Open requests reserve their amount: requests are capped at amount − refunded − open requests under a payment row lock. Never deleted |
| `credit_notes` | ulid, number UNIQUE (CN-YYYY-NNNNN, gap-free from `document_sequences`), refund_id UNIQUE, payment_id, customer_id, customer_snapshot, seller_snapshot, amount_minor, currency, reason, issued_at; immutable, issued by `CompleteRefund` in the transaction that updates the payment |
| `webhook_events` | gateway, event_id, event_type, signature_valid, payload (sanitized), attempts, processed_at, error; UNIQUE(gateway, event_id) → duplicate protection. Received data immutable; only attempts/processed_at/error change. Rejected signatures are stored under a random `unverified-…` id so they cannot reserve a real event id |
| `idempotency_keys` | (later) key UNIQUE, scope, response hash, expires_at. Gateway calls send idempotency keys per payment |
| `invoices`, `invoice_items` | ulid, number UNIQUE NULL (INV-YYYY-NNNNN, assigned on issue), customer, project, milestone, payment_link, customer_snapshot JSON (name/contact/email/address/country/tax id frozen at issue), seller_snapshot JSON (company name + address per language, email, phone frozen at issue), subtotal/tax_rate_bp/tax/total minor, currency, status (draft/issued/paid/void), issued_at, due_at, paid_at, voided_at + reason. Issued invoices are immutable except status columns; printable bilingual HTML (no PDF library) |
| `receipts` | ulid, payment_id UNIQUE, number UNIQUE (RCP-YYYY-NNNNN), customer_id, customer_snapshot, seller_snapshot, amount_minor, currency, issued_at; immutable |
| `document_sequences` | type+year UNIQUE, next_value — `SELECT … FOR UPDATE` inside the issuing transaction for gapless numbering (`App\Services\DocumentNumbers`) |

### 2.8 Blog
| Table | Notes |
|---|---|
| `posts` + `post_translations` | posts: ulid (admin route key), author_id, post_category_id, status (draft/in_review/changes_requested/approved/scheduled/published/archived), origin (human/ai — AI drafts need a reviewer other than their author), is_featured, is_single_language, scheduled_for, published_at, submitted_by/at, reviewed_by/at, approved_at, review_note, lock_version, soft deletes. Public = `published` AND `published_at <= now`. Translations: title, slug (UNIQUE locale+slug; `feed/category/tag/page` reserved), excerpt, body (sanitized HTML — the `PostTranslation::body` mutator runs `HtmlSanitizer` + `ArticleBody`, so every write path is sanitized), toc JSON (H2/H3 anchors), word_count, reading_minutes, seo_title, seo_description, focus_keyword, canonical_url |
| `post_revisions` | post_id, user_id/actor_label, reason (created/saved/restored/change_request), restored_from_id, lock_version, snapshot JSON (all translations + category/tags/cover/flags), created_at only; immutable. Restore = new revision |
| `post_categories` (+`post_category_translations`: name, slug, description), `tags` (+`tag_translations`), `post_tag` pivot | |
| `post_checklist_items` | post_id + item UNIQUE, is_manual, is_passed, detail, checked_by/at — automatic checks re-evaluated on every save plus manual editor confirmations |
| `users.author_title`, `users.author_bio` | JSON per locale; public author box on articles |

### 2.9 SEO & analytics
| Table | Notes |
|---|---|
| `seo_meta` | Per-locale SEO settings with no column on the entity translation: nullable morph `seoable` (page/service/portfolio_project/product/career/post) **or** `route_key` (fixed site pages such as `services.index`, `home`); locale, title + description (route pages only — content keeps `seo_title`/`seo_description` on its translation, posts also keep `focus_keyword`/`canonical_url` there), focus_keyword, canonical_url, noindex, lock_version, updated_by. UNIQUE(seoable_type, seoable_id, locale) and UNIQUE(route_key, locale). Written only by `App\Actions\Seo\UpdateSeoMeta` (checks and bumps the entity's `lock_version`; careers gained `lock_version` for this). OG/schema overrides from the original plan are not stored (OG follows title/description/cover) |
| `redirects` | from_path UNIQUE (normalized decoded path, no trailing slash/query), to_path (site path or absolute https URL; NULL for 410), status_code 301/302/410, hits, last_hit_at, is_active, is_automatic (slug-change 301s), note, created_by/updated_by. Served from a cached plain array only when a request would 404; loops/chains rejected on save, auto redirects re-point older ones |
| `seo_audits`, `seo_issues` | audits: status (queued/running/completed/failed), triggered_by, pages_crawled, health_score, summary JSON, error, started/finished_at. Issues: seo_audit_id (cascade), type (`App\Enums\SeoIssueType`), category, severity (error/warning/notice), url, locale, nullable morph seoable + route_key (manual-fix link), details JSON, resolved_at |
| `analytics_snapshots` | source (ga4/gsc), report_key (daily, channels, landing_pages, events / daily, queries, pages, countries, devices), date (sync day), dimensions JSON (date range), values JSON (rows) — cached API pulls; UNIQUE(source, report_key, date). Last sync outcome per source lives in Settings (`analytics_status`) |

### 2.10 AI, social & governance
| Table | Notes |
|---|---|
| `brand_profiles` | name, is_default, tone/audience JSON {locale: text}, do_rules/dont_rules JSON {locale: [rules]}, banned_claims JSON, glossary JSON [{en, ar}], allowed_domains JSON, lock_version, updated_by (M8.1) |
| `prompt_templates` | key (unique), name, purpose (blog_draft, service_copy, seo_meta, social_post, email, ad_copy, translation), locale (null = any), description, system_template, user_template, variables JSON [{name, label, required, max}], version, is_active, created_by/updated_by, soft deletes |
| `prompt_template_versions` | prompt_template_id, version, system_template, user_template, variables JSON, created_by — immutable; UNIQUE(template, version) |
| `ai_generations` | ulid, prompt_template_id, template_version, purpose, mode (write/translate), nullable target morph, locales JSON, inputs JSON, output JSON {locale: {field: text}}, guardrail_flags JSON, provider, model, input/output tokens, estimated_cost_minor, status (generated, failed, draft_created, social_draft_created, proposed, saved, discarded), is_dry_run, error, user_id, post_id. Change requests link back through `change_requests.ai_generation_id` |
| `ai_usage_logs` | one row per provider call: feature, provider, model, input/output tokens, estimated_cost_minor (US cents, 4 decimals, from the `codefanz.ai.prices` table), latency_ms, status, error (short, never prompt text), user/lead/chat_session refs. Monthly sum enforces the budget (hard stop) |
| `social_accounts` | platform (linkedin_page, facebook_page, instagram_business, x, tiktok, youtube), name, handle, profile_url, external_id (org/page/IG user/X user id; null for manual), status (connected, requires_app_approval, requires_credentials, token_expired, manual_mode, disconnected), access_token / refresh_token (encrypted casts), token_expires_at, refresh_expires_at, scopes JSON, meta JSON (e.g. IG → page_id), last_verified_at, last_error, connected_by, soft deletes; UNIQUE(platform, external_id) (M8.2) |
| `social_posts` | ulid, title (internal), campaign (indexed text), locale, status (draft, in_review, changes_requested, approved, scheduled, publishing, published, failed, exported_manually, cancelled), origin (human/ai), link_url, utm JSON {medium, campaign, content}, media_alt JSON {media_id: alt}, planned_at, scheduled_at, published_at, author_id, generated_by (AI generator — can never approve), submitted_by/at, reviewed_by, approved_at, review_note, lock_version, soft deletes. Images via `mediables` (collection `social`) |
| `social_post_variants` | social_post_id, social_account_id (UNIQUE pair), platform, text, hashtags, first_comment, include_media, ai_generation_id, status (pending, queued, publishing, published, failed, ready_for_export, exported_manually, cancelled), idempotency_key (uuid, unique), attempts, platform_state JSON (uploaded image ids / IG container reused by retries), external_id, published_url, platform_response JSON, last_error, published_at, exported_by/at, metrics_error, metrics_checked_at |
| `social_metric_snapshots` | social_post_variant_id, impressions / engagements / clicks (nullable = not reported, never zero-filled), raw JSON, captured_at |
| `change_requests` | uuid, origin (ai/mcp/human), actor_type/id, action (create/update/delete/publish/schedule), target_type, target_id, target_version (lock_version or content hash), before JSON, proposed JSON, diff JSON, status (Pending, UnderReview, Approved, Rejected, ChangesRequested, Applied, Failed), required_permission, reviewed_by, reviewed_at, review_note, applied_at, apply_result JSON, expires_at. Index (status, created_at). M8.1 adds `ai_generation_id` (nullable FK) and `guardrail_flags` JSON, plus indexes (origin, status) and target_type for the queue filters |
| `change_request_comments` | review thread |
| `audit_logs` | actor_type/id (user, ai_agent, mcp_client, system), operation, auditable morph, before JSON, after JSON, change_request_id, ip, user_agent, created_at — append-only; partition by month when large |
| `mcp_clients` | name, token_id (Sanctum), scopes, last_used_at, revoked_at |
| `mcp_tool_calls` | client_id, tool, input (redacted) JSON, status, duration_ms, change_request_id |

### 2.11 Freelance Radar
| Table | Notes |
|---|---|
| `radar_platforms` | key UNIQUE, name, connector (`api`/`email`/`none`), status (`RadarPlatformStatus`: active, needs_credentials, unavailable, error; recomputed from saved credentials, enabled inbound email endpoints and the last run), status_note, is_enabled (per-platform kill switch), fetch_interval_minutes, rate_limit_per_hour, last_run_at, last_success_at, last_error, last_run_count. Seeded by `RadarPlatformSeeder` |
| `radar_projects` | radar_platform_id, external_id, UNIQUE(radar_platform_id, external_id), url, title, description, budget_min/max_minor + currency, budget_type (fixed/hourly/unknown), skills JSON, client_country, client_rating, client_spent_minor, client_payment_verified, proposals_count, language (en/ar, detected), posted_at, collected_at, content_hash (re-score / re-alert only when it changes), source JSON, status (`RadarProjectStatus`: new, shortlisted, proposal_drafted, applied, won, lost, ignored), score (latest rule score), ai_score, lead_id, soft deletes. Indexes: (status, score), posted_at, created_at, FULLTEXT(title, description) |
| `radar_evaluations` | radar_project_id, kind (`rules` / `ai`), score, breakdown JSON (rules: per factor score, weight, reason + params; ai: matching skills), explanation, model, content_hash, weights_hash (rules re-scored only when content or weights change), user_id. The AI score never replaces the rule score |
| `radar_filters` | name, rules JSON (include/exclude keywords, skills, countries, platforms, languages, min_budget USD, min_score, budget_type), is_active (tab), alerts_enabled (Telegram gate), sort_order |
| `radar_alerts` | radar_project_id, channel, level (immediate/normal/digest), content_hash, score, status (pending/sent/failed/skipped), error, sent_at; UNIQUE(radar_project_id, channel, content_hash) → no repeat alerts |
| `radar_proposals` | radar_project_id, locale, body, guardrail_flags JSON, ai_generation_id — drafts only, never submitted anywhere |
| (settings) | group `radar` (API credentials, Telegram bot token encrypted) and group `radar_scoring` (weights, thresholds, keywords, targets, languages, digest size, AI auto-score) |

### 2.12 Automation & notifications
| Table | Notes |
|---|---|
| `automation_workflows` | key UNIQUE (random suffix, part of the inbound URL), name, direction (outbound/inbound), url (outbound), secret (encrypted, per workflow), events JSON (outbound subscriptions), action (inbound: one whitelisted `AutomationAction`), acts_as_user_id (inbound permissions), include_personal_data, is_enabled, last_run_at, last_status, soft deletes |
| `automation_runs` | uuid, automation_workflow_id, direction, event, idempotency_key (outbound delivery id / inbound sender delivery id), UNIQUE(workflow, idempotency_key) = inbound replay protection, payload_hash, payload (outbound body, resent unchanged on retries; null for inbound), status (pending/succeeded/failed/rejected), attempts, http_status, response, error, duration_ms, ip_address, completed_at |
| `notifications` | Laravel default |
| `notification_preferences` | user_id, event, channels JSON |
| `consents` | subject (lead/user/visitor), type, version, given_at, ip_hash |
| `data_requests` | export/deletion requests with status |

## 3. Critical integrity rules

1. Payment success is set **only** by `VerifyPayment` after webhook signature check + server-side gateway verification, inside a transaction with `SELECT … FOR UPDATE` on the payment row.
2. `milestones.paid_minor` and project summaries are derived from `payments` with status `Paid`/`PartiallyRefunded` minus refunds; a recompute action exists for reconciliation. Refunds reach the ledger only through `CompleteRefund` (gateway-confirmed or a recorded manual refund), which moves the payment via `transitionTo()` and recomputes the milestone.
3. `webhook_events` UNIQUE(gateway, event_id) + `payments` UNIQUE(gateway, gateway_payment_id) make duplicates no-ops.
4. Change requests store `target_version`; apply fails if the target `lock_version` changed.
5. `audit_logs`, `payment_events`, `webhook_events` have no update/delete paths in application code (models throw; webhook events only record processing attempts).

## 4. Indexing & performance plan

- Composite indexes for every list screen filter (status + assigned + date).
- FULLTEXT on post translations (title, body) and radar projects for search; switch to Meilisearch/Scout only if needed (would need approval).
- Eager-loading enforced in dev: `Model::preventLazyLoading(! app()->isProduction())`.
- Redis cache for public catalog/menus/settings, tag-invalidated on publish/apply.
- Retention: `chat_messages` and `mcp_tool_calls` pruned via `Prunable` per privacy settings.

## 5. Seeders (essential system data only)

Languages (en, ar), currencies, roles & permissions, kill switches (off), default settings, radar platforms
(with honest connector status), service/technology categories, notification defaults. Demo content lives in a
separate `DemoContentSeeder` that is never run in production.
