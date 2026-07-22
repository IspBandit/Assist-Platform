-- Forward-only brand rename. Brand id 2 remains stable so all foreign keys,
-- memberships, listings, email attribution, and audit history remain intact.
UPDATE brands
SET brand_key = 'towsmart',
    name = 'TowSmart',
    legal_name = CASE WHEN legal_name = 'TowWise' THEN 'TowSmart' ELSE legal_name END,
    updated_at = NOW()
WHERE id = 2 AND brand_key = 'towwise';

-- storage_namespace intentionally remains `towwise` so existing object keys
-- and external storage integrations continue to resolve after the rename.

-- Rename any previously seeded local/production hostnames without deleting
-- deployment records. Real custom hostnames not matching the legacy brand are
-- deliberately left untouched.
UPDATE brand_domains
SET hostname = REPLACE(hostname, 'towwise', 'towsmart'),
    updated_at = NOW()
WHERE brand_id = 2 AND hostname LIKE '%towwise%';
