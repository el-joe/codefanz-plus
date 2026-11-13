#!/usr/bin/env bash
# CodeFanz — zero-downtime deployment with release directories.
#
#   deploy.sh deploy <git-tag-or-commit> [--approve-migrations] [--maintenance] [--skip-backup]
#                                        [--allow-check-failures] [--skip-health]
#   deploy.sh rollback          # switch `current` back to the previous release (code only, see below)
#   deploy.sh status            # show releases and the active one
#
# Layout under APP_PATH:
#   repo.git/            bare mirror of the Git repository (fetched on every deploy)
#   releases/<stamp>/    one immutable directory per deploy (no .git inside)
#   shared/.env          production environment (never in Git; chmod 640, owner APP_USER)
#   shared/storage/      logs, private uploads, public media, sessions, compiled views
#   current -> releases/<stamp>   what Nginx, PHP-FPM, cron and Supervisor run
#
# Why release directories (not in-place + maintenance mode): the build (composer, npm, optimize) happens off to the
# side while the old release keeps serving; the switch is one atomic rename, and a rollback is the same rename
# backwards. Maintenance mode is still available (--maintenance) for releases whose migrations are not
# backward-compatible with the running code.
#
# Safety rules (master prompt, Phase 20): this script never runs against production by itself. Pending migrations
# are printed (--pretend) and need --approve-migrations or an interactive "yes"; a database backup is taken first.
# Rollback never reverts migrations automatically — `php artisan migrate:rollback` needs an explicit owner decision.
#
# Run as APP_USER (not root). APP_USER needs one sudo rule to reload PHP-FPM (OPcache has validate_timestamps=0):
#   {{APP_USER}} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
#
# Configuration: /etc/codefanz/deploy.conf (override with DEPLOY_CONFIG=...). See deploy.conf.example.

set -Eeuo pipefail
IFS=$'\n\t'

DEPLOY_CONFIG="${DEPLOY_CONFIG:-/etc/codefanz/deploy.conf}"
if [[ -r "$DEPLOY_CONFIG" ]]; then
    # shellcheck source=/dev/null
    source "$DEPLOY_CONFIG"
fi

# Defaults filled in by render.sh; deploy.conf or the environment override them.
DEFAULT_APP_PATH='{{APP_PATH}}'
DEFAULT_DOMAIN='{{DOMAIN}}'
APP_PATH="${APP_PATH:-$DEFAULT_APP_PATH}"
REPO_URL="${REPO_URL:-}"
DOMAIN="${DOMAIN:-$DEFAULT_DOMAIN}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
MCP_BUILD="${MCP_BUILD:-0}"
HEALTH_TOKEN_FILE="${HEALTH_TOKEN_FILE:-}"
HEALTH_ATTEMPTS="${HEALTH_ATTEMPTS:-9}"
BACKUP_SCRIPT="${BACKUP_SCRIPT:-}"

RELEASES_DIR="$APP_PATH/releases"
SHARED_DIR="$APP_PATH/shared"
CURRENT_LINK="$APP_PATH/current"
MIRROR_DIR="$APP_PATH/repo.git"

log() { printf '\033[1;34m[deploy %s]\033[0m %s\n' "$(date '+%H:%M:%S')" "$*"; }
warn() { printf '\033[1;33m[deploy warning]\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31m[deploy error]\033[0m %s\n' "$*" >&2; exit 1; }

usage() {
    sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'
    exit 2
}

require_commands() {
    local missing=()
    local cmd
    for cmd in "$@"; do
        command -v "$cmd" >/dev/null 2>&1 || missing+=("$cmd")
    done
    ((${#missing[@]} == 0)) || die "Missing commands: ${missing[*]}"
}

artisan() {
    (cd "$1" && "$PHP_BIN" artisan "${@:2}")
}

current_release() {
    [[ -L "$CURRENT_LINK" ]] && readlink -f "$CURRENT_LINK" || true
}

reload_php_fpm() {
    if sudo -n systemctl reload "$PHP_FPM_SERVICE"; then
        log "Reloaded $PHP_FPM_SERVICE (OPcache cleared)."
    else
        warn "Could not reload $PHP_FPM_SERVICE — run: sudo systemctl reload $PHP_FPM_SERVICE"
    fi
}

switch_to() {
    local target="$1"
    ln -sfn "$target" "$CURRENT_LINK.next"
    mv -Tf "$CURRENT_LINK.next" "$CURRENT_LINK"
    log "current -> $target"
    reload_php_fpm
    artisan "$CURRENT_LINK" queue:restart --no-interaction || warn "queue:restart failed"
}

health_check() {
    local url="https://$DOMAIN/health"
    local -a headers=()
    local attempt

    if [[ -n "$HEALTH_TOKEN_FILE" && -r "$HEALTH_TOKEN_FILE" ]]; then
        headers=(-H "X-Health-Token: $(<"$HEALTH_TOKEN_FILE")")
    fi

    for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
        # --resolve: test this server, not whatever the DNS / CDN points at.
        if curl -fsS --max-time 10 --resolve "$DOMAIN:443:127.0.0.1" "${headers[@]}" "$url" -o /dev/null; then
            log "Health check passed ($url)."
            return 0
        fi
        warn "Health check attempt $attempt/$HEALTH_ATTEMPTS failed; retrying in 10 s."
        sleep 10
    done

    return 1
}

prepare_shared() {
    local dir
    for dir in storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs; do
        mkdir -p "$SHARED_DIR/$dir"
    done
    [[ -f "$SHARED_DIR/.env" ]] || die "$SHARED_DIR/.env is missing. Copy deploy/production/.env.production.example, fill it in, chmod 640."
}

fetch_source() {
    local ref="$1" release="$2" commit

    [[ -d "$MIRROR_DIR" ]] || {
        [[ -n "$REPO_URL" ]] || die "REPO_URL is not set (deploy.conf) and $MIRROR_DIR does not exist."
        log "Creating mirror $MIRROR_DIR"
        git clone --mirror "$REPO_URL" "$MIRROR_DIR"
    }

    log "Fetching $ref"
    git --git-dir="$MIRROR_DIR" fetch --prune --tags origin '+refs/heads/*:refs/heads/*'
    commit="$(git --git-dir="$MIRROR_DIR" rev-parse --verify "$ref^{commit}")" || die "Unknown ref: $ref"

    mkdir -p "$release"
    git --git-dir="$MIRROR_DIR" archive "$commit" | tar -x -C "$release"
    printf '%s %s\n' "$ref" "$commit" >"$release/REVISION"
    log "Exported $ref ($commit) to $release"
}

link_shared() {
    local release="$1"
    rm -rf "$release/storage"
    ln -s "$SHARED_DIR/storage" "$release/storage"
    ln -sfn "$SHARED_DIR/.env" "$release/.env"
}

build_release() {
    local release="$1"

    log "composer install --no-dev"
    (cd "$release" && "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress)

    log "npm ci && npm run build"
    (cd "$release" && "$NPM_BIN" ci --no-audit --no-fund && "$NPM_BIN" run build && rm -rf node_modules)

    if [[ "$MCP_BUILD" == "1" ]]; then
        log "Building mcp-server"
        (cd "$release/mcp-server" && "$NPM_BIN" ci --no-audit --no-fund && "$NPM_BIN" run build && "$NPM_BIN" prune --omit=dev)
    fi

    artisan "$release" storage:link --no-interaction
}

# Number of pending migrations, or "unknown" when the status cannot be read (e.g. empty database on a first deploy).
pending_migrations() {
    local output
    if ! output="$(artisan "$1" migrate:status --pending --no-interaction 2>&1)"; then
        printf 'unknown'
        return 0
    fi
    grep -c 'Pending' <<<"$output" || true
}

run_migrations() {
    local release="$1" approve="$2" skip_backup="$3" pending answer

    pending="$(pending_migrations "$release")"
    if [[ "$pending" == "0" ]]; then
        log "No pending migrations."
        return 0
    fi

    if [[ "$pending" == "unknown" ]]; then
        warn "Migration status could not be read (first deploy on an empty database?). Treating every migration as pending."
        artisan "$release" migrate --pretend --force --no-interaction || warn "No SQL preview available."
    else
        log "$pending pending migration(s). SQL that will run:"
        artisan "$release" migrate --pretend --force --no-interaction
    fi

    if [[ "$approve" != "1" ]]; then
        if [[ -t 0 ]]; then
            read -r -p "Apply these migrations to PRODUCTION? Type 'yes' to continue: " answer
            [[ "$answer" == "yes" ]] || die "Migrations not approved; nothing was switched. Release left at $release."
        else
            die "Pending migrations need --approve-migrations (review the SQL above first)."
        fi
    fi

    if [[ "$skip_backup" != "1" ]]; then
        if [[ -n "$BACKUP_SCRIPT" && -x "$BACKUP_SCRIPT" ]]; then
            log "Pre-migration backup"
            sudo -n "$BACKUP_SCRIPT" --label pre-deploy || die "Backup failed; aborting before migrations."
        else
            die "BACKUP_SCRIPT is not configured; refusing to migrate without a backup (or pass --skip-backup)."
        fi
    fi

    artisan "$release" migrate --force --no-interaction
}

cleanup_releases() {
    local active keep_count=0 dir
    active="$(current_release)"
    while IFS= read -r dir; do
        keep_count=$((keep_count + 1))
        if ((keep_count > KEEP_RELEASES)) && [[ "$dir" != "$active" ]]; then
            log "Removing old release $dir"
            rm -rf "$dir"
        fi
    done < <(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d | sort -r)
}

cmd_deploy() {
    local ref="" approve=0 maintenance=0 skip_backup=0 allow_check=0 skip_health=0
    while (($#)); do
        case "$1" in
            --approve-migrations) approve=1 ;;
            --maintenance) maintenance=1 ;;
            --skip-backup) skip_backup=1 ;;
            --allow-check-failures) allow_check=1 ;;
            --skip-health) skip_health=1 ;;
            -*) die "Unknown option $1" ;;
            *) ref="$1" ;;
        esac
        shift
    done
    [[ -n "$ref" ]] || usage

    require_commands git tar curl flock "$PHP_BIN" "$COMPOSER_BIN" "$NPM_BIN"
    [[ "$(id -u)" != "0" ]] || die "Run as the application user, not root."

    exec 9>"$APP_PATH/.deploy.lock"
    flock -n 9 || die "Another deploy is running."

    local release previous
    release="$RELEASES_DIR/$(date -u '+%Y%m%d%H%M%S')"
    previous="$(current_release)"

    prepare_shared
    fetch_source "$ref" "$release"
    link_shared "$release"
    build_release "$release"

    if [[ "$maintenance" == "1" && -n "$previous" ]]; then
        log "Maintenance mode on"
        artisan "$previous" down --retry=30 --refresh=15 --no-interaction
    fi

    run_migrations "$release" "$approve" "$skip_backup"

    log "Seeding system data (idempotent: languages, roles/permissions, kill switches, AI studio, radar, sections, menus)"
    artisan "$release" db:seed --force --no-interaction

    log "php artisan optimize"
    artisan "$release" optimize --no-interaction

    log "app:check-production"
    if ! artisan "$release" app:check-production --no-interaction; then
        if [[ "$allow_check" == "1" ]]; then
            warn "Production checks failed but --allow-check-failures was given (first deploy?)."
        else
            [[ "$maintenance" == "1" && -n "$previous" ]] && artisan "$previous" up --no-interaction
            die "Production checks failed; current release unchanged. Fix .env / settings and re-run."
        fi
    fi

    switch_to "$release"

    if [[ "$maintenance" == "1" ]]; then
        artisan "$CURRENT_LINK" up --no-interaction
        log "Maintenance mode off"
    fi

    if [[ "$skip_health" != "1" ]] && ! health_check; then
        if [[ -n "$previous" ]]; then
            warn "Health check failed — rolling back code to $previous (database migrations are NOT reverted)."
            switch_to "$previous"
        fi
        die "Deploy of $ref failed its health check."
    fi

    cleanup_releases
    log "Deployed $ref."
}

cmd_rollback() {
    local active previous
    active="$(current_release)"
    [[ -n "$active" ]] || die "No current release."
    previous="$(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d | sort | awk -v a="$active" '$0 == a { print prev; exit } { prev = $0 }')"
    [[ -n "$previous" ]] || die "No release older than $active."

    warn "Rolling back code: $active -> $previous. Migrations stay applied; revert them only with owner approval."
    switch_to "$previous"
    health_check || die "Rolled back, but the health check still fails."
}

cmd_status() {
    local active dir
    active="$(current_release)"
    while IFS= read -r dir; do
        if [[ "$dir" == "$active" ]]; then
            printf '* %s  %s\n' "$dir" "$(cat "$dir/REVISION" 2>/dev/null || true)"
        else
            printf '  %s  %s\n' "$dir" "$(cat "$dir/REVISION" 2>/dev/null || true)"
        fi
    done < <(find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d | sort)
}

main() {
    local command="${1:-}"
    (($#)) && shift
    [[ "$APP_PATH$DOMAIN" != *'{{'* ]] || die "Placeholders not rendered: set APP_PATH and DOMAIN in $DEPLOY_CONFIG or run render.sh."
    case "$command" in
        deploy) cmd_deploy "$@" ;;
        rollback) cmd_rollback ;;
        status) cmd_status ;;
        *) usage ;;
    esac
}

main "$@"
