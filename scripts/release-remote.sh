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

previous="$(readlink -f "$root/current" || true)"
previous_app_release="$(sed -n 's/^APP_RELEASE=//p' "$app_env" | tail -n 1)"
ln -sfn "$target" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"

set_app_release() {
  local value="$1"
  if grep -q '^APP_RELEASE=' "$app_env"; then
    sed -i "s/^APP_RELEASE=.*/APP_RELEASE=$value/" "$app_env"
  else
    printf '\nAPP_RELEASE=%s\n' "$value" >> "$app_env"
  fi
}
set_app_release "$release"

rollback() {
  if [[ -n "$previous" && -d "$previous" ]]; then
    if [[ -n "$previous_app_release" ]]; then
      set_app_release "$previous_app_release"
    fi
    ln -sfn "$previous" "$root/current.next"
    mv -Tf "$root/current.next" "$root/current"
    docker compose up -d --build app caddy
  fi
}
trap rollback ERR

docker compose config -q
docker compose up -d --build app caddy
docker compose exec -T app php scripts/migrate.php

for url in \
  https://vanassist.com.au/readyz \
  https://towsmart.com.au/readyz \
  https://trailerwise.com.au/readyz; do
  curl --fail --silent --show-error --retry 6 --retry-delay 5 "$url" >/dev/null
done

trap - ERR
rm -f "$archive"
echo "Released $release successfully. Previous release: ${previous:-none}"
