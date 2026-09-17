-- VAN-011: Make Ask radius search see existing Charters Towers listings and
-- other unclaimed providers that only had a town link (no measurable point).
-- Charters Towers was created by national import as unverified after the
-- gazetteer pack fingerprint was already recorded, so publicCoordinateSql
-- could not fall back to the town centre.

UPDATE towns t
JOIN states s ON s.id = t.state_id
SET
    t.latitude = -20.0765117,
    t.longitude = 146.2614002,
    t.coordinate_source = 'qld-place-names-gazetteer',
    t.coordinate_confidence = 'authoritative',
    t.coordinate_reference = '6945',
    t.coordinate_verified_at = CURRENT_DATE,
    t.updated_at = NOW()
WHERE s.abbreviation = 'QLD'
  AND t.slug = 'charters-towers';

-- Unclaimed listings without a sourced address point inherit their base town
-- centre so distance ranking and Ask radius filters can measure them.
UPDATE providers p
INNER JOIN towns t ON t.id = p.base_town_id
SET
    p.latitude = t.latitude,
    p.longitude = t.longitude,
    p.updated_at = NOW()
WHERE p.deleted_at IS NULL
  AND p.is_unclaimed = 1
  AND p.latitude IS NULL
  AND p.longitude IS NULL
  AND t.latitude IS NOT NULL
  AND t.longitude IS NOT NULL
  AND t.coordinate_confidence IN ('authoritative', 'statistical');
