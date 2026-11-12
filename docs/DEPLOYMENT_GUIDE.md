# Deployment Guide (Linux VPS)

> Skeleton — completed in Milestone 12. Production deploys and destructive migrations require explicit owner approval.

## 1. Target stack
Ubuntu LTS · Nginx · PHP 8.3-FPM (ext: imagick with AVIF support, intl, redis, pdo_mysql, gd) · MySQL 8 ·
Redis · Supervisor (queue workers) · cron (scheduler) · Certbot (Let's Encrypt SSL) · Node 20 (build only).

## 2. Environment
- `.env` lives only on the server; template in `.env.example` (no secrets).
- Production: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`.
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
- Cron: `* * * * * cd /var/www/codefanz/current && php artisan schedule:run >> /dev/null 2>&1`
- Nginx server block: HTTP→HTTPS redirect, HSTS, gzip/brotli, long cache for `/build/*` assets, `client_max_body_size` aligned with upload limits.

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
