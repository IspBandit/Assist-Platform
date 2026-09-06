# ADR 0041: Generic shared public edge extension

- **Status:** proposed
- **Date:** 2026-09-06
- **Owners:** Assist Platform Enterprise
- **Backlog item:** OPS-001
- **Affected brands/modules:** BinaryLane production edge only; no Assist application brand additions

## Context

The BinaryLane VPS already has one reviewed Caddy service that owns host ports 80 and 443 for the Assist Platform production runtime. Separate products may temporarily share the same VPS for cost efficiency, but they must not become Assist application brands, code dependencies, database dependencies or acquisition dependencies. Running a second public reverse proxy on the same host ports creates a port collision and can cause failed origins or cross-domain routing mistakes.

## Decision

Keep the Assist Platform Caddy service as the sole public listener on host ports 80 and 443. Add a generic, host-only extension point:

- Caddy imports reviewed vhost files from `/etc/caddy/sites/*.caddy`.
- The host directory `/opt/shared-public-edge/sites` is mounted read-only at that location.
- Caddy joins the explicitly named `shared-public-edge` Docker network so separately deployed products can expose an internal HTTP service to the edge without publishing host ports 80 or 443.

The Assist repository does not contain another product's application code, product-specific vhost, credentials, database, runtime data or business logic. Each separate product owns its own vhost file and internal service definition. Installing or removing such a vhost is a host deployment operation and does not create a new Assist Platform brand.

## Alternatives considered

- Run a second Caddy instance on host ports 80/443: rejected because the ports are already owned by the reviewed Assist edge.
- Add separate-product domains directly to the Assist application Caddyfile: rejected because it would couple separate products to the Assist repository and blur the acquisition boundary.
- Expose a separate product on a high public port: rejected because it adds unnecessary attack surface and complicates public TLS/origin routing.
- Move every separate product immediately to another VPS: valid long-term, but unnecessary for a temporary low-cost shared-host arrangement when runtime isolation can be preserved.

## Consequences

- Assist remains the sole public edge listener on the VPS.
- The existing VanAssist, TowSmart, TrailerWise and CQDiggings site definitions are unchanged.
- Separate products can be attached through an internal Docker network and removable vhost drop-in.
- The edge now has a deliberately documented host-level extension surface, so only reviewed vhost files may be installed there.
- A separate product must be independently movable to another host without changes to Assist application code or data.

## Quality Gate impact

- Architecture: preserves the three-brand application boundary while making shared-host routing explicit.
- UX: no intended change to existing Assist or CQDiggings user journeys.
- Engineering: validate Caddy configuration, Compose configuration, existing domain health and absence of cross-domain content leakage.
- Business: reduces temporary hosting cost without making a separate product part of the Assist sale package.

## Validation and rollback

Validate `docker compose config`, `caddy validate`, all existing Assist/CQ hostnames, the attached product hostname and health endpoint, redirects, TLS and content identity. Rollback removes the external vhost, detaches the separate service from `shared-public-edge`, and reverts this infrastructure change; the original Assist site blocks and data remain unchanged.
