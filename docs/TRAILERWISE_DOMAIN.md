# TrailerWise Domain Foundation

## Status

Migration `035_trailerwise_foundation.sql` provides an additive relational
foundation. TrailerWise remains disabled/coming-soon until public, provider,
admin, moderation, enquiry, and search workflows are implemented and tested.

## Model boundaries

### Canonical provider

The existing `providers` table remains the canonical organisation/business.
A business participates in TrailerWise through a TrailerWise
`provider_brand_listings` record. This avoids duplicating a business that also
appears on VanAssist.

### Trailer business profile

`trailer_business_profiles` identifies a listing's role, such as manufacturer,
dealer, repairer, parts supplier, inspector, certifier, engineer, hire business,
or transport business. Roles are separate rows because a business may perform
more than one role.

Licence references and expiry dates are private administrative evidence fields;
public badges require a separate verified approval workflow. `is_authorised`
must never imply government or manufacturer authorisation without evidence.

### Trailer types

`trailer_types` is the controlled public taxonomy. The many-to-many
`trailer_business_types` table identifies which trailer types a business serves.

### Individual listings

`trailer_listings` represents an individual new, used, hire, or informational
trailer listing. It is deliberately separate from the business directory. A
listing belongs to a TrailerWise provider-brand listing and a controlled trailer
type.

TrailerWise connects enquiries to third-party businesses. It does not become
the seller merely because a third party publishes a listing.

## Required application safeguards

Before enabling the module:

- require the owning provider listing to belong to the TrailerWise brand;
- enforce provider ownership and brand permissions server-side;
- validate specification fields through typed schemas;
- moderate public listings and uploaded media;
- clearly identify the third-party seller/provider;
- keep private licence/verification evidence outside public responses;
- add expiry and revocation workflows for controlled badges;
- add pagination, location and trailer-type indexes based on measured queries;
- test cross-brand and cross-provider isolation;
- implement enquiry attribution and consumer disclosures.
