-- Rename the TowWise brand without changing its stable numeric ID or any
-- provider, campaign, identity or calculation relationships.

UPDATE brands
SET brand_key = 'towsmart',
    name = 'TowSmart',
    legal_name = 'TowSmart',
    updated_at = NOW()
WHERE id = 2 AND brand_key = 'towwise';

DELETE FROM brand_domains WHERE brand_id = 2 AND hostname IN ('towwise.test', 'towwise.com.au', 'www.towwise.com.au');

-- These production domains have been purchased. Preserve any legacy/staging
-- hostnames, but make the purchased domains the canonical production hosts.
UPDATE brand_domains SET is_primary = 0, updated_at = NOW() WHERE brand_id IN (1, 2, 3);

INSERT INTO brand_domains (brand_id, hostname, environment, is_primary, verified_at, created_at)
VALUES
    (1, 'vanassist.com.au', 'production', 1, NULL, NOW()),
    (1, 'www.vanassist.com.au', 'production', 0, NULL, NOW()),
    (1, 'vanassist.test', 'local', 0, NULL, NOW()),
    (2, 'towsmart.com.au', 'production', 1, NULL, NOW()),
    (2, 'www.towsmart.com.au', 'production', 0, NULL, NOW()),
    (2, 'towsmart.test', 'local', 0, NULL, NOW()),
    (3, 'trailerwise.com.au', 'production', 1, NULL, NOW()),
    (3, 'www.trailerwise.com.au', 'production', 0, NULL, NOW()),
    (3, 'trailerwise.test', 'local', 0, NULL, NOW())
ON DUPLICATE KEY UPDATE brand_id = VALUES(brand_id), environment = VALUES(environment), is_primary = VALUES(is_primary), updated_at = NOW();
