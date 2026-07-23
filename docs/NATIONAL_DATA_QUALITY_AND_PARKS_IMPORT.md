# National data quality and parks import

## Verification policy

Directory discovery is not verification. Imported businesses and parks remain
unclaimed and unverified unless an operator claim is approved or an official
authority source directly supports the public record. TowSmart catalogue values
remain advertised reference specifications until an individual manufacturer
source is recorded and reviewed.

The platform uses these distinct states:

- **Unverified/discovered**: imported public facts; users must confirm details.
- **Community sourced**: open/community directory evidence, including OSM.
- **Authority confirmed**: a current Australian government source URL is stored.
- **Operator verified**: an approved ownership claim exists.

No automated import may assign operator verification.

## July 2026 supplied parks archive

The supplied `parks.zip` contains 25,575 deduplicated stays and 2,981 dump
points. It has no licence file and combines official/open sources with scraped
commercial directories. The preparation command therefore exports only rows
whose evidence URL is on an Australian `.gov.au` host:

```bash
php scripts/prepare-parks-import.php \
  stays_master_deduped.csv \
  authority-stays.csv
```

Observed preparation result:

- 2,246 well-formed official-source park/camp records accepted.
- 683 accepted records have coordinates.
- 1,563 accepted records require geocoding/review before GPS search is reliable.
- 1,000 malformed official-source scraper rows quarantined (the complete broken
  Parks Victoria batch, primarily HTML fragments in names/URLs).
- 22,329 commercial or otherwise unlicensed rows quarantined.
- 0 structurally invalid official rows.

The quarantined rows include bulk-derived commercial directory content. Do not
publish their descriptions, ratings or compiled data until Condren Digital has
documented reuse permission or an applicable licence.

Pure dump points are not overnight stays. They require their own directory model
and must not be inserted as caravan parks.

## Safe database import

The authority importer is a dry run unless `--apply` is explicitly supplied:

```bash
php scripts/import-authority-stays.php authority-stays.csv
php scripts/import-authority-stays.php authority-stays.csv --apply
```

Before `--apply`:

1. Take and verify a database backup.
2. Save the dry-run JSON report.
3. Review rejected and matched counts.
4. Confirm source URLs remain official.

The importer matches its stable source identifier first. It may also reuse an
existing park only when normalized name, state and coordinates agree within a
small tolerance. It never downgrades an operator-verified park.

## Repeatable audit

Run:

```bash
php scripts/data-quality-audit.php
```

The report covers missing locality details, provider provenance, brand listing
gaps, conservative duplicate candidates, park provenance, relationship
integrity and TowSmart specification issues. Candidate duplicates are not safe
to merge automatically; claims, reviews, subscriptions and source evidence must
be preserved through an audited merge.

## Known catalogue review queues

At the time this control was added, the repository catalogue audit found:

- 199 TowSmart vehicle references; 163 lacked an individual source URL.
- 3,769 TowSmart trailer references; all lacked an individual source URL.
- 5 trailer records had an invalid weight relationship.
- 86 trailer identity combinations were duplicate candidates.

These records may remain searchable as advertised reference data with the
existing safety disclaimer, but must not be described as verified or certified.
