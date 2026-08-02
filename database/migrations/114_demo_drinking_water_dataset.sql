-- Migration 098: Demo drinking-water fixture catalogue row (DATA-012 S1 priority 3).
-- Review-first; disabled by default. Never writes caravan_parks.

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, record_types_json, licence, attribution, trust_policy, fetch_method, connector_key, endpoint_url, settings_json, default_facility_type, is_enabled, created_at)
VALUES
(
    'demo_csv_drinking_water',
    'Assist Platform (fixture)',
    'Demo drinking water CSV (local fixture)',
    'AU demo',
    JSON_ARRAY('drinking_water'),
    'internal-demo',
    'Demonstration fixture — not an official government dataset',
    'trusted_review',
    'csv',
    'gov_csv',
    NULL,
    JSON_OBJECT('default_facility_type', 'drinking_water', 'name_field', 'name', 'type_field', 'facility_type', 'id_field', 'id', 'lat_field', 'latitude', 'lng_field', 'longitude', 'address_field', 'address'),
    'drinking_water',
    0,
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    settings_json = VALUES(settings_json),
    updated_at = NOW();
