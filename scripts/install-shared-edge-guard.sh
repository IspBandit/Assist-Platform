#!/usr/bin/env bash
# Install the reviewed generic shared-edge guard without changing any vhost.
set -euo pipefail
umask 077

if [[ ${EUID} -ne 0 ]]; then
  echo 'Run as root.' >&2
  exit 1
fi

source_root=$(cd "$(dirname "$0")/.." && pwd)
source_guard="$source_root/infrastructure/binarylane/ops/check-shared-public-edge.sh"
edge="${SHARED_EDGE_ROOT:-/opt/shared-public-edge}"
target="$edge/bin/check-shared-public-edge"

test -f "$source_guard"
test -s "$edge/required-sites.txt"
test -d "$edge/sites"

install -d -o root -g root -m 0755 "$edge/bin"
install -o root -g root -m 0755 "$source_guard" "$target"

"$target" check
echo 'Shared public-edge guard installed; every registered site is healthy.'
