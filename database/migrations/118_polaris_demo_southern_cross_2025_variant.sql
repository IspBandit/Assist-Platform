-- POL-002: demo 2025 Southern Cross variant so model-year selector has year-scoped specs.
-- Year row already exists from migration 103; this only adds a published variant.

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, '18ft Island Bed', '18ft-island-bed-2025', 'Island bed, rear ensuite, club lounge (2025)',
       2, 5.48, 7.15, 1820, 2450, 2300, 175, 180, 100, 300, 180,
       'Ensuite', 'Internal', 'from', 8490000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2025
WHERE m.slug = 'southern-cross' AND m.is_demo = 1
  AND NOT EXISTS (
      SELECT 1 FROM polaris_rv_variants v
      WHERE v.model_id = m.id AND v.model_year_id = y.id AND v.slug = '18ft-island-bed-2025'
  );
