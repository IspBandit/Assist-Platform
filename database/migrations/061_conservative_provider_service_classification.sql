-- Remove unsupported service guesses from imported/unclaimed listings while
-- preserving every claimed provider's self-managed service catalogue.

DELETE ps
FROM provider_services ps
INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed = 1
  AND ps.is_inferred = 1;

-- Locality labels were historically copied into street_address. They describe
-- coverage, not a routable premises, and must not create broken map directions.
UPDATE providers p
INNER JOIN towns t ON t.id = p.base_town_id
SET p.street_address = NULL,
    p.updated_at = NOW()
WHERE p.is_unclaimed = 1
  AND p.source_type = 'locality'
  AND LOWER(TRIM(p.street_address)) = LOWER(TRIM(t.name));

-- Exact-name classifiers for business types that coarse public datasets often
-- mislabel as general mechanical or trade services.
DELETE ps FROM provider_services ps INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed = 1 AND LOWER(p.business_name) REGEXP 'battery world|(^|[^a-z])batter(y|ies)([^a-z]|$)';
INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at)
SELECT p.id, c.id, 0, NOW() FROM providers p JOIN service_categories c ON c.slug='auto-electrical-and-batteries'
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'battery world|(^|[^a-z])batter(y|ies)([^a-z]|$)';

DELETE ps FROM provider_services ps INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'windscreen|auto glass|automotive glass';
INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at)
SELECT p.id,c.id,0,NOW() FROM providers p JOIN service_categories c ON c.slug='windscreen-and-auto-glass'
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'windscreen|auto glass|automotive glass';

DELETE ps FROM provider_services ps INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'supercheap|autopro|auto parts|parts store|parts centre';
INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at)
SELECT p.id,c.id,0,NOW() FROM providers p JOIN service_categories c ON c.slug='vehicle-parts-and-accessories'
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'supercheap|autopro|auto parts|parts store|parts centre';

DELETE ps FROM provider_services ps INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'tyre|tire|tyrepower|bob jane|bridgestone|goodyear';
INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at)
SELECT p.id,c.id,0,NOW() FROM providers p JOIN service_categories c ON c.slug='tyres-and-wheels'
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'tyre|tire|tyrepower|bob jane|bridgestone|goodyear';

DELETE ps FROM provider_services ps INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'petroleum|service station|fuel stop|ampol|caltex|7-eleven';
INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at)
SELECT p.id,c.id,0,NOW() FROM providers p JOIN service_categories c ON c.slug='fuel-and-travel-stops'
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'petroleum|service station|fuel stop|ampol|caltex|7-eleven';

DELETE ps FROM provider_services ps INNER JOIN providers p ON p.id = ps.provider_id
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'elgas|lpg refill|gas bottle|bottle exchange';
INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at)
SELECT p.id,c.id,0,NOW() FROM providers p JOIN service_categories c ON c.slug='lpg-refills-and-bottle-exchange'
WHERE p.is_unclaimed=1 AND LOWER(p.business_name) REGEXP 'elgas|lpg refill|gas bottle|bottle exchange';
