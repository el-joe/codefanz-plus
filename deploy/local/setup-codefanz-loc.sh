#!/usr/bin/env bash
# One-time local setup for http://codefanz.loc (Apache + mod_php). Run with: sudo bash deploy/local/setup-codefanz-loc.sh
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Run this script with sudo." >&2
    exit 1
fi

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if ! grep -qE '^[^#]*\scodefanz\.loc(\s|$)' /etc/hosts; then
    echo '127.0.0.1 codefanz.loc www.codefanz.loc' >> /etc/hosts
    echo "Added codefanz.loc to /etc/hosts"
fi

install -m 644 "$project_dir/deploy/local/codefanz.loc.conf" /etc/apache2/sites-available/codefanz.loc.conf
a2enmod rewrite headers deflate >/dev/null
a2ensite codefanz.loc.conf >/dev/null

apache2ctl configtest
systemctl reload apache2

echo "Done: http://codefanz.loc"
