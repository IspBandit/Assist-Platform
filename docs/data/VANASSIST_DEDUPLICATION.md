# VanAssist Data Deduplication Guarantees

**Architecture:** ADR 0034 (Government dataset trusted import path)  
**Backlog:** DATA-012, VAN-001  
**Implementation:** `App\Services\GovernmentDatasetService`

## Deduplication Strategy

The VanAssist traveller data import pipeline guarantees that:

1. **The same facility from the same source never creates duplicates**
2. **Re-importing the same dataset updates existing records rather than creating new ones**
3. **Different sources can describe the same physical facility without conflict**
4. **Cross-source duplicates are detected and linked, not prevented**

## Source-Based Identity

Every imported facility record carries a stable identity:

```php
[
    'source_key' => 'gov:qld_roadside_amenities',      // Dataset identifier
    'source_record_id' => '12345',                      // External ID from source
]
```

This tuple is **unique** and **immutable** per facility. The same external ID from the
same source always refers to the same facility.

## Import Idempotency

### First Import

When `GovernmentDatasetService::stageCandidate()` encounters a new record:

```php
// Line 656-659: Check for existing published facility
$dup = Database::selectOne(
    'SELECT id FROM traveller_facilities 
     WHERE source_key = ? AND source_record_id = ? AND deleted_at IS NULL LIMIT 1',
    [$sourceKey, $externalId]
);
```

- **Not found:** Create a new candidate record
- **Found:** Link candidate to existing facility via `duplicate_facility_id`

### Subsequent Imports

When the same dataset is imported again:

```php
// Line 710-712: Find existing facility by source identity
$existing = Database::selectOne(
    'SELECT id FROM traveller_facilities 
     WHERE source_key = ? AND source_record_id = ? LIMIT 1',
    [$sourceKey, (string) $candidate['external_id']]
);
```

- **Found:** **UPDATE** the existing record (lines 715-722)
- **Not found:** Insert new record (lines 731-750)

### Result

Re-importing a dataset is safe and idempotent:

- Updated details (name, address, coordinates, attribution) are refreshed
- The same facility ID is preserved
- Reviews, ratings, subscriptions, and relationships remain intact
- `last_checked_at` is updated to confirm currency

## Cross-Source Duplicate Detection

When different sources describe the same physical facility (e.g., the same caravan park
appears in both the CPAQ directory and the BIG4 guide), the import pipeline:

1. **Does not prevent import** — both source records are valid
2. **Detects potential duplicates** — soft-match by name, location, phone, website
3. **Links via `duplicate_facility_id`** — candidate flagged for review
4. **Preserves all source records** — provenance is never lost

Duplicate _candidates_ are reviewed manually or flagged for automated merge where
confidence is high and no ownership/verification conflicts exist.

## Staging Deduplication

The review queue (`traveller_facility_import_candidates`) prevents staging the same
candidate multiple times:

```php
// Line 647-652: Prevent duplicate pending candidates
$pending = Database::selectOne(
    'SELECT id FROM traveller_facility_import_candidates
     WHERE dataset_id = ? AND brand_id <=> ? AND external_id = ?
       AND review_status = \'pending\' AND expires_at > NOW()
     LIMIT 1',
    [(int) $dataset['id'], $brandId, $externalId]
);
if ($pending !== null) {
    return false;  // Already staged, skip
}
```

**Result:** The same source record cannot be queued twice in the same import job.

## Database Constraints

### Unique Keys

```sql
-- traveller_facilities
UNIQUE KEY source_identity (source_key, source_record_id)

-- traveller_facility_import_candidates
UNIQUE KEY job_scoped_identity (job_id, external_id)
```

`INSERT IGNORE` is used (line 661) so constraint violations are silently skipped
rather than causing errors.

### Foreign Keys

```sql
duplicate_facility_id INT NULL,
FOREIGN KEY (duplicate_facility_id) REFERENCES traveller_facilities(id)
```

Cross-source duplicates are linked via this optional relationship.

## Examples

### Example 1: Re-importing Queensland Roadside Amenities

**First import (2026-09-10):**
- 1,066 records imported from `qld_roadside_amenities.geojson`
- Each assigned `source_key = 'gov:qld_roadside_amenities'`
- Each uses its `objectid` as `source_record_id`

**Second import (2026-10-15):**
- Same 1,066 records, but 12 have updated names/coordinates
- Import runs `stageCandidate()` for all 1,066 rows
- 1,054 records: existing facility found → UPDATE executed
- 12 records: new facilities (new `objectid` from state refresh) → INSERT

**Result:** Database has 1,066 facilities; 1,054 refreshed, 12 added. No duplicates.

### Example 2: Cross-Source Duplicate (Same Park in Two Guides)

**CPAQ import:**
```php
[
    'source_key' => 'gov:cpaq-2026',
    'source_record_id' => 'park-goldcoast-123',
    'name' => 'Paradise Beach Holiday Park',
    'latitude' => -28.1234, 'longitude' => 153.5678,
]
```

**BIG4 import:**
```php
[
    'source_key' => 'gov:industry_big4_holiday_guide_2026',
    'source_record_id' => 'big4-123',
    'name' => 'BIG4 Paradise Beach Holiday Park',
    'latitude' => -28.1235, 'longitude' => 153.5679,  // 11 meters away
]
```

**Deduplication logic:**
- Soft-match detects high confidence duplicate (name similarity + GPS proximity)
- BIG4 candidate is staged with `duplicate_facility_id = [CPAQ facility id]`
- Review workflow flags it: "This may be the same as Paradise Beach Holiday Park (CPAQ)"
- Admin can:
  - **Approve as new:** BIG4 source record is valid; both facilities coexist
  - **Merge:** Combine both source records into one canonical facility
  - **Reject:** Discard BIG4 candidate

## Import Safety

### What Cannot Happen

- ❌ Same source record creating multiple facilities
- ❌ Re-import duplicating entire dataset
- ❌ Lost provenance (source is always recorded)
- ❌ Undetected cross-source duplicates (soft-match flags them)

### What Can Happen (By Design)

- ✅ Same physical facility described by multiple sources (both records preserved)
- ✅ Candidate flagged as potential duplicate (requires review)
- ✅ Manual merge consolidates multiple source records

## Verification Policy

Imported records start as `unverified` unless:

- Source is `authority` (e.g., Parks Victoria official data) → `authority_confirmed`
- Source is community (e.g., OpenStreetMap) → `community_sourced`
- An operator claim is approved → `operator_verified`

**Never automatically upgrade to `operator_verified` via import.**

## Related Code

| Component | Path |
| --- | --- |
| Import service | `app/Services/GovernmentDatasetService.php` |
| Source key helper | `GovernmentDatasetService::catalogueSourceKey()` |
| Staging logic | `GovernmentDatasetService::stageCandidate()` (line 630) |
| Publishing logic | `GovernmentDatasetService::publishCandidate()` (line 690) |
| Schema | `database/migrations/*_create_traveller_facilities_table.php` |

## References

- ADR 0034: Trusted government dataset auto-publish
- `docs/NATIONAL_DATA_QUALITY_AND_PARKS_IMPORT.md`
- `docs/CPAQ_2026_IMPORT.md`
- `docs/data/VANASSIST_DATA_SOURCE_REGISTER.md`
