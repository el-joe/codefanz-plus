#!/usr/bin/env bash
# CodeFanz — restore from an encrypted backup directory.
#
#   restore.sh db    <backup-dir> <target-database> [--force]   import the dump (creates the database)
#   restore.sh files <backup-dir> <target-directory>            unpack app/private (+ app/public) into the directory
#   restore.sh env   <backup-dir> <target-file>                 decrypt the saved .env
#
# Checksums are verified first. Restoring INTO the live database name ($DB_NAME) requires --force and should only
# happen with the site in maintenance mode, after the owner approved it (DEPLOYMENT_GUIDE §8.2).

set -Eeuo pipefail
umask 077

# shellcheck source=deploy/production/backup/lib.sh
source "$(dirname "$(readlink -f "$0")")/lib.sh"

usage() { sed -n '2,8p' "$0" | sed 's/^# \{0,1\}//'; exit 2; }

(($# >= 3)) || usage
mode="$1" dir="$2" target="$3" force="${4:-}"
[[ -d "$dir" ]] || die "No backup directory $dir"
require_commands sha256sum gzip tar

(cd "$dir" && sha256sum --quiet -c SHA256SUMS) || die "Checksum mismatch in $dir — do not restore from it."
artefact() { find "$dir" -maxdepth 1 -name "$1.*" ! -name SHA256SUMS | head -n 1; }

case "$mode" in
    db)
        [[ "$target" =~ ^[A-Za-z0-9_]+$ ]] || die "Database name must be letters, digits and underscores."
        if [[ "$target" == "$DB_NAME" && "$force" != "--force" ]]; then
            die "Refusing to overwrite the live database $DB_NAME without --force."
        fi
        file="$(artefact db.sql.gz)"
        [[ -n "$file" ]] || die "No database dump in $dir"
        log "Restoring $file into $target"
        mysql_admin -e "CREATE DATABASE IF NOT EXISTS \`$target\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        decrypt_stream "$file" <"$file" | gunzip | mysql_admin "$target"
        log "Database $target restored."
        ;;
    files)
        file="$(artefact files.tar.gz)"
        [[ -n "$file" ]] || die "No files archive in $dir"
        mkdir -p "$target"
        decrypt_stream "$file" <"$file" | tar -xz -C "$target"
        log "Files restored into $target (fix ownership: chown -R APP_USER: $target)."
        ;;
    env)
        file="$(artefact env)"
        [[ -n "$file" ]] || die "No .env in $dir"
        decrypt_stream "$file" <"$file" >"$target"
        chmod 600 "$target"
        log ".env restored to $target"
        ;;
    *) usage ;;
esac
