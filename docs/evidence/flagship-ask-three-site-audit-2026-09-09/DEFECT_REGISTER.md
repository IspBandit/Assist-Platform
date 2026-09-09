# Defect register

## Fixed in candidate

1. **Critical — wrong-host VanAssist module exposure.** Public, customer,
   provider and park request/run/stay routes were reachable on brands whose
   module registry disabled them. Added server middleware and 404 acceptance.
2. **High — Ask discarded state qualifiers.** Duplicate Australian place names
   could silently resolve to the wrong state. State/postcode text is retained
   and ambiguous exact names now require clarification.
3. **High — request radius was not authoritative.** The explicit request field
   did not override interpreted/default radius. It now does, within 1–500 km.
4. **High — reviewed facilities were not brand filtered.** Every facility query
   now requires and filters the trusted database brand ID.
5. **High — unresolved facility locations could fall back nationally.** Facility
   results now require a resolved town or valid Australian coordinate.
6. **High — safety-critical questions lacked emergency framing.** Fire, gas,
   failed brakes, medical and stranded wording now puts safety guidance before
   directory results.
7. **Medium — external source schemes were rendered unchecked.** Facility and
   staged-candidate source links now allow only HTTP/HTTPS.
8. **Medium — Ask analytics retained obvious contact details.** Email addresses
   and Australian phone-number patterns are redacted before query persistence.
9. **Medium — documentation pages exposed duplicate H1 landmarks.** The shell
   now owns the single page H1 while Markdown retains standalone source titles.
10. **Medium — PHPUnit's configured Unit suite omitted nested test folders.**
    AiSearch, DataSources and Polaris suites are now explicit; the gate expanded
    from 94 to 493 tests.
11. **Low — PHP database calls emitted warnings when configuration was absent.**
    Database configuration now fails cleanly before array access.
12. **Low — mobile homepage acceptance expected a retired navigation label.**
    The test now targets the actual accessible `Find VanAssist help` landmark.
13. **High — NZ GPS could pass the Australian rectangle.** Longitude is capped
    below New Zealand so Fiordland/NZ South Island coordinates are rejected.
14. **High — unresolved named locations still continued adapter execution.**
    Ambiguous or unresolved place names now skip provider/stay/facility/dataset
    searches; the nationwide facility fallback path was removed.
15. **Medium — account UI exposed request/run cards without module checks.**
    Shared account dashboard and VanAssist header CTA now honour module flags.
16. **Medium — legacy `/privacy` and `/terms` paths 404'd.** Brand footer config
    now points at `/privacy-policy` and `/terms-of-use`, with permanent redirects
    from the short aliases.
17. **Medium — national matrix and Batehaven harness under-asserted location and
    contamination claims.** Assertions now check the expected town/state, empty
    non-facility groups, brand ID and radius ceilings.

## Verified existing controls

- TowSmart calculator routes fail closed outside TowSmart.
- TrailerWise marketplace/provider listing controller paths are brand and
  ownership constrained.
- Provider, stay and traveller-facility result groups remain distinct.
- Pending imported candidates are disabled for initial Ask launch.
- Paid AI is disabled and no paid calls occurred during acceptance.

## Open / release-controlled

- Production currently renders Ask and facility results, but Ask is not the
  primary homepage form and the wrong-host module fixes are not deployed.
- Authenticated browser acceptance passed against disposable
  customer/provider fixtures that were removed after the run.
- Production data population, flag writes and deployment require the controlled
  operations runbook and are not performed by source-code validation.
- Full screen-reader manual testing remains an operator/UX evidence item;
  automated landmark, label, focus, touch-size and overflow checks are present.
- Production `/help/request` remains a documentation 404 by design; the live
  request journey is `/request-assistance` on VanAssist only.
