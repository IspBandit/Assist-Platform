# VanAssist traveller-data source archive

Immutable source vault + provenance register for VanAssist national traveller
data (DATA-012 / DATA-011A / VAN-001).

## Layout

| Path | Purpose |
| --- | --- |
| `registry/` | `sources.json`, `checksums.sha256` (committed) |
| `australia/`, `qld/`, … | Jurisdiction raw archives (gitignored binaries) |
| `industry/` | Industry guides + authorised CPAQ PDF |
| `regional-guides/` | Official tourism/council PDF guides |
| `reports/` | Sync/import run reports |

Large raw files are **gitignored**. Assist RIC remains the production acquisition
engine (ADR 0033). This archive is the Platform-side vault and register.

Raw binaries may also live under Assist RIC:
`D:\Works _in_progress\assist-ric\data_catalogue\raw\`.

## Refresh

```bash
python tools/vanassist_sources/sync_archive.py
python tools/vanassist_sources/sync_archive.py --refresh-green
python tools/vanassist_sources/update_registry_extras.py
```

## Import

```bash
# Authorised CPAQ parks + trade
php scripts/import-cpaq-2026.php            # dry-run
php scripts/import-cpaq-2026.php --apply

# GREEN facility packs via existing GovernmentDatasetService
php scripts/import-archived-green-facilities.php --force --approve

# National Toilet Map (~25k) — streamed bulk publish
php scripts/import-toilet-map-direct.php --force
```

Human register: `docs/data/VANASSIST_DATA_SOURCE_REGISTER.md`
