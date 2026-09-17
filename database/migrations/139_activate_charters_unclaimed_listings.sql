-- VAN-011 follow-up: Charters Towers unclaimed imports were stuck as pending,
-- so Ask radius SQL (p.status='active') could not return them even after
-- coordinate backfill and Places refrigeration merge.

UPDATE providers p
INNER JOIN towns t ON t.id = p.base_town_id
INNER JOIN states s ON s.id = t.state_id
SET
    p.status = 'active',
    p.updated_at = NOW()
WHERE p.deleted_at IS NULL
  AND p.is_unclaimed = 1
  AND p.status IN ('pending', 'draft')
  AND s.abbreviation = 'QLD'
  AND t.slug = 'charters-towers';

UPDATE provider_brand_listings pbl
INNER JOIN providers p ON p.id = pbl.provider_id
INNER JOIN towns t ON t.id = p.base_town_id
INNER JOIN states s ON s.id = t.state_id
SET
    pbl.status = 'active',
    pbl.search_visible = 1,
    pbl.updated_at = NOW()
WHERE p.deleted_at IS NULL
  AND p.is_unclaimed = 1
  AND pbl.status IN ('draft', 'pending')
  AND s.abbreviation = 'QLD'
  AND t.slug = 'charters-towers';
