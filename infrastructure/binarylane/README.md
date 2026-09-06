# BinaryLane production runtime

This directory contains the reproducible Ubuntu 24.04 production runtime for
the three-domain Assist Platform.

## Topology

- Caddy terminates HTTPS for VanAssist, TowSmart, and TrailerWise.
- One PHP-FPM application resolves the brand from the trusted request host.
- MariaDB is reachable only on the internal Docker network.
- Runtime storage and public uploads survive immutable release switches.
- Cron locks live in shared writable storage, never inside immutable releases.
- Cloudflare proxies public DNS to the VPS.

## Shared-host edge extension

The Assist Caddy container is the sole public listener on host ports 80 and 443.
A separate product may temporarily share this VPS without becoming an Assist
application brand by using the reviewed host-only edge extension:

- external vhost drop-ins are mounted from `/opt/shared-public-edge/sites` and
  imported as `/etc/caddy/sites/*.caddy`;
- Caddy joins the Docker network named `shared-public-edge`;
- the separate product exposes only an internal HTTP service on that network and
  must not publish host ports 80 or 443;
- the separate product owns its own vhost file, application, credentials,
  database and runtime data.

Do not place another product's application code or product-specific runtime
configuration in this repository. Removing its vhost and network attachment must
leave the three-brand Assist runtime unchanged. See ADR 0041.

## Safe deployment

1. Review the files and create a BinaryLane snapshot.
2. Run `bootstrap.sh` as root.
3. Create `/opt/assist-platform/config/app.env` and `infra.env` with mode `0600`.
4. Upload a checksum-verified immutable release and point `current` at it.
5. Run `docker compose config -q` before starting containers.
6. Apply migrations once, seed reference data, and create the administrator locally.
7. Install monitoring and scheduled jobs from `ops/`.
8. Verify `/healthz`, `/readyz`, brand pages, and installer denial for every domain.

The public installer is deliberately denied by Caddy. Initial production setup
must be completed through a controlled server-side operation.
