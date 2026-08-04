-- Extend the National Public Toilet Map catalogue to its published drinking-water
-- and shower attributes. All rows remain disabled and review-first.

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
VALUES
(
    'au_national_toilet_map_drinking_water',
    'Australian Government (data.gov.au)',
    'National Public Toilet Map — drinking water flag rows',
    'AU national', 'AU', JSON_ARRAY('drinking_water'),
    JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 25),
    'CC BY 3.0 AU',
    '© Commonwealth of Australia — National Public Toilet Map (data.gov.au)',
    'trusted_review', 'ckan', 'CSV', 'irregular', 'gov_ckan',
    'https://data.gov.au/data/dataset/national-public-toilet-map',
    'https://data.gov.au/data/dataset/national-public-toilet-map',
    JSON_OBJECT(
        'package_api_url', 'https://data.gov.au/data/api',
        'resource_id', '34076296-6692-4e30-b627-67b7c4eb1027',
        'format', 'csv', 'name_field', 'name', 'id_field', 'facilityid',
        'lat_field', 'latitude', 'lng_field', 'longitude', 'address_field', 'address1',
        'filter_field', 'drinkingwater', 'filter_value', 'true',
        'default_facility_type', 'drinking_water', 'limit', 25000
    ),
    'drinking_water', 0, 0, 'indexed',
    'Review-first extraction of records whose DrinkingWater attribute is true.', NOW()
),
(
    'au_national_toilet_map_showers',
    'Australian Government (data.gov.au)',
    'National Public Toilet Map — shower flag rows',
    'AU national', 'AU', JSON_ARRAY('public_shower'),
    JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 25),
    'CC BY 3.0 AU',
    '© Commonwealth of Australia — National Public Toilet Map (data.gov.au)',
    'trusted_review', 'ckan', 'CSV', 'irregular', 'gov_ckan',
    'https://data.gov.au/data/dataset/national-public-toilet-map',
    'https://data.gov.au/data/dataset/national-public-toilet-map',
    JSON_OBJECT(
        'package_api_url', 'https://data.gov.au/data/api',
        'resource_id', '34076296-6692-4e30-b627-67b7c4eb1027',
        'format', 'csv', 'name_field', 'name', 'id_field', 'facilityid',
        'lat_field', 'latitude', 'lng_field', 'longitude', 'address_field', 'address1',
        'filter_field', 'shower', 'filter_value', 'true',
        'default_facility_type', 'public_shower', 'limit', 25000
    ),
    'public_shower', 0, 0, 'indexed',
    'Review-first extraction of records whose Shower attribute is true.', NOW()
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title), settings_json = VALUES(settings_json),
    source_url = VALUES(source_url), catalogue_status = VALUES(catalogue_status),
    notes = VALUES(notes), updated_at = NOW();

UPDATE government_datasets
SET settings_json = JSON_SET(settings_json, '$.limit', 25000), updated_at = NOW()
WHERE dataset_key IN ('au_national_public_toilet_map', 'au_national_toilet_map_dump_points');
