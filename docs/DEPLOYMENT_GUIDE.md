# Deployment Guide (Linux VPS)

> Skeleton — completed in Milestone 12. Production deploys and destructive migrations require explicit owner approval.

## 1. Target stack
Ubuntu LTS · Nginx · PHP 8.3-FPM (ext: imagick with AVIF support, intl, redis, pdo_mysql, gd) · MySQL 8 ·
Redis · Supervisor (queue workers) · cron (scheduler) · Certbot (Let's Encrypt SSL) · Node 20 (build only).

## 2. Environment
- `.env` lives only on the server; template in `.env.example` (no secrets).
- Production: `APP_ENV=production`, `APP_DEBUG=false` (never true on a public host: debug pages expose
  configuration, queries and stack traces), `SESSION_SECURE_COOKIE=true` (defaults to true when `APP_URL` is https),
  `SESSION_ENCRYPT=true`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis` (the database cache store costs one query
  per distinct key per request), `CHECK_BREACHED_PASSWORDS=true`, `ALLOW_PRIVATE_OUTBOUND=false`.
- Optional: `CSP_REPORT_ONLY=true` for a first deploy behind a new CDN, `CSP_IMG_SRC` / `CSP_CONNECT_SRC`
  (comma-separated origins) when media is served from another host.
- Integration credentials are entered via Admin → Settings and stored encrypted in DB.

## 3. Deploy steps (zero-downtime, release directories)
1. `git fetch` + checkout tag into `releases/<timestamp>`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. Link shared `storage/` and `.env`
5. `php artisan migrate --force` (**review migration plan first; destructive changes need approval + backup**),
   then `php artisan db:seed --class=RolesAndPermissionsSeeder --force` whenever `App\Enums\Permission`/`Role` change
   (idempotent; e.g. M5 slice 2 added `customers.view`/`customers.manage`)
6. `php artisan optimize` (config/route/view/event cache)
7. Atomic symlink switch `current → releases/<timestamp>`, reload PHP-FPM
8. `php artisan queue:restart`
9. Health check `GET /health` → rollback symlink on failure

## 4. Process configuration (to be provided as files in M12)
- Supervisor programs: `codefanz-queue-payments`, `codefanz-queue-default`, `codefanz-queue-media`, `codefanz-queue-ai`, `codefanz-queue-social`, `codefanz-queue-radar`.
- Social publishing (M8.2): the `social` queue worker runs `PublishSocialPost`, token refresh and metrics jobs; the scheduler runs `social:publish-due` (every minute), `social:refresh-tokens` (15 min) and `social:fetch-metrics` (6 h). Register each OAuth app's redirect URI shown on Admin → Social → Accounts (`/admin/social/oauth/{linkedin|meta|x}/callback`). Meta downloads images from signed `/social-media/{uuid}` URLs, so `APP_URL` must be the public HTTPS host.
- Cron: `* * * * * cd /var/www/codefanz/current && php artisan schedule:run >> /dev/null 2>&1`
- Nginx server block: HTTP→HTTPS redirect, gzip/brotli, long cache for `/build/*` assets, `client_max_body_size` aligned with upload limits.
  Security headers (CSP with per-request nonce, HSTS on HTTPS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`,
  `Permissions-Policy`) are sent by the app's `SecurityHeaders` middleware — do not add a second CSP in Nginx.
  Static asset caching and compression (M11):

  ```nginx
  location /build/assets/ {                 # content-hashed Vite output
      add_header Cache-Control "public, max-age=31536000, immutable";
      access_log off;
      try_files $uri =404;
  }
  location /storage/media/ {                # optimized image variants (uuid file names)
      add_header Cache-Control "public, max-age=2592000";
      try_files $uri =404;
  }
  gzip on; gzip_comp_level 5; gzip_min_length 1024; gzip_vary on;
  gzip_types text/plain text/css text/xml application/xml application/rss+xml application/javascript application/json image/svg+xml;
  # With ngx_brotli: brotli on; brotli_comp_level 5; brotli_types <same list>;
  ```
  WOFF2 fonts, AVIF/WebP images are already compressed — do not gzip them. The local Apache vhost
  (`deploy/local/codefanz.loc.conf`) sets the same `/build` cache header and `mod_deflate`.

## 5. Backups & restore
- Nightly `mysqldump --single-transaction` + private storage archive, encrypted, kept 30 days, synced off-site.
- Monthly restore drill into a staging database.

## 6. Monitoring
- `/health` (DB, Redis, queue lag, disk, failed jobs); uptime monitor.
- Log rotation (`logrotate` daily, 14 days); failed jobs + payment webhook failures alert via Telegram.

## 7. Production checklist
See Phase 20 of the master prompt; tracked in `IMPLEMENTATION_ROADMAP.md` at M12.

## 8. Local demo data

`php artisan db:seed --class=DemoContentSeeder` (never in production) adds sample content, sample leads and a sample
customer with one portal user, `client.demo@example.test`. Its password is random on purpose: to sign in locally,
open `/forgot-password`, request a link for that address and open it from the local mail log
(`MAIL_MAILER=log` → `storage/logs/laravel.log`) to choose a password, then sign in — you land on `/portal`.

## 9. Local development

**QA sign-in accounts.** `Database\Seeders\LocalDevAccountsSeeder` (not called by `DatabaseSeeder`; it throws unless
`APP_ENV=local`) creates or updates a Super Admin `admin@codefanz.test` and a Content Manager `editor@codefanz.test` (both
hold approval permissions, so both get confirmed 2FA with the same local secret), and sets a known password on the demo portal client `client.demo@example.test` (run
`DemoContentSeeder` first). Passwords and the TOTP secret are read from your own `.env` only:

1. Add `DEV_ADMIN_PASSWORD`, `DEV_EDITOR_PASSWORD`, `DEV_CLIENT_PASSWORD` (strong, random, 12+ characters) and
   `DEV_ADMIN_2FA_SECRET` (base32, e.g. `php -r 'require "vendor/autoload.php"; echo (new PragmaRX\Google2FA\Google2FA)->generateSecretKey(32), PHP_EOL;'`).
   `.env.example` lists the names with empty values — never commit real values or set them outside local.
2. `php artisan config:clear && php artisan db:seed --class=LocalDevAccountsSeeder`.
3. Sign in at `/login`; for the admin, add the secret to an authenticator app or compute the current code with
   `php artisan tinker --execute 'echo (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp(config("codefanz.local_dev_accounts.admin_two_factor_secret"));'`.

Re-running the seeder resets the three passwords to the `.env` values and replaces the admin's recovery codes.
