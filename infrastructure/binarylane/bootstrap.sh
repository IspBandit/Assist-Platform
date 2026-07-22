#!/usr/bin/env bash
set -Eeuo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Run as root." >&2
  exit 1
fi
if ! grep -q 'Ubuntu 24.04' /etc/os-release; then
  echo "This bootstrap targets Ubuntu 24.04." >&2
  exit 1
fi

SOURCE_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
TARGET=/opt/assist-platform

apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  ca-certificates curl gnupg ufw fail2ban unattended-upgrades openssl

if ! command -v docker >/dev/null 2>&1; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
    | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
  chmod a+r /etc/apt/keyrings/docker.gpg
  . /etc/os-release
  printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu %s stable\n' \
    "$(dpkg --print-architecture)" "$VERSION_CODENAME" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y \
    docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

install -d -o root -g root -m 0750 \
  "$TARGET"/runtime "$TARGET"/releases \
  "$TARGET"/shared/storage/{cache,logs,sessions,private,backups,imports} \
  "$TARGET"/shared/uploads-public "$TARGET"/backups/database "$TARGET"/logs

install -o root -g root -m 0640 "$SOURCE_DIR/docker-compose.yml" "$TARGET/docker-compose.yml"
install -o root -g root -m 0640 "$SOURCE_DIR/Dockerfile" "$TARGET/runtime/Dockerfile"
install -o root -g root -m 0640 "$SOURCE_DIR/Caddyfile" "$TARGET/runtime/Caddyfile"
install -o root -g root -m 0640 "$SOURCE_DIR/php.ini" "$TARGET/runtime/php.ini"
install -o root -g root -m 0750 "$SOURCE_DIR/firewall.sh" "$TARGET/runtime/firewall.sh"
chown -R 82:82 "$TARGET/shared/storage" "$TARGET/shared/uploads-public"
find "$TARGET/shared/storage" "$TARGET/shared/uploads-public" -type d -exec chmod 0750 {} +

systemctl enable --now docker fail2ban unattended-upgrades
"$TARGET/runtime/firewall.sh"
echo "Host bootstrap complete. No application containers were started."
