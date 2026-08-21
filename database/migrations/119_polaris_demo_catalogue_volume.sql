-- POL-002: expand Polaris demonstration catalogue volume (is_demo = 1 only).
-- Not national production catalogue data. Idempotent inserts.

INSERT INTO polaris_manufacturers
    (brand_id, legal_name, trading_name, slug, website_url, description, claim_status, verification_status, publication_status, lifecycle_status, is_demo, last_reviewed_at, created_at)
SELECT 5, 'Demo Alpine Family Caravans Pty Ltd', 'Demo Alpine Family', 'demo-alpine-family',
       'https://example.invalid/demo-alpine-family',
       'Demonstration family-touring manufacturer for local Polaris development. Not a real business.',
       'unclaimed', 'unverified', 'published', 'active', 1, NULL, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM polaris_manufacturers WHERE slug = 'demo-alpine-family' AND brand_id = 5
);

INSERT INTO polaris_data_sources
    (brand_id, source_type, title, url, retrieved_at, authority, review_status, is_demo, created_at)
SELECT 5, 'brochure', 'Demo Alpine Family 2026 brochure (fixture)',
       'https://example.invalid/demo-alpine-family/brochure', CURDATE(), 'public', 'accepted', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM polaris_data_sources
    WHERE brand_id = 5 AND title = 'Demo Alpine Family 2026 brochure (fixture)' AND is_demo = 1
);

-- Horizon: family bunkhouse
INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, last_reviewed_at, created_at)
SELECT 5, id, 'Family Bunkhouse', 'family-bunkhouse', 'caravan',
       'Demo family bunk caravan with rear bunks and club lounge. Fixture data only.',
       'current', 2026, 'verified', 'published', 'active', 1, NOW(), NOW()
FROM polaris_manufacturers
WHERE slug = 'demo-horizon' AND brand_id = 5
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_models WHERE slug = 'family-bunkhouse' AND brand_id = 5 AND is_demo = 1
  );

-- Horizon: lighter tourer
INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, last_reviewed_at, created_at)
SELECT 5, id, 'Tour Lite', 'tour-lite', 'caravan',
       'Demo lighter-ATM couple tourer for mid-size tow vehicles. Fixture data only.',
       'current', 2026, 'verified', 'published', 'active', 1, NOW(), NOW()
FROM polaris_manufacturers
WHERE slug = 'demo-horizon' AND brand_id = 5
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_models WHERE slug = 'tour-lite' AND brand_id = 5 AND is_demo = 1
  );

-- Outback: desert hybrid
INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Desert Trek', 'desert-trek', 'hybrid_caravan',
       'Demo high-solar hybrid for remote touring. Fixture data only.',
       'current', 2026, 'verified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers
WHERE slug = 'demo-outback-hybrids' AND brand_id = 5
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_models WHERE slug = 'desert-trek' AND brand_id = 5 AND is_demo = 1
  );

-- Alpine Family: snowline explorer
INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Snowline Explorer', 'snowline-explorer', 'caravan',
       'Demo insulated family caravan for alpine getaways. Fixture data only.',
       'current', 2026, 'unverified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers
WHERE slug = 'demo-alpine-family' AND brand_id = 5
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_models WHERE slug = 'snowline-explorer' AND brand_id = 5 AND is_demo = 1
  );

-- Alpine Family: kids bunk
INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Kids Bunk 21', 'kids-bunk-21', 'caravan',
       'Demo 21ft bunk layout for school-holiday travel. Fixture data only.',
       'current', 2025, 'verified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers
WHERE slug = 'demo-alpine-family' AND brand_id = 5
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_models WHERE slug = 'kids-bunk-21' AND brand_id = 5 AND is_demo = 1
  );

-- Horizon: pop-top weekender
INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Pop Top Weekender', 'pop-top-weekender', 'camper_trailer',
       'Demo pop-top for short coastal weekends. Fixture data only.',
       'current', 2025, 'verified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers
WHERE slug = 'demo-horizon' AND brand_id = 5
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_models WHERE slug = 'pop-top-weekender' AND brand_id = 5 AND is_demo = 1
  );

INSERT INTO polaris_rv_model_years (model_id, model_year, production_status, brochure_label, publication_status, lifecycle_status, created_at)
SELECT id, 2026, 'current', '2026 demo brochure', 'published', 'active', NOW()
FROM polaris_rv_models m
WHERE m.slug IN ('family-bunkhouse', 'tour-lite', 'desert-trek', 'snowline-explorer', 'kids-bunk-21', 'pop-top-weekender')
  AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_model_years y WHERE y.model_id = m.id AND y.model_year = 2026
  );

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, 'Bunk Family', 'bunk-family', 'Front island bed, mid club lounge, rear bunks',
       6, 6.40, 8.10, 2280, 3000, 2850, 220, 200, 120, 400, 200,
       'Ensuite', 'Internal', 'from', 10490000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'family-bunkhouse' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.slug = 'bunk-family'
  );

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, 'Couple Pack', 'couple-pack', 'East-west bed, compact ensuite',
       2, 4.90, 6.60, 1520, 2100, 1980, 150, 140, 90, 300, 150,
       'Ensuite', 'Internal', 'from', 7490000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'tour-lite' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.slug = 'couple-pack'
  );

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, 'Remote Pack', 'remote-pack', 'East-west bed, external kitchen, high solar',
       3, 5.40, 7.00, 1750, 2500, 2350, 190, 260, 160, 800, 400,
       'Combined', 'External', 'rrp', 12990000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'desert-trek' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.slug = 'remote-pack'
  );

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, 'Alpine Pack', 'alpine-pack', 'Island bed, insulated walls, rear ensuite',
       4, 6.10, 7.80, 2100, 2800, 2650, 200, 180, 110, 500, 250,
       'Ensuite', 'Internal', 'from', 11990000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'snowline-explorer' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.slug = 'alpine-pack'
  );

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, 'Triple Bunk', 'triple-bunk', 'Queen front, triple bunks rear',
       5, 6.50, 8.20, 2350, 3100, 2950, 230, 190, 120, 400, 200,
       'Ensuite', 'Internal', 'from', 10990000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'kids-bunk-21' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.slug = 'triple-bunk'
  );

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, fresh_water_l, bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, 'Soft Top', 'soft-top', 'Pop-top soft walls, external kitchen',
       4, 3.60, 4.90, 920, 1350, 70, 'None', 'External', 'from', 3890000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'pop-top-weekender' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.slug = 'soft-top'
  );

INSERT INTO polaris_floorplans
    (model_id, variant_id, title, accessible_description, bed_configuration, bathroom_position, kitchen_position, seating_configuration, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT m.id, v.id, 'Family bunk layout',
       'Entry mid-body. Island bed toward the front, club lounge opposite the kitchen, triple bunks across the rear with ensuite beside.',
       'Island bed + bunks', 'Rear', 'Internal roadside', 'Club lounge',
       'verified', 'published', 'active', 1, NOW()
FROM polaris_rv_models m
JOIN polaris_rv_variants v ON v.model_id = m.id AND v.slug = 'bunk-family'
WHERE m.slug = 'family-bunkhouse' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_floorplans f WHERE f.model_id = m.id AND f.title = 'Family bunk layout' AND f.is_demo = 1
  );

-- Link new models to brochure sources when provenance tables exist (migration 115).
INSERT INTO polaris_model_sources (model_id, source_id, is_primary, created_at)
SELECT m.id, s.id, 1, NOW()
FROM polaris_rv_models m
CROSS JOIN polaris_data_sources s
WHERE m.slug IN ('family-bunkhouse', 'tour-lite', 'pop-top-weekender')
  AND m.is_demo = 1 AND m.brand_id = 5
  AND s.brand_id = 5 AND s.is_demo = 1
  AND s.title = 'Demo Horizon 2026 brochure (fixture)'
  AND NOT EXISTS (
      SELECT 1 FROM polaris_model_sources ms WHERE ms.model_id = m.id AND ms.source_id = s.id
  );

INSERT INTO polaris_model_sources (model_id, source_id, is_primary, created_at)
SELECT m.id, s.id, 1, NOW()
FROM polaris_rv_models m
CROSS JOIN polaris_data_sources s
WHERE m.slug IN ('snowline-explorer', 'kids-bunk-21')
  AND m.is_demo = 1 AND m.brand_id = 5
  AND s.brand_id = 5 AND s.is_demo = 1
  AND s.title = 'Demo Alpine Family 2026 brochure (fixture)'
  AND NOT EXISTS (
      SELECT 1 FROM polaris_model_sources ms WHERE ms.model_id = m.id AND ms.source_id = s.id
  );
