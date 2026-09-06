set -Eeuo pipefail
cd /opt/assist-platform
archive=backups/database/assist-20260906T193058.sql.gz
sha256sum -c "$archive.sha256"
gzip -t "$archive"
container=assist-sale-restore-20260906
if docker container inspect "$container" >/dev/null 2>&1; then echo 'Existing rehearsal container; refusing overwrite'; exit 2; fi
password=$(openssl rand -hex 24)
start=$(date +%s)
trap 'docker rm -fv "$container" >/dev/null 2>&1 || true' EXIT
docker run -d --name "$container" --network none --memory 512m -e MARIADB_ROOT_PASSWORD="$password" mariadb:11.4 >/dev/null
ready=0
for i in $(seq 1 30); do
 if docker exec -e MYSQL_PWD="$password" "$container" mariadb -uroot -Nse 'SELECT 1' >/dev/null 2>&1; then ready=1; break; fi
 sleep 2
done
[[ "$ready" == 1 ]]
docker exec -e MYSQL_PWD="$password" "$container" mariadb -uroot -e 'CREATE DATABASE restore_test'
gzip -dc "$archive" | docker exec -i -e MYSQL_PWD="$password" "$container" mariadb -uroot restore_test
docker exec -e MYSQL_PWD="$password" "$container" mariadb -uroot -N -e "SELECT COUNT(*) AS restored_tables FROM information_schema.tables WHERE table_schema='restore_test'; SELECT 'providers',COUNT(*) FROM restore_test.providers; SELECT 'users',COUNT(*) FROM restore_test.users; SELECT 'towns',COUNT(*) FROM restore_test.towns;"
docker exec -e MYSQL_PWD="$password" "$container" mariadb-check -uroot --check restore_test | tail -4
printf 'restore_elapsed_seconds=%s\n' "$(( $(date +%s)-start ))"
printf 'release=%s\n' "$(readlink -f /opt/assist-platform/current)"
printf 'completed_at=%s\n' "$(date -u +%FT%TZ)"
