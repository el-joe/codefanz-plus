#!/usr/bin/env bash
# CodeFanz — restore drill. Takes a fresh backup (or uses the one given), restores it into a throwaway database
# <DB_NAME>_restore_drill, compares the row count of every table with the live database, checks the files archive
# and the .env, then drops the drill database. Exit 0 = backup proven restorable.
#
#   restore-drill.sh [<backup-dir>]
#
# Tables that change every few seconds (cache, sessions, jobs) are reported but may differ.

set -Eeuo pipefail
umask 077

script_dir="$(dirname "$(readlink -f "$0")")"
# shellcheck source=deploy/production/backup/lib.sh
source "$script_dir/lib.sh"

VOLATILE_TABLES=" cache cache_locks sessions jobs job_batches failed_jobs "
drill_db="${DB_NAME}_restore_drill"
[[ "$drill_db" != "$DB_NAME" ]] || die "Drill database must differ from the live one."

dir="${1:-}"
if [[ -z "$dir" ]]; then
    "$script_dir/backup.sh" --label drill
    dir="$(find "$BACKUP_ROOT/adhoc" -mindepth 1 -maxdepth 1 -type d -name '*_drill' | sort -r | head -n 1)"
fi
[[ -d "$dir" ]] || die "No backup to drill."

cleanup() { mysql_admin -e "DROP DATABASE IF EXISTS \`$drill_db\`" && log "Dropped $drill_db"; }
trap cleanup EXIT

started=$SECONDS
mysql_admin -e "DROP DATABASE IF EXISTS \`$drill_db\`"
"$script_dir/restore.sh" db "$dir" "$drill_db"
restore_seconds=$((SECONDS - started))

live="$(table_row_counts "$DB_NAME")"
restored="$(table_row_counts "$drill_db")"

failures=0 tables=0 rows=0
while read -r table live_count; do
    tables=$((tables + 1))
    rows=$((rows + live_count))
    restored_count="$(awk -v t="$table" '$1 == t { print $2 }' <<<"$restored")"
    if [[ -z "$restored_count" ]]; then
        log "MISSING  $table (live $live_count rows)"
        failures=$((failures + 1))
    elif [[ "$restored_count" != "$live_count" ]]; then
        if [[ "$VOLATILE_TABLES" == *" $table "* ]]; then
            log "volatile $table live=$live_count restored=$restored_count (expected to move)"
        else
            log "MISMATCH $table live=$live_count restored=$restored_count"
            failures=$((failures + 1))
        fi
    fi
done <<<"$live"

files_file="$(find "$dir" -maxdepth 1 -name 'files.tar.gz.*' | head -n 1)"
archived_files="$(decrypt_stream "$files_file" <"$files_file" | tar -tz | grep -vc '/$' || true)"
live_files="$(find "$STORAGE_DIR/app/private" -type f | wc -l)"
if [[ "$INCLUDE_PUBLIC_MEDIA" == "1" && -d "$STORAGE_DIR/app/public" ]]; then
    live_files=$((live_files + $(find "$STORAGE_DIR/app/public" -type f | wc -l)))
fi

env_file="$(find "$dir" -maxdepth 1 -name 'env.*' | head -n 1)"
if decrypt_stream "$env_file" <"$env_file" | grep -q '^APP_KEY=base64:'; then env_ok=yes; else env_ok=no; failures=$((failures + 1)); fi

log "Drill of $dir: $tables tables, $rows live rows, restore took ${restore_seconds}s; files archived=$archived_files live=$live_files; .env with APP_KEY: $env_ok"

if ((failures > 0)); then
    die "Restore drill FAILED ($failures problem(s))."
fi
log "Restore drill PASSED."
