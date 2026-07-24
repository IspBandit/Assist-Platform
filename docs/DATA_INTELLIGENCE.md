# Data Intelligence

Data Intelligence sits above Data Sources in the Platform Control Centre. Data Sources discovers and reviews records; Data Intelligence decides where that work will create the most value.

## Current capability

- Brand-scoped national coverage heat map.
- Provider density by category, state and town/suburb.
- Population-versus-provider ratios when a sourced population record exists.
- Gap and opportunity scoring based on supply, population, zero-result demand and verification coverage.
- Verification coverage and import-quality metrics.
- Action queue that preselects the matching category and locality in Data Sources.
- Shared implementation for VanAssist, TowSmart, TrailerWise and LocalTorque; the selected admin brand controls categories and listings.

Population is deliberately optional. The UI says `Not available` instead of inventing a value. Population facts belong in `locality_population_statistics` and must include a source key, reference year and import date.

## Opportunity score

The score is a transparent 0–100 prioritisation aid, not a prediction of revenue. It combines:

1. provider scarcity;
2. population pressure where population is available;
3. recent category-matched zero-result searches; and
4. the share of listings that are not verified.

Priority bands are critical (80+), high (60–79.99), medium (35–59.99) and low (below 35). The scoring implementation is isolated in `OpportunityScorer` and covered by unit tests.

## Adding an analytics source

Implement `MetricSourceInterface`, give the source a stable key, register it with `SourceRegistry`, and add a row to `data_intelligence_sources`. Sources return the common coverage fields consumed by the service. This keeps external analytics, census releases or future demand models out of controllers and views.

## Workflow

1. An administrator filters Data Intelligence by brand, state or category.
2. They send an opportunity to the action queue.
3. The task opens Data Sources with the locality and category mapping preselected.
4. Starting an import marks the task in progress.
5. Imported candidates remain unpublished until reviewed under the existing Data Sources controls.
6. The task can be completed or dismissed with an audit event.

The module never publishes imported records directly and never treats third-party data as verified provider-owned information.
