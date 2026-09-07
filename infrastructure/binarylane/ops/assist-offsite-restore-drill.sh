#!/usr/bin/env bash
set -Eeuo pipefail
root=/opt/assist-platform
env_file="$root/config/backup.env"
status_dir="$root/shared/storage/ops"
status_file="$status_dir/offsite-restore-drill.status.json"
mkdir -p "$status_dir"
record_failure(){ printf '{"status":"failed","completed_at":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$status_file"; }
trap record_failure ERR
[[ -r "$env_file" ]] || { record_failure; echo "Missing $env_file" >&2; exit 2; }
set -a; source "$env_file"; set +a
: "${RESTIC_REPOSITORY:?}" "${AWS_ACCESS_KEY_ID:?}" "${AWS_SECRET_ACCESS_KEY:?}" "${RESTIC_PASSWORD:?}"
work=$(mktemp -d /opt/assist-restore-drill.XXXXXX)
container="assist-restore-drill-$(date +%s)"
password=$(openssl rand -hex 24)
cleanup(){ docker rm -f "$container" >/dev/null 2>&1 || true; rm -rf -- "$work"; }
trap cleanup EXIT
cd "$root"
restic restore latest --tag assist-production --target "$work" \
  --include '/backups/database/**' \
  --include '/backups/metadata/latest.json' \
  --include '/shared/storage/private/**' \
  --include '/shared/uploads-public/**' \
  --include '/config/app.env' \
  --include '/config/infra.env'
metadata="$work/backups/metadata/latest.json"
[[ -s "$metadata" ]] || { echo 'Backup-set release metadata is missing.' >&2; exit 3; }
release="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1], encoding="utf-8"))["release"])' "$metadata")"
[[ "$release" =~ ^[a-f0-9]{40}$ ]] || { echo 'Backup-set release identity is invalid.' >&2; exit 3; }
restic restore latest --tag assist-production --tag "release-$release" --target "$work" \
  --include "/releases/$release/**"
dump=$(find "$work/backups/database" -type f -name 'assist-*.sql.gz' | sort | tail -1)
[[ -n "$dump" && -f "$dump" ]] || { echo 'No database dump found in restored snapshot.' >&2; exit 3; }
[[ -s "$work/config/app.env" && -s "$work/config/infra.env" ]] || {
  echo 'Protected application or infrastructure configuration was not restored.' >&2
  exit 3
}
[[ -f "$work/releases/$release/public/index.php" \
   && -f "$work/releases/$release/config/app.php" \
   && -f "$work/releases/$release/vendor/autoload.php" ]] || {
  echo 'Matching immutable application release was not restored completely.' >&2
  exit 3
}
[[ -d "$work/shared/storage/private" && -d "$work/shared/uploads-public" ]] || {
  echo 'Private or public media directories were not restored.' >&2
  exit 3
}
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
printf '{"status":"success","completed_at":"%s","release":"%s","restored_tables":%s,"application":true,"configuration":true,"media":true}\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$release" "$table_count" > "$status_file"
trap - ERR
echo "Off-site restore drill recovered release $release, protected configuration, media and $table_count database tables. Production was not modified."
