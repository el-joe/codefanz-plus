# Deployment Guide (Linux VPS)

> Completed in Milestone 12. Everything here is **preparation**: nothing is deployed automatically. Production deploys,
> destructive migrations and restores into the live database need explicit owner approval (master prompt, Phase 20).

All production files live in `deploy/production/` as templates with `{{PLACEHOLDERS}}` and no secrets:

| File | Installs to | Purpose |
|---|---|---|
| `nginx/codefanz-http.conf` | `/etc/nginx/conf.d/codefanz-http.conf` | rate-limit zones, gzip/brotli, `server_tokens off` |
| `nginx/codefanz-site.conf` | `/etc/nginx/sites-available/codefanz` | HTTP→HTTPS, alt host → canonical host, HTTP/2, caching, PHP-FPM |
| `nginx/codefanz-tls.conf` | `/etc/nginx/snippets/codefanz-tls.conf` | Mozilla *intermediate* TLS |
| `apache/codefanz-ssl.conf` | `/etc/apache2/sites-available/` | Apache alternative (appendix, §5.4) |
| `php/fpm-pool-codefanz.conf` | `/etc/php/8.3/fpm/pool.d/codefanz.conf` | FPM pool sizing, upload limits, hardening |
| `php/99-codefanz.ini` | `/etc/php/8.3/{fpm,cli}/conf.d/` | OPcache, `expose_php=Off`, limits |
| `supervisor/codefanz-workers.conf` | `/etc/supervisor/conf.d/` | queue workers `default`, `media`, `social` (+ optional MCP HTTP) |
| `cron/codefanz` | `/etc/cron.d/codefanz` | scheduler every minute, nightly backup, monthly restore drill |
| `logrotate/codefanz` | `/etc/logrotate.d/codefanz` | Nginx, FPM and backup logs |
| `deploy.sh` + `deploy.conf.example` | repo / `/etc/codefanz/deploy.conf` | zero-downtime deploy, rollback, status |
| `backup/*.sh` + `backup.conf.example` | repo / `/etc/codefanz/backup.conf` | encrypted backup, restore, restore drill |
| `.env.production.example` | `{{APP_PATH}}/shared/.env` | every environment variable with production values |
| `render.sh` | — | fills the placeholders: `render.sh vars.env /tmp/codefanz-rendered` |

Placeholders: `{{DOMAIN}}` (canonical host, same as `APP_URL`), `{{ALT_DOMAIN}}` (redirects to it — swap the two to
make `www` canonical), `{{APP_PATH}}` (e.g. `/var/www/codefanz`), `{{APP_USER}}` (e.g. `codefanz`), `{{PHP_FPM_SOCK}}`
(e.g. `/run/php/php8.3-fpm-codefanz.sock`), `{{SSL_CERT}}` / `{{SSL_KEY}}`, `{{DB_NAME}}`, `{{GIT_REPOSITORY}}`.
`render.sh` refuses to finish while any placeholder is left without a value.

## 1. Server requirements

- **OS:** Ubuntu 24.04 LTS, 2–4 GB RAM, 2 vCPU, 40 GB+ SSD (media variants and backups grow with use). Swap 1–2 GB.
- **PHP 8.3** (FPM + CLI) with extensions: `imagick` **built against an ImageMagick with AVIF support** (check:
  `php -r 'var_dump(Imagick::queryFormats("AVIF"));'` must list AVIF; Ubuntu 24.04's `php8.3-imagick` +
  `libheif` provide it), `intl`, `bcmath`, `gd`, `pdo_mysql`, `mbstring`, `openssl`, `zip`, `fileinfo`, `curl`, `xml`,
  `dom`, `tokenizer`, `ctype`, `sodium`, `opcache`; `redis` only if Redis is used (phpredis — `predis/predis` is **not**
  installed and must not be added). Without AVIF the media pipeline still produces WebP (`app:check-production` warns).
- **MySQL 8.0+**, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` (the app's collation). Suggested `mysqld.cnf`:
  `character-set-server=utf8mb4`, `collation-server=utf8mb4_unicode_ci`, `innodb_buffer_pool_size=512M` (2 GB VPS) /
  `1G` (4 GB), `max_connections=100`, `sql_mode` left at the MySQL 8 default (strict), `default_time_zone='+00:00'`,
  binary logs on with `binlog_expire_logs_seconds=604800` if you want point-in-time recovery.
- **Node.js 20 LTS + npm** on the server (or the build host) for `npm ci && npm run build` and the optional MCP server
  (`mcp-server/` requires Node ≥ 20). Never yarn/pnpm.
- **Composer 2**, **Nginx** (1.24+, `http2`), **Supervisor**, **cron**, **Certbot**, `git`, `curl`, `openssl`, `gzip`,
  `logrotate`. **Redis 7** optional (recommended for cache/sessions/queue at higher traffic; the database drivers work).
- Time zone: server and app in **UTC** (`config/app.php`); scheduled times below are UTC.

## 2. First-time setup (owner runs these; nothing here is automated)

1. **System user and layout**
   ```bash
   adduser --disabled-password codefanz
   mkdir -p /var/www/codefanz/{releases,shared/storage} /etc/codefanz /var/www/letsencrypt
   chown -R codefanz: /var/www/codefanz
   ```
   Give `codefanz` a read-only deploy key for the Git repository and one sudo rule file
   (`visudo -f /etc/sudoers.d/codefanz`):
   `codefanz ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm, /var/www/codefanz/current/deploy/production/backup/backup.sh`
2. **MySQL**: `CREATE DATABASE codefanz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;` an app user with all
   privileges on that schema only, and a backup user with `SELECT, SHOW VIEW, TRIGGER, LOCK TABLES` (see
   `backup/backup.conf.example`).
3. **Render the templates**: write `vars.env` (see `render.sh` header), run
   `deploy/production/render.sh vars.env /root/codefanz-rendered`, review, then copy each file to the path in the table.
4. **TLS**: with the port-80 server block installed, `certbot certonly --webroot -w /var/www/letsencrypt -d {{DOMAIN}}
   -d {{ALT_DOMAIN}}`; create `/etc/nginx/dhparam.pem` (command in `codefanz-tls.conf`); `nginx -t && systemctl reload
   nginx`. Certbot's timer renews; add `--deploy-hook "systemctl reload nginx"`.
5. **Environment**: copy `.env.production.example` to `/var/www/codefanz/shared/.env`, fill every `CHANGE`,
   `chmod 640`, owner `codefanz`. Generate `APP_KEY` once (`php artisan key:generate --show` from any checkout) and
   store a copy in the owner's password manager — without it encrypted settings and backups' `.env` are useless.
6. **First deploy**: `/etc/codefanz/deploy.conf` from `deploy.conf.example`, then as `codefanz`:
   `deploy.sh deploy v1.0.0 --approve-migrations --skip-backup --allow-check-failures` (empty database: nothing to back
   up; the production check cannot pass yet because no Super Admin exists and the scheduler has not run).
7. **Processes**: `supervisorctl reread && supervisorctl update`; install the cron file; `systemctl reload php8.3-fpm`.
8. **Owner account**: `cd /var/www/codefanz/current && php artisan app:create-super-admin` — name and e-mail as options
   or prompts, password **only** at the hidden prompt (twice; min. 12 characters, checked against known breaches).
   Sign in: two-factor setup is enforced before the admin area opens.
9. **Admin → Settings**: General/Contact (both languages), SEO → *Search engine indexing* = **Allow**, mail sender,
   integrations (see QA_CHECKLIST §5). Then `php artisan app:check-production` must pass (§9.2).

## 3. Environment

`deploy/production/.env.production.example` lists **every** variable the code reads (a unit test keeps it complete),
with production values and comments. Essentials: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`,
`SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, real SMTP, `QUEUE_CONNECTION=database|redis` (never `sync`),
`DB_QUEUE_RETRY_AFTER=1900` / `REDIS_QUEUE_RETRY_AFTER=1900` (must exceed the 1800 s SEO audit job, otherwise a second
worker starts it again), `LOG_STACK=daily`, `LOG_LEVEL=warning`, `HEALTH_TOKEN`, `CHECK_BREACHED_PASSWORDS=true`,
`ALLOW_PRIVATE_OUTBOUND=false`. Variables listed as `# KEY=` stay unset on purpose (an empty value would replace a
framework default with an empty string).

- **Redis**: `apt install redis-server php8.3-redis`, `bind 127.0.0.1`, `requirepass`, then `CACHE_STORE=redis`,
  `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `REDIS_PASSWORD=…`. Switching the queue driver: stop workers,
  let the old queue drain (`php artisan queue:work --stop-when-empty`), switch, `optimize`, start workers.
- **Search indexing** is a database setting, not an env var: unset = allowed only when `APP_ENV=production`; staging
  must keep it on *Block* (robots.txt `Disallow: /` + `X-Robots-Tag: noindex`). Go-live sets *Allow* explicitly.
- **CSP**: enforced by default. `CSP_REPORT_ONLY=true` only for a first deploy behind a new CDN; `CSP_IMG_SRC` /
  `CSP_CONNECT_SRC` add origins when media or APIs move to another host.
- Integration credentials (payments, AI, social, Telegram, Google, n8n) are entered in Admin → Settings and stored
  encrypted with `APP_KEY`; they are never env vars.

## 4. Deploying — `deploy/production/deploy.sh`

**Strategy: release directories with an atomic symlink switch** (`releases/<UTC stamp>`, `shared/{.env,storage}`,
`current → releases/…`). Chosen over in-place + maintenance mode because composer, npm and `optimize` run while the
old release keeps serving, the switch is a single `rename()`, and rollback is the same operation backwards. Nginx uses
`$realpath_root`, and PHP-FPM is reloaded after the switch (OPcache runs with `validate_timestamps=0`).
`--maintenance` adds `php artisan down` around migrations whose schema is not compatible with the running code.

`deploy.sh deploy <tag>` (run as `codefanz`, never root):

1. lock (`flock`), check `shared/.env`, create shared storage directories;
2. fetch the bare mirror `repo.git`, export the tag with `git archive` (no `.git` in releases), write `REVISION`;
3. link `storage/` and `.env` from `shared/`;
4. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build` (node_modules removed afterwards),
   `mcp-server` build when `MCP_BUILD=1` (`npm ci && npm run build && npm prune --omit=dev`), `php artisan storage:link`;
5. **migrations**: pending ones are printed with `migrate --pretend`; they run only with `--approve-migrations` or an
   interactive `yes`, after a pre-deploy backup (`backup.sh --label pre-deploy`); `migrate --force`;
6. `php artisan db:seed --force` — `DatabaseSeeder` only seeds system data and is **idempotent and safe on production**
   (tested in `tests/Feature/Operations/ProductionSeedersTest.php`): languages (missing rows created; active flag, default
   and order kept — fixed in M12, it used to re-activate a disabled language), roles/permissions (the code-defined
   matrix is re-applied; the admin matrix is read-only), kill switches (`firstOrCreate`, never reset), AI studio brand
   profile + prompt templates (created once, edits kept), radar platforms (connector facts follow code; switch, cadence
   and limits kept), page sections (missing ones added), menus (one per location). Demo and local-account seeders are
   never called;
7. `php artisan optimize` (config, routes, views, events), `php artisan app:check-production` (aborts unless
   `--allow-check-failures`);
8. atomic switch, `systemctl reload php8.3-fpm`, `php artisan queue:restart` (workers finish their job and restart
   from the new release);
9. health check `https://{{DOMAIN}}/health` against `127.0.0.1` (9 tries × 10 s). On failure the code is switched back
   automatically;
10. keep the newest `KEEP_RELEASES` (5).

`deploy.sh rollback` switches back to the previous release (reload FPM, restart workers, health check).
**Migrations are never rolled back automatically**: if a release added columns, the old code normally keeps working;
`php artisan migrate:rollback --step=N` is a data-loss decision for the owner, after a backup. `deploy.sh status`
lists releases and the active one. The script passes `bash -n`, uses `set -Eeuo pipefail` and quoted expansions.

**Rehearsal (local sandbox, 2026-11-13):** against a throwaway database and a scratch `APP_PATH`, a first deploy without
`--approve-migrations` stopped before touching the schema (status unreadable on an empty database is treated as
"everything pending"); with approval it printed the SQL of 45 migrations, migrated, seeded, optimized and switched; a
second deploy reported no pending migrations and pruned releases beyond `KEEP_RELEASES`; `rollback` and `status`
switched and listed correctly. Health check and FPM reload could not be exercised locally (no TLS / FPM) and were
skipped or warned as designed. Nginx/Apache configs were reviewed but not run through `nginx -t` (not installed here) —
do that on the server.

## 5. Web server

### 5.1 Nginx (`nginx/`)
- Port 80 serves only ACME challenges and 301s everything to `https://{{DOMAIN}}`; `{{ALT_DOMAIN}}` on 443 301s to the
  canonical host. HTTP/2 (`listen 443 ssl http2`; Nginx ≥ 1.25.1: `http2 on;`).
- TLS: Mozilla intermediate (TLS 1.2/1.3, ECDHE + CHACHA20 suites, ffdhe2048, no session tickets). No OCSP stapling
  (Let's Encrypt dropped OCSP in 2025). HSTS `max-age=31536000; includeSubDomains` on every response — Nginx owns the
  header (the app's copy is hidden) so static files get it too. Submit to the HSTS preload list only after every
  subdomain is on HTTPS (then add `preload`).
- Security headers (CSP with per-request nonce, `X-Frame-Options`, `nosniff`, `Referrer-Policy`,
  `Permissions-Policy`, COOP) come from the app's `SecurityHeaders` middleware — do not add a second CSP in Nginx.
- Caching: `/build/` (content-hashed Vite output) `max-age=31536000, immutable`; `/storage/media/` (uuid-named AVIF/WebP
  variants) 30 days. Gzip level 5 for text types; brotli block ready for `ngx_brotli`. WOFF2/AVIF/WebP are never
  recompressed.
- `client_max_body_size 64m` = Request a Quote's 5 attachments × 10 MB + fields (PHP: `upload_max_filesize 12M`,
  `post_max_size 64M`).
- Denied: dotfiles (except `/.well-known/`), backup/dump/log/script extensions, everything under `/storage/` except
  `media/`, any `.php` other than `/index.php`. Private files (`storage/app/private`: originals, lead attachments, project
  documents) are outside the web root and streamed only by authorised controllers.
- PHP-FPM: `SCRIPT_FILENAME $realpath_root…`, `HTTP_PROXY ""` (httpoxy), connect 5 s / read 60 s (AI calls time out at
  30 s; long work is queued), FPM `request_terminate_timeout 75s`.
- **Trailing slash**: GET/HEAD `/en/` → 301 `/en` (query kept), the same as `public/.htaccess`. The app also has a
  fallback middleware (`App\Http\Middleware\RedirectTrailingSlash`, tested) so `php artisan serve` and any other server
  behave the same — closes the M11 open item.
- **Rate limits (defence in depth)**: `cf_login` 10 r/min burst 10 for POSTs to login / 2FA / password reset /
  password confirmation, `cf_webhook` 300 r/min burst 60 for `/webhooks/*`, `cf_mcp` 120 r/min burst 30 for
  `/api/v1/mcp`; 429 on excess. The application's own limits (Fortify `login`/`two-factor`, `auth-sensitive`,
  `webhooks`, MCP per token) stay authoritative and are stricter.

### 5.2 Real client IP behind a CDN / load balancer
Pick **one** of:
- **Nginx terminates TLS and a CDN is in front** (e.g. Cloudflare): uncomment `set_real_ip_from` (one line per CDN
  range) + `real_ip_header CF-Connecting-IP` (or `X-Forwarded-For` + `real_ip_recursive on`). `REMOTE_ADDR` is then the
  visitor; leave `TRUSTED_PROXIES` empty.
- **A load balancer terminates TLS and talks HTTP to Nginx**: set `TRUSTED_PROXIES=<LB IPs/CIDRs>` (or `*` only if the
  origin is firewalled to the LB) and pass `X-Forwarded-For`/`-Proto`. `AppServiceProvider` calls
  `TrustProxies::at()` so `request()->ip()`, `isSecure()`, rate limits, audit IPs and HSTS see the real values.
Never trust `X-Forwarded-*` from the open internet: rate limits and audit logs would be spoofable.

### 5.3 Firewall
`ufw allow 22,80,443/tcp`; MySQL and Redis listen on 127.0.0.1 only; the optional MCP HTTP server binds 127.0.0.1:3333
and is reached only through the commented `/mcp` Nginx location.

### 5.4 Apache alternative
`apache/codefanz-ssl.conf`: same redirects, TLS, HSTS, caching, denies and PHP-FPM handler; `public/.htaccess`
already does the trailing-slash redirect. Enable `ssl http2 headers rewrite proxy_fcgi deflate remoteip`.

## 6. PHP-FPM and OPcache (`php/`)
Dedicated pool `codefanz` on a unix socket; `pm = dynamic`, `max_children` 10 (2 GB) – 24 (4 GB) with the sizing rule
in the file, `max_requests 500`, slow log at 10 s, status/ping paths. `expose_php=Off`, `display_errors=Off`,
`memory_limit 256M`, uploads 12M/64M, shell functions disabled for the web pool (the app never shells out),
`open_basedir` prepared as an option. OPcache 192 MB, 20 000 files, `validate_timestamps=0` (reload on deploy), JIT off.

## 7. Queue workers and scheduler

### 7.1 Supervisor (`supervisor/codefanz-workers.conf`)

| Program | Queue | Jobs | Procs | Worker flags | `stopwaitsecs` |
|---|---|---|---|---|---|
| `codefanz-queue-default` | `default` | `DeliverAutomationWebhook` (60 s), `SendAnalyticsEvent` (15 s), `ScoreRadarProjectWithAi`, `SendRadarAlert`, `RunSeoAudit` (1800 s) | 2 | `--tries=3 --backoff=10 --timeout=120 --max-time=3600 --memory=256` | 1860 |
| `codefanz-queue-media` | `media` | `OptimizeImage` (180 s, AVIF/WebP) | 1 | `--timeout=180 --max-jobs=200 --memory=640`, PHP 768M | 240 |
| `codefanz-queue-social` | `social` | `PublishSocialPost` (120 s), `RefreshSocialAccountToken`, `FetchSocialMetrics` (900 s) | 1 | `--tries=3 --timeout=120 --max-time=3600` | 960 |

Jobs that declare `$tries` / `$timeout` / `backoff` override the worker flags. Workers use `QUEUE_CONNECTION`.
`stopwaitsecs` exceeds the longest job on each queue so restarts never cut a job; `retry_after` 1900 s exceeds them all.
Deploys call `php artisan queue:restart`. Failed jobs: `php artisan queue:failed`, `queue:retry <id>`; payment and
social failures also surface in the admin. Mail notifications are sent during the request (not queued).

### 7.2 Cron and scheduled commands
`* * * * * codefanz cd {{APP_PATH}}/current && php artisan schedule:run` (`cron/codefanz`, which also runs the nightly
backup at 02:15 and the restore drill on the 1st at 04:30). Scheduled in `routes/console.php` (UTC):

| Command | Cadence | Purpose |
|---|---|---|
| `scheduler-heartbeat` (closure) | every minute | writes the heartbeat read by `/health` and `app:check-production` |
| `posts:publish-scheduled` | every minute, no overlap | publish approved articles whose time has come |
| `social:publish-due` | every minute, no overlap | queue approved social posts that are due (`social` queue) |
| `radar:fetch` | every 5 min, no overlap | Freelance Radar API platforms (each platform's own cadence/budget applies) |
| `social:refresh-tokens` | every 15 min, no overlap | refresh OAuth tokens before expiry |
| `payments:expire-links` | hourly | expire payment links past their date |
| `social:fetch-metrics` | every 6 h, no overlap | metrics for API-published posts |
| `model:prune` | daily 00:00 | prune assistant conversations and other `Prunable` models past retention |
| `tickets:close-resolved` | daily 02:30 | close tickets resolved for `SUPPORT_AUTO_CLOSE_DAYS` |
| `payments:reconcile` | daily 03:10 | re-derive milestone paid amounts from the ledger |
| `analytics:sync` | daily 06:00, no overlap | GA4 / Search Console snapshot |
| `radar:digest` | daily `RADAR_DIGEST_TIME` (08:00) | Telegram digest |
| `seo:audit` | Mondays 05:00 | queued SEO crawl (`RunSeoAudit`) |

Check with `php artisan schedule:list`. One server only: if a second app server is ever added, run cron on one of them.

## 8. Backups and restore (`backup/`)

### 8.1 What, when, where
`backup.sh` (root, 02:15 nightly) writes `BACKUP_ROOT/daily/<stamp>/`:
`db.sql.gz.enc` (`mysqldump --single-transaction --quick --triggers --hex-blob --no-tablespaces`, consistent InnoDB
snapshot without locks), `files.tar.gz.enc` (`storage/app/private` — media originals, lead attachments, project
documents — plus `storage/app/public` media variants), `env.enc` (the `.env`, including `APP_KEY`), `SHA256SUMS`.
Each artefact is encrypted with `openssl enc -aes-256-cbc -pbkdf2 -iter 200000` using `/etc/codefanz/backup.key`
(root, mode 400 — keep a copy in the owner's password manager), or with GnuPG to a public key when
`BACKUP_GPG_RECIPIENT` is set (the server then cannot decrypt; restores happen where the private key is). The dump is
verified after writing (decrypts, gunzips, ends with `Dump completed`); an incomplete backup is deleted.

Retention: **7 daily, 4 weekly** (Sunday copies, hard-linked), **6 monthly** (1st of the month); pre-deploy and drill
backups in `adhoc/` (newest 5). **Off-site**: `BACKUP_OFFSITE_CMD`, e.g. `rclone sync "$BACKUP_ROOT"
offsite:codefanz-backups` (object storage with versioning/object lock) or `rsync` to another host — data is already
encrypted before it leaves the server.

### 8.2 Restore procedure (`restore.sh`, tested)
Every restore verifies `SHA256SUMS` first and refuses tampered files.
```bash
B=/var/backups/codefanz/daily/2026-11-13_021500
restore.sh db    "$B" codefanz_restore        # into a NEW database (refuses the live name without --force)
restore.sh files "$B" /tmp/codefanz-files     # app/private (+ app/public)
restore.sh env   "$B" /root/restored.env
```
**Full disaster recovery** (new server): §1–§2 setup → restore `.env` (same `APP_KEY`!) to `shared/.env` → `restore.sh
db "$B" codefanz --force` → `restore.sh files "$B" /var/www/codefanz/shared/storage` + `chown -R codefanz:` → deploy the
same tag → `app:check-production` → `/health`. Restoring over the **live** database: owner approval, `php artisan
down`, a fresh ad-hoc backup first, `--force`, `php artisan up`. Expected RPO 24 h (less with MySQL binlogs), RTO ≈ 1 h.

### 8.3 Restore drill
`restore-drill.sh` takes a fresh backup, restores it into `<DB_NAME>_restore_drill`, compares the exact row count of
every table with the live database (cache/sessions/jobs may move), counts the files in the archive against the
storage directories, checks the `.env` decrypts with an `APP_KEY`, and drops the drill database (also on failure). Cron
runs it monthly; non-zero exit = backups are not trustworthy.

**Drill result (local, 2026-11-13, MySQL 8.0.46):** `codefanz_plus` → encrypted backup (92 KB) → restored into
`codefanz_plus_restore_drill` in 10 s → **129 tables, 941 rows, all row counts identical**; files archived 16 = live 16;
`.env` decrypted with `APP_KEY`; drill database dropped → **PASSED**. Also verified: restore into the live name is
refused without `--force`, a modified artefact is rejected by the checksum check, the files archive restores, daily
retention prunes older runs.

## 9. Health, monitoring, logs and alerts

### 9.1 `/health`
`GET /health` (no session, `Cache-Control: no-store`) runs: database (`select 1` + latency), cache (write/read/delete),
queue (oldest waiting job per queue on the database driver, sizes on Redis; failed jobs in 24 h as information),
scheduler heartbeat (≤ 300 s), free disk on the storage volume (≥ `HEALTH_MIN_FREE_DISK_MB`). Answers **200
`{"status":"ok"}`** or **503 `{"status":"fail"}`**. Per-check details (`checks`, `checked_at`) only for
`HEALTH_ALLOWED_IPS` (CIDR) or a matching `X-Health-Token` header; no hosts, credentials or exception messages ever.
Laravel's bare `/up` (framework boots) remains for load balancers. Tests: `tests/Feature/Operations/HealthEndpointTest.php`.

### 9.2 `php artisan app:check-production`
Fails (exit 1) on: `APP_ENV` ≠ production, `APP_DEBUG=true`, missing `APP_KEY`, non-https `APP_URL`, insecure session
cookie, `log`/`array` mailer, example sender or missing SMTP host, `sync` queue, `retry_after` ≤ longest job timeout,
`array` cache, missing `public/storage` link, search indexing blocked, scheduler heartbeat missing or older than 5 min,
no active Super Admin, `ALLOW_PRIVATE_OUTBOUND=true`. Warns on: session encryption off, CSP disabled/report-only,
breached-password check off, `LOG_LEVEL=debug`, no `HEALTH_TOKEN`, Imagick without AVIF, config/routes not cached.
Prints setting names, never values. Run after every `.env` change; `deploy.sh` runs it before switching.

### 9.3 Uptime monitoring
External monitor (UptimeRobot, Better Stack, Uptime Kuma on another host…) every 1–5 min:
1. `https://{{DOMAIN}}/health` with header `X-Health-Token: <HEALTH_TOKEN>` — alert on non-200 or > 5 s;
2. `https://{{DOMAIN}}/en` keyword check (page renders); 3. TLS expiry (< 14 days); 4. optional
`https://{{DOMAIN}}/sitemap.xml`. Alert by e-mail + Telegram to the owner. Server metrics: disk, RAM, load (provider
graphs or `netdata`), `supervisorctl status`, FPM status page from localhost.

### 9.4 Logs and alerts
- App: `LOG_STACK=daily`, `LOG_DAILY_DAYS=14` → `storage/logs/laravel-YYYY-MM-DD.log`, pruned by Laravel (logrotate
  must not touch them). `LOG_LEVEL=warning`.
- **Error alerts**: add `slack` to the stack — `LOG_STACK=daily,slack`, `LOG_SLACK_WEBHOOK_URL=<incoming webhook>`;
  `LOG_SLACK_LEVEL=critical` (new in M12, independent of `LOG_LEVEL`) sends only critical/alert/emergency entries
  (unhandled exceptions are `error`; raise to `error` during the first weeks if you want every 500). Alternatives:
  a Slack-compatible endpoint (Mattermost, Discord `/slack`), or an n8n webhook relaying to Telegram/e-mail.
- Workers: Supervisor-rotated `storage/logs/worker-*.log` (10 MB × 5). Nginx/FPM/backup logs: `logrotate/codefanz`.
- Daily glance: `php artisan queue:failed`, Admin → Audit log, Admin → Approvals backlog, payment webhook statuses.

## 10. Local demo data

`php artisan db:seed --class=DemoContentSeeder` (never in production) adds sample content, sample leads and a sample
customer with one portal user, `client.demo@example.test`. Its password is random on purpose: to sign in locally,
open `/forgot-password`, request a link for that address and open it from the local mail log
(`MAIL_MAILER=log` → `storage/logs/laravel.log`) to choose a password, then sign in — you land on `/portal`.

## 11. Local development

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
