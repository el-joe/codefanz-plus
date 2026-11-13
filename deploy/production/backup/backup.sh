#!/usr/bin/env bash
# CodeFanz — nightly encrypted backup: MySQL dump, private storage (+ public media), .env.
#
#   backup.sh                  nightly run (daily/, promoted to weekly/ on Sundays and monthly/ on the 1st)
#   backup.sh --label NAME     ad-hoc backup in adhoc/ (e.g. pre-deploy), kept RETAIN_ADHOC times
#
# The schema has no stored routines or events, so the dump user needs no SHOW_ROUTINE / EVENT grants.
# Run as root (cron.d/codefanz). Output per backup directory:
#   db.sql.gz.<enc|gpg>      mysqldump --single-transaction (consistent InnoDB snapshot, no table locks)
#   files.tar.gz.<enc|gpg>   storage/app/private (originals, lead attachments, project documents) [+ app/public]
#   env.<enc|gpg>            the production .env (APP_KEY is required to read encrypted settings!)
#   SHA256SUMS               checksums of the encrypted files, verified before any restore
# Retention: RETAIN_DAILY=7, RETAIN_WEEKLY=4, RETAIN_MONTHLY=6 (newest kept). Off-site: BACKUP_OFFSITE_CMD.

set -Eeuo pipefail
umask 077

# shellcheck source=deploy/production/backup/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

label=""
while (($#)); do
    case "$1" in
        --label) label="${2:-}"; shift ;;
        *) die "Unknown option $1" ;;
    esac
    shift
done
[[ -z "$label" || "$label" =~ ^[a-z0-9-]+$ ]] || die "Label must be lowercase letters, digits and dashes."

require_commands mysqldump gzip tar openssl sha256sum
check_key

stamp="$(date '+%Y-%m-%d_%H%M%S')"
if [[ -n "$label" ]]; then
    target="$BACKUP_ROOT/adhoc/${stamp}_$label"
else
    target="$BACKUP_ROOT/daily/$stamp"
fi
mkdir -p "$target"
ext="$(enc_ext)"
completed=0
on_exit() {
    if [[ "$completed" != "1" ]]; then
        log "Backup failed; removing incomplete $target"
        rm -rf "$target"
    fi
}
trap on_exit EXIT

log "Dumping database $DB_NAME"
mysql_backup_client mysqldump --single-transaction --quick --triggers --hex-blob \
    --no-tablespaces --default-character-set=utf8mb4 --set-gtid-purged=OFF "$DB_NAME" \
    | gzip -6 | encrypt_stream >"$target/db.sql.gz.$ext"

log "Archiving storage"
paths=(app/private)
if [[ "$INCLUDE_PUBLIC_MEDIA" == "1" && -d "$STORAGE_DIR/app/public" ]]; then
    paths+=(app/public)
fi
tar -C "$STORAGE_DIR" -czf - "${paths[@]}" | encrypt_stream >"$target/files.tar.gz.$ext"

log "Saving .env"
encrypt_stream <"$ENV_FILE" >"$target/env.$ext"

(cd "$target" && sha256sum -- *."$ext" >SHA256SUMS)

# Integrity: the dump must decrypt and decompress cleanly (only possible with the symmetric key on this host).
if [[ "$ext" == "enc" ]]; then
    decrypt_stream "$target/db.sql.gz.enc" <"$target/db.sql.gz.enc" | gzip -t
    decrypt_stream "$target/db.sql.gz.enc" <"$target/db.sql.gz.enc" | gunzip | tail -n 1 | grep -q 'Dump completed' \
        || die "Dump is incomplete (no 'Dump completed' trailer)."
fi
completed=1
log "Backup written to $target ($(du -sh "$target" | cut -f1))"

promote() {
    local tier="$1"
    mkdir -p "$BACKUP_ROOT/$tier"
    cp -al "$target" "$BACKUP_ROOT/$tier/$stamp"
    log "Promoted to $tier/"
}

prune() {
    local tier="$1" keep="$2" dir count=0
    [[ -d "$BACKUP_ROOT/$tier" ]] || return 0
    while IFS= read -r dir; do
        count=$((count + 1))
        if ((count > keep)); then
            rm -rf "$dir"
            log "Pruned $dir"
        fi
    done < <(find "$BACKUP_ROOT/$tier" -mindepth 1 -maxdepth 1 -type d | sort -r)
}

if [[ -z "$label" ]]; then
    [[ "$(date '+%u')" == "7" ]] && promote weekly
    [[ "$(date '+%d')" == "01" ]] && promote monthly
fi

prune daily "$RETAIN_DAILY"
prune weekly "$RETAIN_WEEKLY"
prune monthly "$RETAIN_MONTHLY"
prune adhoc "$RETAIN_ADHOC"

if [[ -n "$BACKUP_OFFSITE_CMD" ]]; then
    log "Off-site copy"
    BACKUP_DIR="$target" BACKUP_ROOT="$BACKUP_ROOT" bash -c "$BACKUP_OFFSITE_CMD"
fi

log "Done."
