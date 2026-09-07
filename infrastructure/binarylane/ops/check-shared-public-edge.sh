#!/usr/bin/env bash
# Host-owned safety contract; product URLs are configuration, not Assist brands.
set -euo pipefail
umask 077
root="${SHARED_EDGE_ROOT:-/opt/shared-public-edge}"
manifest="$root/required-sites.txt"
container="${SHARED_EDGE_CADDY:-assist-platform-caddy-1}"
test -s "$manifest" || { echo 'Required shared-edge site registry is missing or empty.' >&2; exit 1; }
test -d "$root/sites"

inventory() {
  (cd "$root"; find sites -maxdepth 1 -type f -name '*.caddy' -print0 | sort -z | xargs -0 -r sha256sum)
}

check_sites() {
  local url marker extra code count=0 body
  body=$(mktemp)
  while IFS='|' read -r url marker extra || [[ -n "${url:-}" ]]; do
    [[ -z "$url" || "$url" == \#* ]] && continue
    if [[ ! "$url" =~ ^https://[a-zA-Z0-9.-]+/[^[:space:]\|]*$ || -z "$marker" || -n "$extra" ]]; then
      rm -f "$body"
      echo 'Invalid registry entry; require https://host/path|expected-body-text.' >&2
      return 1
    fi
    count=$((count + 1))
    if ! code=$(curl --fail --silent --show-error --location --retry 3 --retry-all-errors --retry-delay 2 \
      --connect-timeout 10 --max-time 20 --user-agent 'Mozilla/5.0 Shared-Edge-Release-Check/1.0' \
      --output "$body" --write-out '%{http_code}' "$url"); then
      rm -f "$body"; echo "Shared-edge request failed: $url" >&2; return 1
    fi
    if [[ "$code" != 200 ]] || ! grep -Fqi -- "$marker" "$body"; then
      rm -f "$body"; echo "Shared-edge status or product identity failed: $url" >&2; return 1
    fi
    printf 'Healthy: %s\n' "$url"
  done < "$manifest"
  rm -f "$body"
  [[ "$count" -gt 0 ]] || { echo 'No registered public sites.' >&2; return 1; }
}

case "${1:-}" in
  check) check_sites ;;
  snapshot)
    state="${2:?Supply a private snapshot directory}"
    test -d "$state"
    check_sites
    cp "$manifest" "$state/required-sites.txt"
    inventory > "$state/sites.sha256"
    ;;
  candidate)
    candidate="${2:?Supply candidate Caddyfile}"
    json=$(mktemp)
    trap 'rm -f "$json"' EXIT
    docker exec -i "$container" caddy adapt --config /dev/stdin --adapter caddyfile --validate < "$candidate" > "$json"
    python3 - "$manifest" "$json" <<'PY'
import json, sys
from urllib.parse import urlsplit
manifest, config = sys.argv[1:]
with open(config, encoding='utf-8') as stream:
    http = json.load(stream).get('apps', {}).get('http', {})
hosts = set()
def visit(value):
    if isinstance(value, dict):
        for key, child in value.items():
            if key == 'host' and isinstance(child, list):
                hosts.update(str(host).lower() for host in child)
            visit(child)
    elif isinstance(value, list):
        for child in value:
            visit(child)
visit(http)
with open(manifest, encoding='utf-8') as stream:
    required = {urlsplit(line.split('|', 1)[0]).hostname for line in stream
                if line.strip() and not line.startswith('#')}
missing = required - hosts
if missing:
    raise SystemExit('Candidate drops registered host routes: ' + ', '.join(sorted(str(x) for x in missing)))
print('Candidate retains every registered hostname.')
PY
    ;;
  verify)
    state="${2:?Supply the pre-release snapshot directory}"
    cmp -s "$manifest" "$state/required-sites.txt" || { echo 'Site registry changed during release.' >&2; exit 1; }
    current=$(mktemp)
    trap 'rm -f "$current"' EXIT
    inventory > "$current"
    cmp -s "$current" "$state/sites.sha256" || { echo 'Product-owned edge routes changed during release.' >&2; exit 1; }
    check_sites
    ;;
  *) echo 'Usage: check-shared-public-edge.sh check|snapshot DIR|candidate CADDYFILE|verify DIR' >&2; exit 2 ;;
esac
