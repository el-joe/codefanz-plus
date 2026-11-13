# Security Policy

Baseline: OWASP ASVS L2 / OWASP Top 10.

## 1. Authentication
- Session auth (Fortify backend, Blade views). Login throttling per email+IP; generic error messages.
- TOTP MFA **mandatory** for Super Admin, Administrator, Finance Manager, and any user with approval permissions; optional for clients.
- Secure password reset (signed, expiring tokens), password rules (min 12, breached-password check via `Password::uncompromised()`).
- Session: `secure`, `http_only`, `same_site=lax`, encrypted, regenerated on login/privilege change, idle timeout for admin.

## 2. Authorization
- Policies on every model; Form Request `authorize()`; route middleware by permission.
- Client portal: all queries scoped to the authenticated customer; ULID references; isolation tests per resource.
- Sanctum token abilities for API/MCP (one hashed token per MCP client, abilities = scopes, owner permission re-checked per call, optional IP allow-list and expiry); AI/MCP actors can never hold finance, user-management or security abilities.

## 3. Input & output
- Validation via Form Requests; Eloquent/bindings only (no raw SQL with interpolation).
- Blade auto-escaping; rich-text HTML sanitized server-side (allow-list) before storage and render.
- Strict CSP (nonce-based for inline scripts), `X-Frame-Options: DENY` (except gateway iframes as required), HSTS, `Referrer-Policy`, `Permissions-Policy`.

## 4. Files
- Private disk for originals, attachments, documents; served via signed, authorized routes.
- MIME sniffing + extension allow-list + size/dimension limits; images re-encoded (strips payloads); SVG sanitized; no executable uploads; randomized filenames.

## 5. SSRF
- Outbound HTTP only to configured integration hosts; any user-supplied URL (link checker, existing website) goes through a guard blocking private/loopback/metadata IP ranges and non-http(s) schemes, with redirect re-validation.

## 6. Webhooks & integrations
- Signature verification per gateway (Stripe `Stripe-Signature` HMAC-SHA256 with timestamp tolerance, Paymob HMAC-SHA512 over the documented transaction fields, PayTabs `signature` HMAC-SHA256 of the raw body with the server key, Fawry notification V2 SHA-256 `messageSignature`); idempotent processing; server-side verification before marking paid.
- Payment credentials: `settings.manage` plus a recently confirmed password to view or change Settings → Payments; changes audited without secret values and clear the gateway's connection verification.
- Refunds: four-eyes (requester can never approve), amount capped under a row lock, credit note per refund; CSV exports are permission-checked, audited and formula-injection safe.
- n8n: HMAC-SHA256 signed payloads both ways with timestamp + nonce.
- Credentials encrypted at rest (`encrypted` cast, `APP_KEY` rotation procedure documented); never logged, never returned to UI after save (masked), never in Git.

## 7. Rate limiting
- Named limiters: login, password reset, contact/quote forms, chatbot (per IP + per conversation), API, MCP (per token), webhooks.
- Honeypot + time-to-submit checks on public forms (no CAPTCHA-bypass concerns since we own the forms).

## 8. Chatbot & AI safety
- System prompt server-side only; model has no tools that read CRM/customer data.
- Inputs length-limited and validated; outputs rendered as text (escaped).
- No pricing/delivery promises; estimates labeled "preliminary".
- Prompt-injection: untrusted content (chat, radar descriptions, articles) is always passed as data, never merged into system instructions; AI output can only create drafts/change requests.
- All provider calls go through `App\Services\Ai\AiGateway`: per-feature kill switch (`ai_assistant` for the public assistant, `ai_automation` for Content Studio/Radar), monthly budget hard stop, and `ai_usage_logs` rows that never contain prompts or responses (errors are short codes such as "HTTP 503").
- Public assistant: conversations are bound to the browser by a random token (only its hash is stored), IPs are stored as HMAC hashes, messages are length-limited and capped per conversation, rate limited per conversation and per IP; model structured output is re-validated against the quote-form enums and stripped of HTML before storage, and the browser renders it with `textContent`. Contact details are confirmed by the visitor in a form before a lead is created.

## 9. Logging, audit & privacy
- `audit_logs` append-only; sensitive fields redacted.
- Data retention: chat messages, MCP tool calls, login attempts pruned per configured windows.
- Consent records, data export & deletion requests workflow; cookie consent for non-essential analytics.

## 10. Operations
- Nightly encrypted backups (DB dump, private storage, `.env`; AES-256 + PBKDF2 with a root-only key file or GnuPG
  public key), 7 daily / 4 weekly / 6 monthly, off-site copy, monthly automated restore drill (DEPLOYMENT_GUIDE §8).
- `/health` never returns hosts, credentials or exception messages; per-check details only for allow-listed IPs or
  `X-Health-Token` (constant-time compare). `app:check-production` prints setting names, never values.
- First Super Admin via `php artisan app:create-super-admin`: password from a hidden prompt only (no option/env, so
  nothing in shell history), breached-password policy applied, 2FA enforced at first sign-in, audited.
- Nginx adds HSTS, denies dotfiles and everything under `/storage` except media variants, and rate-limits credential
  endpoints, webhooks and the MCP API ahead of the app's own limits; PHP-FPM disables shell functions.
- Dependency audit (`composer audit`, `npm audit`) in CI.
- Vulnerability reports: security@codefanz.com (to be confirmed by owner).

## 11. Security review — M11 (slice 1)

Scope: OWASP Top 10 (2021) / ASVS L2 against every route (`php artisan route:list --except-vendor`: 422 app routes
plus Fortify), every admin/portal controller, models, uploads, Blade output, outbound HTTP, webhooks, config.
Tests live in `tests/Feature/Security/*` and `tests/Feature/PerformanceBudgetTest.php`.

### Findings and fixes

| # | Finding | Severity | Fix | Regression test |
|---|---------|----------|-----|-----------------|
| 1 | **SSRF** — the SEO audit's external link check sent HEAD requests to any link in published content (incl. AI/MCP-proposed content) and followed redirects, so `http://169.254.169.254/…` or intranet hosts could be probed; n8n outbound workflow URLs were only syntax-checked, followed redirects and the stored response body was shown in the run log (full-read SSRF for `automation.manage`). | High | `App\Services\Security\OutboundUrlGuard`: http(s) only, every resolved A/AAAA record must be public (blocks loopback, RFC 1918, link-local/metadata, CGNAT, reserved, IPv4-mapped IPv6), connection pinned to the vetted IP (`CURLOPT_RESOLVE`), redirects re-checked per hop. Applied to the link check (unsafe links are reported unreachable, never requested), to workflow validation (`The webhook URL must point to a public host.`) and to every delivery (no redirects). `ALLOW_PRIVATE_OUTBOUND=true` only for local n8n. | `OutboundUrlGuardTest` (18 address cases, redirect hook, form validation, delivery, crawler) |
| 2 | **No CSP / browser hardening headers** (no CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS). | Medium | Global `SecurityHeaders` middleware: nonce-based CSP shared with Vite (`Vite::useCspNonce()`, `@vite`/`@fonts` and the three inline scripts carry it; TipTap gets it via `<meta name="csp-nonce">` → `injectNonce`), `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'none'`, frames only youtube-nocookie / player.vimeo, GA hosts only when a GA4 id is configured, Vite dev-server origin only while `npm run dev` runs; `style-src-attr 'unsafe-inline'` (style attributes cannot carry nonces). HSTS + `upgrade-insecure-requests` on HTTPS. CSP skipped on non-HTML and on debug exception pages; `CSP_REPORT_ONLY` switch. Verified in the browser on /en, /ar, portfolio, blog, quote, assistant, contact and login: no violations; an injected inline script is blocked. | `SecurityHeadersTest` |
| 3 | **Password policy below policy** — `Password::default()` without `Password::defaults()` meant 8 characters and no breach check (policy: 12 + uncompromised). | Medium | `Password::defaults()`: min 12, max 128, `uncompromised()` (HIBP k-anonymity) unless `CHECK_BREACHED_PASSWORDS=false` (test suite only). | `AuthenticationHardeningTest` |
| 4 | **Unthrottled credential endpoints** — Fortify throttles only login and the 2FA challenge; reset-link requests (mail flooding / enumeration timing), password reset, password confirmation (`/user/confirm-password`, brute force of a signed-in session's password), password change and 2FA confirmation had no limiter. | Medium | `ThrottleSensitiveAuthRoutes` (web group) applies the `auth-sensitive` limiter (5/min per route + IP) to those five Fortify routes. | `AuthenticationHardeningTest` |
| 5 | **2FA not enforced for approvers** — policy requires TOTP for "any user with approval permissions", but only Super Admin/Administrator/Finance Manager were checked; Content Manager, SEO Specialist, Social Media Manager (and anyone granted an approve permission directly) could approve AI/MCP changes with a password only. | Medium | `User::requiresTwoFactor()` also checks `Permission::twoFactorPermissions()` (content/seo/social approve + refund approve) through roles or direct grants. | `AuthenticationHardeningTest` |
| 6 | **Information disclosure on the admin overview** — every `admin.access` user saw pending change-request summaries and the latest audit-log operations, regardless of `approvals`/`audit.view` permissions. | Low | Dashboard loads and renders the queue only for `viewAny ChangeRequest` and the activity card only for `audit.view`. | `AuthenticationHardeningTest` |
| 7 | **Session defaults** — `SESSION_SECURE_COOKIE` defaulted to unset (cookie sent over http in production unless configured), session payload unencrypted by default. | Low | `secure` defaults to true when `APP_URL` is https; `encrypt` defaults to true (`.env.example` updated). `http_only`, `same_site=lax` unchanged. | `AuthenticationHardeningTest` (cookie flags) |
| 8 | **Private disk served over HTTP** — `local` disk had `serve => true`, registering signed `/storage/{path}` GET/PUT routes for private files; unused (downloads go through authorized controllers). | Low (defence in depth) | `serve => false`. | `RouteAuthorizationTest` |

### Reviewed — no change needed

- **Access control**: all 333 admin routes carry `auth`, `active`, `can:admin.access` and (except security/profile) `two-factor`, plus a permission or policy check — enforced by `RouteAuthorizationTest` (guests redirected and clients refused on every admin route, guests redirected on every portal route, tokenless and session-only requests 401 on every API route, and a static check that each admin action has `can:` middleware, a Gate/authorize call or a non-trivial Form Request `authorize()`; the 6 self-service routes are listed explicitly). Portal: every controller resolves records through `forCurrentCustomer()` by ulid/uuid (spot-checked all 12; isolation tests in `tests/Feature/Portal`). Nested admin routes use `scopeBindings()` or explicit membership checks.
- **Mass assignment**: no `$guarded = []`, no `$request->all()`; models use `#[Fillable]`, controllers pass `validated()`.
- **SQL injection**: all `*Raw` calls use static SQL or bindings; sorting is whitelisted (`Rule::in`).
- **XSS** (`{!! !!}`): article bodies (`HtmlSanitizer` allow-list, applied on write), page/service/career/product/portfolio/FAQ bodies (`Str::markdown` with `html_input=strip`, `allow_unsafe_links=false`), 2FA QR SVG (generated by Fortify), static SVG icons / `$newTab()` attribute helper / pagination icons / language-switch label (server constants), RSS XML prolog. JSON-LD uses `JSON_HEX_TAG|AMP|APOS|QUOT`. No inline event handlers.
- **Open redirects**: notification links keep only same-area paths; consent fallback keeps same-host paths; `redirect()->away()` only to gateway/OAuth URLs from config/provider responses; managed redirects are admin-authored. `back()` honours the Referer header on CSRF-protected POSTs only (informational).
- **CSRF**: no exemptions; webhook routes live outside the `web` group and verify HMAC signatures (Stripe, Paymob, PayTabs, Fawry, n8n with timestamp + delivery id).
- **Uploads**: MIME sniff + extension allow-list + size/dimension limits, private disk, random names, original names sanitised, downloads authorized with `nosniff` + `attachment`.
- **Secrets**: encrypted Settings, masked in the UI, MCP arguments redacted; Telegram bot token never logged (connection errors are logged without the URL).
- **Rate limiting**: login 5/min (email+IP), 2FA challenge 5/min, contact/quote/assistant/pay/webhooks/MCP named limiters, portal write routes throttled.
- **Dependencies** (2026-11-13): `composer audit` — no advisories; `npm audit --omit=dev` — 0 vulnerabilities.
- **APP_DEBUG**: must be `false` in production (docs/DEPLOYMENT_GUIDE.md §2); local `.env` keeps `true`.

### Open items

- CSP `form-action` allows `https:` (checkout and social OAuth forms are redirected to provider hosts); tighten to the configured gateway hosts once production gateways are fixed.
- The guard pins the first hop only; redirect hops are re-validated but resolved again by cURL (residual DNS-rebinding window on redirects of the opt-in external link check).
- Admin TipTap editor and chart.js pages were verified by tests (nonce exposure, `style-src-attr`) but not clicked through in a browser: the dev database has no staff account.
