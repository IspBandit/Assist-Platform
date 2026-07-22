#!/usr/bin/env bash
set -Eeuo pipefail
root=/opt/assist-platform
env_file="$root/config/backup.env"
[[ -r "$env_file" ]] || { echo "Missing $env_file" >&2; exit 2; }
set -a; source "$env_file"; set +a
: "${RESTIC_REPOSITORY:?}" "${AWS_ACCESS_KEY_ID:?}" "${AWS_SECRET_ACCESS_KEY:?}" "${RESTIC_PASSWORD:?}"
work=$(mktemp -d /opt/assist-restore-drill.XXXXXX)
container="assist-restore-drill-$(date +%s)"
password=$(openssl rand -hex 24)
cleanup(){ docker rm -f "$container" >/dev/null 2>&1 || true; rm -rf -- "$work"; }
trap cleanup EXIT
cd "$root"
restic restore latest --tag assist-production --target "$work" --include '/backups/database/**'
dump=$(find "$work/backups/database" -type f -name 'assist-*.sql.gz' | sort | tail -1)
[[ -n "$dump" && -f "$dump" ]] || { echo 'No database dump found in restored snapshot.' >&2; exit 3; }
if [[ -f "${dump}.sha256" ]]; then
  checksum_path="${dump#"$work"/}.sha256"
  (cd "$work" && sha256sum -c "$checksum_path")
fi
gzip -t "$dump"
docker run -d --name "$container" -e MARIADB_ROOT_PASSWORD="$password" mariadb:11.4 >/dev/null
ready=0
for _ in $(seq 1 60); do
  if docker exec "$container" mariadb -uroot -p"$password" -Nse 'SELECT 1' >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 2
done
[[ "$ready" == 1 ]] || { echo 'Restore-test database did not become ready.' >&2; exit 5; }
docker exec "$container" mariadb -uroot -p"$password" -e 'CREATE DATABASE restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
gzip -dc "$dump" | docker exec -i "$container" mariadb -uroot -p"$password" restore_test
table_count=$(docker exec "$container" mariadb -N -uroot -p"$password" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='restore_test'")
[[ "$table_count" -gt 20 ]] || { echo "Restore drill produced only $table_count tables." >&2; exit 4; }
echo "Off-site restore drill passed with $table_count restored tables. Production was not modified."
