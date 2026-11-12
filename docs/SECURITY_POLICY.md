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
- Sanctum token abilities for API/MCP; AI/MCP actors can never hold finance, user-management or security abilities.

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
- Signature verification per gateway (Stripe signature, Paymob HMAC, PayTabs signature, Fawry hash); timestamp tolerance; idempotent processing; server-side verification before marking paid.
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

## 9. Logging, audit & privacy
- `audit_logs` append-only; sensitive fields redacted.
- Data retention: chat messages, MCP tool calls, login attempts pruned per configured windows.
- Consent records, data export & deletion requests workflow; cookie consent for non-essential analytics.

## 10. Operations
- Daily encrypted DB backups + off-site copy; monthly restore test.
- Dependency audit (`composer audit`, `npm audit`) in CI.
- Vulnerability reports: security@codefanz.com (to be confirmed by owner).
