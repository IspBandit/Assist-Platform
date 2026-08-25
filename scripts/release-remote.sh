#!/usr/bin/env bash
set -euo pipefail

root="/opt/assist-platform"
archive="${1:-}"
release="${2:-}"
app_env="$root/config/app.env"

if [[ ! "$release" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Release must be a full Git commit SHA." >&2
  exit 2
fi
expected="$root/incoming/assist-platform-$release.tar.gz"
if [[ "$archive" != "$expected" || ! -f "$archive" ]]; then
  echo "Release archive is not the expected verified incoming file." >&2
  exit 2
fi

cd "$root"
./runtime/ops/assist-backup-now.sh

target="$root/releases/$release"
if [[ ! -d "$target" ]]; then
  mkdir -p "$target"
  tar --extract --gzip --file "$archive" --directory "$target" --no-same-owner
fi

runtime_source="$target/infrastructure/binarylane"
for required in docker-compose.yml Dockerfile Caddyfile php.ini firewall.sh; do
  if [[ ! -f "$runtime_source/$required" ]]; then
    echo "Reviewed runtime file is missing from the release: $required" >&2
    exit 1
  fi
done
if [[ ! -d "$runtime_source/ops" ]]; then
  echo "Reviewed runtime operations directory is missing from the release." >&2
  exit 1
fi

previous="$(readlink -f "$root/current" || true)"
previous_app_release="$(sed -n 's/^APP_RELEASE=//p' "$app_env" | tail -n 1)"
runtime_rollback="$root/runtime-rollback-$release"
install -d -o root -g root -m 0700 "$runtime_rollback/ops"
cp -a "$root/docker-compose.yml" "$runtime_rollback/docker-compose.yml"
for file in Dockerfile Caddyfile php.ini firewall.sh; do
  cp -a "$root/runtime/$file" "$runtime_rollback/$file"
done
cp -a "$root/runtime/ops/." "$runtime_rollback/ops/"

set_app_release() {
  local value="$1"
  if grep -q '^APP_RELEASE=' "$app_env"; then
    sed -i "s/^APP_RELEASE=.*/APP_RELEASE=$value/" "$app_env"
  else
    printf '\nAPP_RELEASE=%s\n' "$value" >> "$app_env"
  fi
}

rollback() {
  trap - ERR
  install -o root -g root -m 0640 "$runtime_rollback/docker-compose.yml" "$root/docker-compose.yml"
  install -o root -g root -m 0640 "$runtime_rollback/Dockerfile" "$root/runtime/Dockerfile"
  install -o root -g root -m 0640 "$runtime_rollback/Caddyfile" "$root/runtime/Caddyfile"
  install -o root -g root -m 0640 "$runtime_rollback/php.ini" "$root/runtime/php.ini"
  install -o root -g root -m 0750 "$runtime_rollback/firewall.sh" "$root/runtime/firewall.sh"
  find "$root/runtime/ops" -maxdepth 1 -type f -name '*.sh' -delete
  find "$runtime_rollback/ops" -maxdepth 1 -type f -name '*.sh' -exec install -o root -g root -m 0750 {} "$root/runtime/ops/" \;
  if [[ -n "$previous" && -d "$previous" ]]; then
    if [[ -n "$previous_app_release" ]]; then
      set_app_release "$previous_app_release"
    fi
    ln -sfn "$previous" "$root/current.next"
    mv -Tf "$root/current.next" "$root/current"
    docker compose up -d --build --force-recreate app caddy
  fi
  rm -rf -- "$runtime_rollback"
}
trap rollback ERR

# Runtime and Compose configuration are bootstrap-installed outside the current
# symlink. Refresh them from this reviewed immutable release before building so
# infrastructure changes cannot remain silently stranded in GitHub.
install -o root -g root -m 0640 "$runtime_source/docker-compose.yml" "$root/docker-compose.yml"
install -o root -g root -m 0640 "$runtime_source/Dockerfile" "$root/runtime/Dockerfile"
install -o root -g root -m 0640 "$runtime_source/Caddyfile" "$root/runtime/Caddyfile"
install -o root -g root -m 0640 "$runtime_source/php.ini" "$root/runtime/php.ini"
install -o root -g root -m 0750 "$runtime_source/firewall.sh" "$root/runtime/firewall.sh"
find "$root/runtime/ops" -maxdepth 1 -type f -name '*.sh' -delete
find "$runtime_source/ops" -maxdepth 1 -type f -name '*.sh' -exec install -o root -g root -m 0750 {} "$root/runtime/ops/" \;

ln -sfn "$target" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"
set_app_release "$release"

# CQDiggings community detector reports are runtime data, not release files.
# Prepare the bind sources before Caddy reads the reviewed Compose file.
cq_root="/opt/cqdiggings"
install -d -o 82 -g 82 -m 0750 \
  "$cq_root/shared/analytics/_detector-settings" \
  "$cq_root/shared/analytics/_detector-setting-uploads" \
  "$cq_root/shared/assets/community-detector-settings" \
  "$cq_root/shared/data"
if [[ ! -f "$cq_root/shared/data/community-detector-settings.json" ]]; then
  printf '{"schema_version":1,"updated":null,"records":[]}\n' > "$cq_root/shared/data/community-detector-settings.json"
fi
chown 82:82 "$cq_root/shared/data/community-detector-settings.json"
chmod 0640 "$cq_root/shared/data/community-detector-settings.json"

docker compose config -q
docker compose up -d --build --force-recreate app caddy
docker compose exec -T app php scripts/migrate.php
docker compose exec -T app php scripts/seed.php --ask-library
docker compose exec -T app php scripts/seed.php --localtorque
docker compose exec -T app php scripts/data-quality-audit.php --strict

for url in \
  https://vanassist.com.au/readyz \
  https://towsmart.com.au/readyz \
  https://trailerwise.com.au/readyz; do
  curl --fail --silent --show-error --retry 6 --retry-delay 5 "$url" >/dev/null
done

trap - ERR
rm -rf -- "$runtime_rollback"
rm -f "$archive"
echo "Released $release successfully. Previous release: ${previous:-none}"
