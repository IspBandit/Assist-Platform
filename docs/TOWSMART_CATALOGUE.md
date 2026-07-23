# TowSmart catalogue and data provenance

## Current catalogue

TowSmart includes the structured catalogue recovered from the original TowWise Android application:

- 199 Australian tow-vehicle reference records
- 3,769 caravan, camper, hybrid and trailer reference records

The source files are `resources/towsmart/catalog/vehicles.json` and
`resources/towsmart/catalog/trailers.json`. They are intentionally treated as
advertised reference specifications, not certified weights or current legal
limits. Users are instructed to confirm the exact variant, compliance plate,
handbook and current manufacturer information.

## Required provenance for additions

Every future specification source must record the source organisation, source
URL or document reference, publication/model year, retrieval date, units and
verification status. A model name without a variant and model year is not
sufficient where ratings differ.

Recent additions carry `source_url` and `source_as_at` fields directly in the
JSON record. The recovered legacy records predate this rule and remain queued
for provenance and grade/body replacement work.

Preferred sources are manufacturer handbooks, official brochures, compliance
information and properly licensed commercial datasets. Do not bulk-copy a
commercial vehicle or caravan database without an appropriate licence.

## Provider and location enrichment

National providers must be created as an organisation with individual branch
locations. Each branch must retain its official locator URL, last-checked date,
public contact details, relevant services and verification state. A retail
parts branch must not be labelled as a repairer, inspector or roadworthy
provider unless the official branch information supports that service.

Locality data should use an authoritative licensed Australian source. G-NAF is
the preferred open national address reference. Australia Post postcode data
requires a commercial-use licence; do not use its personal-use file in this
commercial platform.
