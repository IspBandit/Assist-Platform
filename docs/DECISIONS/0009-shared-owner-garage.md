# ADR 0009: Shared owner Garage and private compliance wallet

- **Status:** accepted
- **Date:** 2026-07-27
- **Owners:** Assist Platform Enterprise
- **Backlog items:** CORE-009, EXP-007
- **Affected brands/modules:** all brands, accounts, vehicle profiles, private uploads, regulatory library

## Context

The same person may use VanAssist for travel, TowSmart for a weight check,
TrailerWise for trailer ownership and LocalTorque for vehicle work. Separate
brand-owned vehicle profiles would force duplicate data entry and weaken useful
handoffs between specialist experiences.

Vehicle compliance documents are sensitive account data. They must not be
public uploads or inferred to be verified merely because an owner stored them.

## Decision

The platform owns one user-scoped `garage_assets` record for each vehicle or
towable. The profile is deliberately not brand-scoped. `created_in_brand_id`
records origin for honest context and analytics but does not limit visibility.
Every read and mutation is constrained by the authenticated user ID.

Private documents are stored outside the public root and served only after an
asset-ownership join. Initial profiles do not collect a registration number or
VIN. Documents may carry issue and expiry dates; expiry creates a reminder
preference but sending remains dependent on the normal email and reminder gate.

Cross-brand use is recorded as low-sensitivity product activity. It does not
copy the profile, disclose it to providers or grant a provider access. Provider
handoff will require a separate explicit user action and consented disclosure
contract under CORE-010.

## Consequences

- owners add details once and reuse them across all four brands;
- brand experiences can deep-link to official rules using asset type and state;
- TowSmart's existing saved calculations remain immutable snapshots and can be
  linked to Garage assets later without changing past results;
- uploaded files increase private-storage and data-erasure obligations;
- a document in the wallet is owner-supplied, not platform-verified evidence.

## Quality Gate impact

- **Architecture — pass:** shared user ownership replaces brand-specific
  duplication; brand origin remains context rather than an access boundary.
- **UX — pass:** responsive Garage index, detail, action and document-wallet
  states were inspected at 1440 px and a true 390 px viewport with no horizontal
  overflow.
- **Engineering — pass:** additive schema, strict owner joins and private
  authenticated files; two Garage integration tests pass with 14 assertions,
  including cross-account isolation.
- **Business — pass:** higher repeat utility and cross-brand retention without
  selling or exposing private owner data. Provider disclosure remains a later,
  explicit-consent capability.

## Validation and rollback

Apply migration 051 after migration 050. Test two-user isolation, all four brand
contexts, document ownership, invalid asset types and mobile/desktop rendering.
Application rollback removes Garage routes and entry points while retaining
private records and documents until the data-retention process removes them.
