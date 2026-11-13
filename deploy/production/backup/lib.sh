#!/usr/bin/env bash
# CodeFanz backups — shared settings and helpers, sourced by backup.sh, restore.sh and restore-drill.sh.
#
# Settings come from /etc/codefanz/backup.conf (override with BACKUP_CONFIG=...). See backup.conf.example.
# Encryption: openssl AES-256-CBC + PBKDF2 (200k iterations) with a key read from a root-only file, or — when
# BACKUP_GPG_RECIPIENT is set — GnuPG public-key encryption (the server never holds the private key; restores then
# happen on a machine that has it).

BACKUP_CONFIG="${BACKUP_CONFIG:-/etc/codefanz/backup.conf}"
if [[ -r "$BACKUP_CONFIG" ]]; then
    # shellcheck source=/dev/null
    source "$BACKUP_CONFIG"
fi

DEFAULT_APP_PATH='{{APP_PATH}}'
APP_PATH="${APP_PATH:-$DEFAULT_APP_PATH}"
DB_NAME="${DB_NAME:-codefanz}"
STORAGE_DIR="${STORAGE_DIR:-$APP_PATH/shared/storage}"
ENV_FILE="${ENV_FILE:-$APP_PATH/shared/.env}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/codefanz}"
BACKUP_KEY_FILE="${BACKUP_KEY_FILE:-/etc/codefanz/backup.key}"
BACKUP_GPG_RECIPIENT="${BACKUP_GPG_RECIPIENT:-}"
MYSQL_DEFAULTS_FILE="${MYSQL_DEFAULTS_FILE:-/etc/codefanz/backup.my.cnf}"
MYSQL_ADMIN_DEFAULTS_FILE="${MYSQL_ADMIN_DEFAULTS_FILE:-}"
RETAIN_DAILY="${RETAIN_DAILY:-7}"
RETAIN_WEEKLY="${RETAIN_WEEKLY:-4}"
RETAIN_MONTHLY="${RETAIN_MONTHLY:-6}"
RETAIN_ADHOC="${RETAIN_ADHOC:-5}"
INCLUDE_PUBLIC_MEDIA="${INCLUDE_PUBLIC_MEDIA:-1}"
BACKUP_OFFSITE_CMD="${BACKUP_OFFSITE_CMD:-}"

[[ "$APP_PATH" != *'{{'* ]] || { echo "APP_PATH is not set: create $BACKUP_CONFIG from backup.conf.example" >&2; exit 1; }

log() { printf '[backup %s] %s\n' "$(date '+%F %T')" "$*"; }
die() { printf '[backup %s] ERROR: %s\n' "$(date '+%F %T')" "$*" >&2; exit 1; }

require_commands() {
    local cmd
    for cmd in "$@"; do
        command -v "$cmd" >/dev/null 2>&1 || die "Missing command: $cmd"
    done
}

# File extension of encrypted artefacts.
enc_ext() {
    if [[ -n "$BACKUP_GPG_RECIPIENT" ]]; then printf 'gpg'; else printf 'enc'; fi
}

check_key() {
    if [[ -z "$BACKUP_GPG_RECIPIENT" ]]; then
        [[ -r "$BACKUP_KEY_FILE" ]] || die "Key file $BACKUP_KEY_FILE is missing (openssl rand -base64 48 > it; chmod 400)."
        [[ "$(stat -c '%a' "$BACKUP_KEY_FILE")" =~ ^[46]00$ ]] || die "$BACKUP_KEY_FILE must be mode 400 or 600."
    fi
}

encrypt_stream() {
    if [[ -n "$BACKUP_GPG_RECIPIENT" ]]; then
        gpg --batch --yes --quiet --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output -
    else
        openssl enc -aes-256-cbc -salt -pbkdf2 -iter 200000 -pass "file:$BACKUP_KEY_FILE"
    fi
}

decrypt_stream() {
    case "$1" in
        *.gpg) gpg --batch --quiet --decrypt ;;
        *) openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass "file:$BACKUP_KEY_FILE" ;;
    esac
}

# mysql / mysqldump with credentials from a defaults file (never on the command line).
mysql_backup_client() {
    local bin="$1"
    shift
    if [[ -r "$MYSQL_DEFAULTS_FILE" ]]; then
        "$bin" --defaults-extra-file="$MYSQL_DEFAULTS_FILE" "$@"
    else
        "$bin" "$@"
    fi
}

# Admin connection for restores and drills (CREATE/DROP DATABASE). Empty = local socket auth as root.
mysql_admin() {
    if [[ -n "$MYSQL_ADMIN_DEFAULTS_FILE" ]]; then
        mysql --defaults-extra-file="$MYSQL_ADMIN_DEFAULTS_FILE" "$@"
    else
        mysql "$@"
    fi
}

# Newest backup directory across daily/weekly/monthly/adhoc.
latest_backup() {
    find "$BACKUP_ROOT" -mindepth 2 -maxdepth 2 -type d -name '20*' -printf '%f %p\n' 2>/dev/null | sort -r | head -n 1 | cut -d' ' -f2-
}

# Exact row count of every base table in a database, as "table count" lines.
table_row_counts() {
    local db="$1" table
    while IFS= read -r table; do
        printf '%s %s\n' "$table" "$(mysql_admin -N -B -e "SELECT COUNT(*) FROM \`$db\`.\`$table\`")"
    done < <(mysql_admin -N -B -e "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_type = 'BASE TABLE' ORDER BY table_name")
}
