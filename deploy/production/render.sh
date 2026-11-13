#!/usr/bin/env bash
# CodeFanz — render the production templates with your values (no secrets are involved).
#
#   render.sh <vars-file> <output-dir>
#
# <vars-file> holds KEY=value lines for the placeholders used in this folder, e.g.
#   DOMAIN=codefanz.com
#   ALT_DOMAIN=www.codefanz.com
#   APP_PATH=/var/www/codefanz
#   APP_USER=codefanz
#   PHP_FPM_SOCK=/run/php/php8.3-fpm-codefanz.sock
#   SSL_CERT=/etc/letsencrypt/live/codefanz.com/fullchain.pem
#   SSL_KEY=/etc/letsencrypt/live/codefanz.com/privkey.pem
#   DB_NAME=codefanz
#   GIT_REPOSITORY=owner/codefanz-plus
# The rendered tree mirrors this folder; review it, then copy each file to the path named in its header.
# Any placeholder left without a value is reported and the script exits non-zero.

set -Eeuo pipefail
# Bash ≥ 5.2 treats & in ${var//pattern/replacement} as the match; values must be literal.
shopt -u patsub_replacement 2>/dev/null || true

(($# == 2)) || { sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 2; }
vars_file="$1"
out_dir="$2"
src_dir="$(dirname "$(readlink -f "$0")")"
[[ -r "$vars_file" ]] || { echo "Cannot read $vars_file" >&2; exit 1; }

declare -A values=()
while IFS='=' read -r key value; do
    [[ -z "$key" || "$key" == \#* ]] && continue
    [[ "$key" =~ ^[A-Z_]+$ ]] || { echo "Bad key: $key" >&2; exit 1; }
    values["$key"]="$value"
done <"$vars_file"

mkdir -p "$out_dir"
while IFS= read -r -d '' file; do
    rel="${file#"$src_dir"/}"
    mkdir -p "$out_dir/$(dirname "$rel")"
    content="$(<"$file")"
    for key in "${!values[@]}"; do
        content="${content//\{\{$key\}\}/${values[$key]}}"
    done
    printf '%s\n' "$content" >"$out_dir/$rel"
    [[ -x "$file" ]] && chmod +x "$out_dir/$rel"
done < <(find "$src_dir" -type f ! -name 'render.sh' -print0)

# PROXY_CIDR / OFFSITE_HOST appear only in commented examples and may stay unrendered.
if leftovers="$(grep -rnoE '\{\{[A-Z_]+\}\}' "$out_dir" | grep -vE 'PROXY_CIDR|OFFSITE_HOST')"; then
    echo "Placeholders without a value:" >&2
    echo "$leftovers" >&2
    exit 1
fi
echo "Rendered into $out_dir"
