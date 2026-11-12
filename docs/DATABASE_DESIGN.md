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
| `page_sections` + `page_section_translations` | page_id, type (hero, services, stats…), sort, is_visible, settings JSON — powers manageable homepage |
| `menus`, `menu_items` (+translations) | location, parent_id, url/route/linkable morph, sort |
| `redirects` | from_path UNIQUE, to_path, status_code, hits, is_active |
| `services` + `service_translations` | parent_id (sub-services), icon, cover_media_id, status, sort, lock_version |
| `service_features` (+translations), `service_faqs` (+translations) | |
| `technology_categories`, `technologies` (+translations) | proficiency enum: `Supported`, `Expertise`, `VerifiedInProjects` (computed from project links) |
| `industries` (+translations) | |
| `portfolio_projects` + translations | category_id, industry_id, type, client_name, is_client_public, project_date, live_url, android_url, ios_url, github_url, video_url, is_featured, visibility, lock_version |
| `portfolio_categories` (+translations) | |
| `products` + translations | pricing_model, availability, licensing, demo_enabled |
| `product_versions` | product_id, version, released_at, changelog (translatable) |
| Pivots | `service_technology`, `portfolio_project_technology`, `portfolio_project_service`, `product_technology` |
| `testimonials` (+translations), `faqs` (+translations), `statistics` (+translations) | status, sort, is_featured |
| `careers` (+translations), `job_applications` | |

### 2.4 Media
| Table | Notes |
|---|---|
| `media` | uuid, disk, original_path (private), mime, size, width, height, checksum UNIQUE-ish, alt (translatable via `media_translations`), folder/category, uploaded_by, status (processing/ready/failed) |
| `media_variants` | media_id, format (avif/webp/original), width, path, size; UNIQUE(media_id, format, width) |
| `mediables` | morph pivot: media_id, mediable_type/id, collection (cover, gallery, screenshots), sort — enables usage references & unused detection |

### 2.5 CRM & chatbot
| Table | Notes |
|---|---|
| `customers` | type (company/individual), company_name, country, user_id (portal login), primary contact, soft deletes |
| `contacts` | customer_id, name, email, phone, role |
| `leads` | reference UNIQUE (e.g. `LD-2026-000123`), source (contact_form, quote_form, chatbot, whatsapp, meeting, manual), name, email, phone, country, company, project_type, platform, description, features, technologies, budget_range, deadline, existing_url, preferred_contact, preferred_locale, status, priority, assigned_to, follow_up_at, estimated_value_minor, currency, converted_customer_id, lost_reason, consent_at, soft deletes. Indexes: (status, assigned_to), follow_up_at, email |
| `lead_activities` | lead_id, type (note, call, email, status_change, meeting), body, meta JSON, user_id |
| `tags`, `taggables` | shared tagging |
| `attachments` | morph attachable; private disk; original_name, mime, size, scanned_at |
| `chat_conversations` | uuid, locale, visitor fingerprint hash, ip hash, status (active, summarised, confirmed, converted, abandoned), lead_id, consent flags |
| `chat_messages` | conversation_id, role (visitor/assistant/system), content, tokens, provider, created_at — retention-pruned |
| `meeting_requests` | lead_id, preferred slots JSON, status |

### 2.6 Delivery & portal
| Table | Notes |
|---|---|
| `projects` | number UNIQUE (`PRJ-2026-0001`), customer_id, lead_id, title, description, total_value_minor, currency, start_date, due_date, status, internal_notes, client_notes, soft deletes. Index (customer_id, status) |
| `project_members` | project_id, user_id, role |
| `milestones` | project_id, title, description, amount_minor, due_date, status, approval_status, client_notes, internal_notes, sort, paid_minor (denormalised cache, recomputed from ledger) |
| `milestone_dependencies` | milestone_id, depends_on_id; UNIQUE pair; cycle check in action |
| `milestone_deliverables` | milestone_id, title, is_done |
| `documents` | morph (project/milestone), title, visibility (internal/client), media/attachment link, version |
| `conversations`, `conversation_messages` | project-scoped client ↔ team threads, read receipts |
| `support_tickets`, `ticket_replies` | customer_id, project_id, number UNIQUE, priority, status, assigned_to |

### 2.7 Billing (immutable ledger)
| Table | Notes |
|---|---|
| `payment_links` | reference UNIQUE, token_hash UNIQUE (raw token shown once), project_id, milestone_id NULL, customer_id, amount_minor, currency, description, is_reusable, max_uses, uses, expires_at, status, created_by |
| `payments` | reference UNIQUE, payment_link_id, project_id, milestone_id, customer_id, gateway, gateway_payment_id UNIQUE(gateway, gateway_payment_id), amount_minor, currency, fee_minor, net_minor, status, paid_at, verified_at. No soft deletes |
| `payment_events` | append-only status transitions (payment_id, from, to, source: webhook/verify/admin, payload JSON) |
| `refunds` | payment_id, amount_minor, status, reason, requested_by, approved_by (separate finance approval) |
| `webhook_events` | gateway, event_id, signature_valid, payload, processed_at, attempts; UNIQUE(gateway, event_id) → duplicate protection |
| `idempotency_keys` | key UNIQUE, scope, response hash, expires_at |
| `invoices`, `invoice_items` | number UNIQUE (sequential per year, gapless via locked counter), customer, project, totals, tax, status, issued_at, pdf media |
| `receipts` | payment_id UNIQUE, number UNIQUE, pdf path |
| `document_sequences` | type+year UNIQUE, next_value — `SELECT … FOR UPDATE` for gapless numbering |

### 2.8 Blog
| Table | Notes |
|---|---|
| `posts` + `post_translations` (title, slug, excerpt, body, reading_minutes, toc JSON, SEO fields) | author_id, status (Draft, AiDraft, PendingReview, Approved, Scheduled, Published, Rejected, Archived), scheduled_at, published_at, is_featured, canonical_url, lock_version, origin (human/ai/mcp) |
| `post_revisions` | post_id, locale, snapshot JSON, user_id/agent, created_at |
| `categories` (+translations), `post_tag` via `taggables` | |
| `editorial_checklists` | post_id, item, is_checked, checked_by |

### 2.9 SEO & analytics
| Table | Notes |
|---|---|
| `seo_meta` | morph seoable + locale UNIQUE; title, description, focus_keyword, secondary_keywords JSON, canonical, robots, og_title, og_description, og_media_id, schema_config JSON, score, suggestions JSON |
| `seo_audits`, `seo_audit_issues` | run metadata; issue type, url, severity, details, resolved_at |
| `link_checks` | url, source_url, status_code, checked_at |
| `analytics_snapshots` | source (ga4/gsc), metric set, date, dimensions JSON, values JSON — cached API pulls; UNIQUE(source, report_key, date) |

### 2.10 AI, social & governance
| Table | Notes |
|---|---|
| `ai_prompts` | key, locale, template, version, is_active |
| `ai_generations` | type, provider, model, prompt_key, input JSON, output, tokens_in/out, cost_minor, user_id/agent, status |
| `ai_usage_daily` | provider, model, date, tokens, cost — budget enforcement |
| `social_accounts` | platform, external_id, name, integration_id, capabilities JSON, status |
| `social_posts` | campaign_id, status (Draft, PendingReview, Approved, Scheduled, Publishing, Published, Failed, Cancelled, ManualExport), scheduled_at, origin |
| `social_post_targets` | social_post_id, social_account_id, platform_content, media JSON, status, published_url, platform_response JSON, attempts, last_error |
| `social_metrics` | target_id, metric, value, captured_at |
| `change_requests` | uuid, origin (ai/mcp/human), actor_type/id, action (create/update/delete/publish/schedule), target_type, target_id, target_version (lock_version or content hash), before JSON, proposed JSON, diff JSON, status (Pending, UnderReview, Approved, Rejected, ChangesRequested, Applied, Failed), required_permission, reviewed_by, reviewed_at, review_note, applied_at, apply_result JSON, expires_at. Index (status, created_at) |
| `change_request_comments` | review thread |
| `audit_logs` | actor_type/id (user, ai_agent, mcp_client, system), operation, auditable morph, before JSON, after JSON, change_request_id, ip, user_agent, created_at — append-only; partition by month when large |
| `mcp_clients` | name, token_id (Sanctum), scopes, last_used_at, revoked_at |
| `mcp_tool_calls` | client_id, tool, input (redacted) JSON, status, duration_ms, change_request_id |

### 2.11 Freelance Radar
| Table | Notes |
|---|---|
| `radar_platforms` | key UNIQUE, name, connector, status (Active, Unavailable, NeedsCredentials), rate_limit, last_run_at |
| `radar_projects` | platform_id, external_id, UNIQUE(platform_id, external_id), title, description, url, budget_min/max_minor, currency, type, skills JSON, posted_at, client_info JSON, proposals_count, collected_at, content_hash, source JSON, status (New, Interested, Applied, Won, Lost, Ignored). FULLTEXT(title, description) |
| `radar_evaluations` | radar_project_id, score, breakdown JSON (skill, experience, clarity, budget, opportunity, complexity, profitability, competition), explanation, model, content_hash (re-score only if changed) |
| `radar_filters` | name, rules JSON (include/exclude keywords, skills, budget, platforms, min_score, languages…), is_active |
| `radar_alerts` | radar_project_id, channel, level, content_hash, sent_at; UNIQUE(radar_project_id, channel, content_hash) → no repeat alerts |
| `radar_proposals` | AI proposal drafts (never auto-submitted) |

### 2.12 Automation & notifications
| Table | Notes |
|---|---|
| `automation_workflows` | key UNIQUE, name, direction (outbound/inbound), url, secret (enc), is_enabled |
| `automation_runs` | workflow_id, event, payload hash, status, attempts, response, error |
| `notifications` | Laravel default |
| `notification_preferences` | user_id, event, channels JSON |
| `consents` | subject (lead/user/visitor), type, version, given_at, ip_hash |
| `data_requests` | export/deletion requests with status |

## 3. Critical integrity rules

1. Payment success is set **only** by `VerifyPayment` after webhook signature check + server-side gateway verification, inside a transaction with `SELECT … FOR UPDATE` on the payment row.
2. `milestones.paid_minor` and project summaries are derived from `payments` with status `Paid`/`PartiallyRefunded` minus refunds; a recompute action exists for reconciliation.
3. `webhook_events` UNIQUE(gateway, event_id) + `payments` UNIQUE(gateway, gateway_payment_id) make duplicates no-ops.
4. Change requests store `target_version`; apply fails if the target `lock_version` changed.
5. `audit_logs`, `payment_events`, `webhook_events` have no update/delete paths in application code.

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
