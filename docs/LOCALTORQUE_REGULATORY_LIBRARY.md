# Shared vehicle regulatory library

Backlog: **DATA-008**. Architecture: **ADR 0008**.

## Product boundary

The library helps a reader reach the applicable official material. VanAssist,
TowSmart, TrailerWise and LocalTorque expose brand-relevant subsets through the
same /rules route and canonical source register. It is not
legal advice, engineering approval, registration approval or a roadworthy
certificate. Federal/national technical codes and jurisdiction administration
are stored separately because both may apply.

Brand views are:


- VanAssist: caravans, motorhomes, camper trailers, towing and travel safety;
- TowSmart: towing, mass, loading, braking, coupling and combinations;
- TrailerWise: trailer construction, registration, inspection and modification;
- LocalTorque: broad roadworthy and modification coverage for cars, 4WDs,
  utes, trucks, motorcycles, trailers and street rods. Vehicle-filtered
  journeys use subject-specific, art-directed responsive hero photography.

Shared source coverage includes:
- Commonwealth Australian Design Rules, VSB 14 and national street-rod guidance;
- NHVR VSB 6 and the National Heavy Vehicle Inspection Manual;
- ACT, NSW, Victoria, Queensland, South Australia, Western Australia, Tasmania
  and Northern Territory inspection and modification resources;
- cars, 4WDs, utes/light trucks, heavy vehicles, motorcycles, trailers, individually
  constructed vehicles and street rods where the authority material applies.

Street-rod coverage deliberately separates the nationally endorsed construction
manual from each jurisdiction's approval and registration pathway. Migration 054
adds official ACT, NSW, Victoria, Queensland, South Australia, Western Australia,
Tasmania and Northern Territory sources, including genuine authority-hosted PDF
downloads where one is published. A national manual never substitutes for a
state or territory registration decision.

## Motorsport rules, venues and calendars

Backlog: **DATA-010 / LOC-004**. Architecture: **ADR 0011**.

Competition eligibility is separate from road legality. `/motorsport` uses a
dedicated catalogue for Motorsport Australia, AASA, ANDRA, Speedway Australia,
Karting Australia and Motorcycling Australia material. Nine families expose
more than 50 named car, kart and motorcycle disciplines rather than hiding them
behind one generic racing label.

Results identify national/sanctioning-body rules, discipline and class
technical rules, state or series rules, and event supplementary regulations.
The venue register distinguishes permanent facilities, temporary locations,
route-based competition and club networks. It stores the venue website when
known and a separate venue, club or governing-body calendar URL when published.
Dates remain on the official source because an event may change or be cancelled
after publication.

Run `php scripts/check-motorsport-sources.php 20` on the trusted scheduler. It
checks due rulebook and calendar sources with conditional HTTP requests. Changed
rule documents move to review; changed venue/calendar records leave the public
list pending review. Failures retry after six hours and never create substitute
rules or event dates.

## Source contract

Every public record identifies its authority, jurisdiction, applicable vehicle
classes, kind, official page, optional direct authority download, version,
effective period, publication status and last successful/attempted check.
Link-only is the default licence status. A file must not be copied into platform
storage without explicit reuse permission and a documented retention need.

Current and upcoming records may be public. Review, superseded, withdrawn and
non-public records are excluded from the public query.

## Freshness workflow

Run the check-regulatory-sources script with a limit of 20 from a trusted
scheduled worker after migration 050. The intended production cadence is daily.
The monitor uses conditional HTTP headers where supplied, records a SHA-256
fingerprint and appends a check event. The first observation establishes a
baseline. A changed fingerprint changes the document to review, records the
change time, and removes it from public results. An editor must verify the new
version, effective date, download URL, vehicle scope and description before
restoring current or upcoming.

A source failure is retried after six hours and is retained in the audit log.
Repeated failures require operations alerting; a failure never causes an
unverified substitute source to be published.

## Sponsored local specialists

The result page may show up to three active campaigns targeted to placement
regulatory_library. Campaigns use the shared advertising catalogue and can
target LocalTorque provider category, state, region or town. Rule type, vehicle
and search keywords select relevant provider categories. A reader can explicitly
choose a town; the platform does not infer location for advertising.

Every placement is labelled Sponsored and explains that payment does not affect
official sources or organic directory rankings. Campaign setup and billing
remain subject to the platform's advertising and billing controls.

## Release and rollback

Apply migration 050 through the immutable release process, run an initial
baseline check, inspect failures, then enable the daily scheduled command.
Application rollback leaves the additive tables intact. The monitor can be
disabled independently. LocalTorque public release still requires the domain,
mail, legal and quality-gate prerequisites in LOCALTORQUE.md.
