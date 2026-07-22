#!/usr/bin/env bash
set -euo pipefail

cd /opt/assist-platform
install -d -o root -g root -m 0700 backups/database
stamp="$(date +%Y%m%dT%H%M%S)"
target="backups/database/assist-${stamp}.sql.gz"
docker compose exec -T mariadb sh -c \
  'mariadb-dump --single-transaction --routines --triggers -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
  | gzip -9 > "$target"
chmod 0600 "$target"
sha256sum "$target" > "${target}.sha256"
chmod 0600 "${target}.sha256"
gzip -t "$target"
printf 'Verified backup: %s\n' "$target"
