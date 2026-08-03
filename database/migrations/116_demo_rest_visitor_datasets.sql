-- Migration 100: Demo rest-area + visitor-information fixture catalogue rows (DATA-012 priorities 4–5).
-- Review-first; disabled by default. Never writes caravan_parks.
-- LPG/fuel intentionally omitted — no licensed connector path yet (deferred; see DATA_012.md).

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, record_types_json, licence, attribution, trust_policy, fetch_method, connector_key, endpoint_url, settings_json, default_facility_type, is_enabled, created_at)
VALUES
(
    'demo_csv_rest_areas',
    'Assist Platform (fixture)',
    'Demo rest areas CSV (local fixture)',
    'AU demo',
    JSON_ARRAY('rest_area'),
    'internal-demo',
    'Demonstration fixture — not an official government dataset',
    'trusted_review',
    'csv',
    'gov_csv',
    NULL,
    JSON_OBJECT('default_facility_type', 'rest_area', 'name_field', 'name', 'type_field', 'facility_type', 'id_field', 'id', 'lat_field', 'latitude', 'lng_field', 'longitude', 'address_field', 'address'),
    'rest_area',
    0,
    NOW()
),
(
    'demo_csv_visitor_information',
    'Assist Platform (fixture)',
    'Demo visitor information CSV (local fixture)',
    'AU demo',
    JSON_ARRAY('visitor_information'),
    'internal-demo',
    'Demonstration fixture — not an official government dataset',
    'trusted_review',
    'csv',
    'gov_csv',
    NULL,
    JSON_OBJECT('default_facility_type', 'visitor_information', 'name_field', 'name', 'type_field', 'facility_type', 'id_field', 'id', 'lat_field', 'latitude', 'lng_field', 'longitude', 'address_field', 'address'),
    'visitor_information',
    0,
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    settings_json = VALUES(settings_json),
    updated_at = NOW();
