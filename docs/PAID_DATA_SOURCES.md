# Paid Data Sources

All paid connectors are **disabled by default**.

## Controls (mandatory)

- Explicit owner enablement in Dataset Catalogue / Settings
- API keys only in OS secure storage (`keyring`) or local env — never SQLite plaintext commits
- Daily hard request limit
- Monthly hard request limit
- Monthly currency budget (AUD)
- Soft warning before approaching caps (Settings / job message)
- Automatic stop at hard limit (`PaidBudgetExceeded`)
- No silent fallback from free sources to paid sources
- Usage reporting via import job messages and Settings budget fields

## Catalogued paid sources

| Dataset ID | Vendor docs | Default daily | Default monthly | Default budget AUD | Status |
| --- | --- | --- | --- | --- | --- |
| `paid_google_places` | https://developers.google.com/maps/documentation/places/web-service | 100 | 1000 | 25 | Disabled; connector blocks live query until scoped job approved |
| `paid_brave_search` | https://brave.com/search/api/ | 100 | 1000 | 20 | Disabled catalogue stub |
| `paid_here_places` | https://www.here.com/docs/bundle/places-api-developer-guide | 50 | 500 | 20 | Disabled catalogue stub |
| `paid_tomtom_search` | https://developer.tomtom.com/search-api | 50 | 500 | 20 | Disabled catalogue stub |
| `paid_mapbox_search` | https://docs.mapbox.com/api/search/ | 50 | 500 | 20 | Disabled catalogue stub |
| `paid_openchargemap` | https://openchargemap.org/site/develop | 100 | 1000 | 10 | Disabled; confirm current free/paid terms before enable |

## Pricing honesty

Seed `estimated_request_cost_aud` values are **operator guardrail defaults**, not
quoted vendor invoices. Before enabling a paid source, open the vendor pricing
page, record the current unit price and free allowance in Notes, then set caps
below your real monthly budget.

## Google Places specific

- Do not scrape Maps/Search HTML.
- Connector key: `google_places_research`
- Live download raises until an owner-approved scoped research job is configured.
- Results, if ever enabled, must stage as `web_research_review` and never
  auto-publish.
