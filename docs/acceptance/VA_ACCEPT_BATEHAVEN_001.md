# VA-ACCEPT-BATEHAVEN-001 — Acceptance specification

**Mandatory query:** `public toilets and dump points near Batehaven, NSW`  
**Parent package:** [`VANASSIST_PRODUCTION_READINESS_PACKAGE.md`](../VANASSIST_PRODUCTION_READINESS_PACKAGE.md) §3  
**Environment:** non-production / staging only  
**Production flags:** must remain **off**

## Goal

Prove VanAssist can surface real public toilets and dump points for the
Batehaven / Batemans Bay (NSW) area without inventing listings and without
misclassifying facilities as caravan parks.

## Preconditions

1. Migrations through `092`–`094` applied (`097` optional).  
2. At least one active+reviewed `public_toilet` and one `dump_point` within
   25–50 km of Batehaven or Batemans Bay (demo approve and/or Toilet Map subset).  
3. Staging only: `assist_ai_search=1`, `assist_ai_traveller_facilities=1`.  
4. Paid AI disabled (`ai_enabled=0`).  
5. After the run, restore flags to off if this host could be mistaken for prod.

## Procedure

### Automated (preferred)

```bash
# No DB: fixtures + intent
php scripts/acceptance-batehaven-facilities.php --dry-run

# With configured MariaDB (non-prod):
php scripts/migrate.php
php scripts/acceptance-batehaven-facilities.php --import-approve --radius=50
```

Unit harness (CI): `tests/Unit/AiSearch/BatehavenAcceptanceHarnessTest.php`

### Manual

1. Record git SHA, env, operator.  
2. SQL or admin: count facilities by type near the resolved town.  
3. Open Ask VanAssist; submit the mandatory query (optional lat/lng for Batehaven).  
4. Capture intent summary (facility keys, town, radius, adapters).  
5. Capture result lists: facilities vs providers vs stays.  
6. Confirm structured `/find` still works for a dump-points / related category.  
7. Disable Ask + facilities flags; confirm `/ask` → 404.  
8. File evidence under `docs/evidence/vanassist-readiness-YYYY-MM-DD/`.

## Pass criteria

- ≥1 toilet and ≥1 dump point in Traveller facilities (or CONDITIONAL if only
  providers — document gap).  
- No facility rendered as a caravan park.  
- Provenance present for dataset-sourced rows.  
- No paid AI usage events for the run.  
- Flags off afterward on shared hosts.

## Result record

Local full run (2026-08-02): **PASS** — see
`docs/evidence/vanassist-readiness-2026-08-02/VA_ACCEPT_BATEHAVEN_001.json`
(toilet + dump via rules; paid AI off; flags restored).

```text
Date:
Env:
Git SHA:
Town resolved (id/name):
Toilet count / Dump count in radius:
Ask result summary:
/find check:
Paid AI usage (must be 0):
Flags after:
PASS / CONDITIONAL / FAIL:
Notes:
Tester:
```
