-- DATA-012 follow-on: curated Australian open-data catalogue rows (disabled).
-- National Public Toilet Map via stable CKAN resource_id (download URL rotates).
-- Review-first; operators must enable + Fetch + approve before Ask facilities.

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, record_types_json, licence, attribution, trust_policy, fetch_method, connector_key, endpoint_url, settings_json, default_facility_type, is_enabled, created_at)
VALUES
(
    'au_national_public_toilet_map',
    'Australian Government (data.gov.au)',
    'National Public Toilet Map (CSV via CKAN)',
    'AU national',
    JSON_ARRAY('public_toilet'),
    'CC BY 3.0 AU',
    '© Commonwealth of Australia — National Public Toilet Map (data.gov.au)',
    'trusted_review',
    'ckan',
    'gov_ckan',
    'https://data.gov.au/data/dataset/national-public-toilet-map',
    JSON_OBJECT(
        'package_api_url', 'https://data.gov.au/data/api',
        'resource_id', '34076296-6692-4e30-b627-67b7c4eb1027',
        'format', 'csv',
        'name_field', 'name',
        'id_field', 'facilityid',
        'lat_field', 'latitude',
        'lng_field', 'longitude',
        'address_field', 'address1',
        'type_field', 'facilitytype',
        'default_facility_type', 'public_toilet',
        'limit', 100
    ),
    'public_toilet',
    0,
    NOW()
),
(
    'au_national_toilet_map_dump_points',
    'Australian Government (data.gov.au)',
    'National Public Toilet Map — dump point flag rows',
    'AU national',
    JSON_ARRAY('dump_point'),
    'CC BY 3.0 AU',
    '© Commonwealth of Australia — National Public Toilet Map dump-point attributes (data.gov.au)',
    'trusted_review',
    'ckan',
    'gov_ckan',
    'https://data.gov.au/data/dataset/national-public-toilet-map',
    JSON_OBJECT(
        'package_api_url', 'https://data.gov.au/data/api',
        'resource_id', '34076296-6692-4e30-b627-67b7c4eb1027',
        'format', 'csv',
        'name_field', 'name',
        'id_field', 'facilityid',
        'lat_field', 'latitude',
        'lng_field', 'longitude',
        'address_field', 'address1',
        'filter_field', 'dumppoint',
        'filter_value', 'true',
        'default_facility_type', 'dump_point',
        'limit', 100
    ),
    'dump_point',
    0,
    NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    settings_json = VALUES(settings_json),
    attribution = VALUES(attribution),
    licence = VALUES(licence),
    updated_at = NOW();
