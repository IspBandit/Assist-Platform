# National Places rescue bootstrap (ADR 0042)

**Backlog:** VAN-011 / DATA-013  
**Script:** `scripts/places-rescue-bootstrap.php`  
**Flag:** `provider_places_rescue` (default off)

## Purpose

Seed high-demand categories across Australian hub towns (including Charters
Towers, Townsville and Longreach) using the **same**
`ZeroResultProviderRescueService` path as live traveller rescue. Results are
labelled unclaimed public-source listings — never auto-verified.

## Prerequisites

1. Google Places connector active in Admin → Data Sources with API key, daily
   quota and AUD budget.
2. Migration `136_provider_places_rescue_flag.sql` applied.
3. Feature flag `provider_places_rescue` enabled only after Quality Gate and
   budget alert are in place.
4. Local or staging database — do not point this machine at production without
   the operations runbook.

## Commands

```powershell
# Preview hubs/categories (no Places calls)
php scripts/places-rescue-bootstrap.php --dry-run

# Focused Charters Towers fridge/refrigeration run
php scripts/places-rescue-bootstrap.php --apply --town="Charters Towers" --category=refrigeration

# Broader national hubs (cap towns)
php scripts/places-rescue-bootstrap.php --apply --limit-towns=24
```

## Cost control

Each town × category issues one Places Text Search. Start with a single town
and category. Configure Google Cloud budget alerts and the connector daily
budget before any production apply.

## Honesty

Auto-created rows are `is_unclaimed=1`, `is_verified=0`, with Place ID
provenance. Travellers see public-source labelling. Claim and verification
remain the monetisation path.
