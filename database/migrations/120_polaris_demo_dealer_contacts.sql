-- POL-008: demo dealer contact channels + link to Demo Horizon for model-page handoff.
-- example.invalid only — not real dealer contact data.

UPDATE polaris_dealers
SET email = 'demo-outback@example.invalid',
    website_url = 'https://example.invalid/demo-outback-rv-centre',
    updated_at = NOW()
WHERE slug = 'demo-outback-rv-centre' AND brand_id = 5 AND is_demo = 1
  AND (email IS NULL OR email = '');

UPDATE polaris_dealers
SET email = 'demo-coastal@example.invalid',
    website_url = 'https://example.invalid/demo-coastal-caravans',
    updated_at = NOW()
WHERE slug = 'demo-coastal-caravans' AND brand_id = 5 AND is_demo = 1
  AND (email IS NULL OR email = '');

INSERT INTO polaris_manufacturer_dealers (manufacturer_id, dealer_id, is_primary, brands_represented, created_at)
SELECT m.id, d.id, 1, 'Demo Horizon', NOW()
FROM polaris_manufacturers m
CROSS JOIN polaris_dealers d
WHERE m.slug = 'demo-horizon' AND m.brand_id = 5 AND m.is_demo = 1
  AND d.slug = 'demo-outback-rv-centre' AND d.brand_id = 5 AND d.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_manufacturer_dealers md
      WHERE md.manufacturer_id = m.id AND md.dealer_id = d.id
  );

INSERT INTO polaris_manufacturer_dealers (manufacturer_id, dealer_id, is_primary, brands_represented, created_at)
SELECT m.id, d.id, 0, 'Demo Horizon', NOW()
FROM polaris_manufacturers m
CROSS JOIN polaris_dealers d
WHERE m.slug = 'demo-horizon' AND m.brand_id = 5 AND m.is_demo = 1
  AND d.slug = 'demo-coastal-caravans' AND d.brand_id = 5 AND d.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_manufacturer_dealers md
      WHERE md.manufacturer_id = m.id AND md.dealer_id = d.id
  );
