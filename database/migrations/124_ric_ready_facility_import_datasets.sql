-- Assist RIC ready free packs: ensure government_datasets keys exist for
-- POST /api/v1/admin/facility-imports staging. Review-first; not auto-published.

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'au_national_formal_rest_areas' AS dataset_key,
        'National Rest Areas Australia' AS publisher,
        'National Formal Rest Areas' AS title,
        'AU national' AS coverage,
        'AU' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'National Rest Area dataset attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'CSV' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        NULL AS endpoint_url,
        'https://data.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_national_formal_rest_areas'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'wa_minor_rest_areas','Main Roads Western Australia','WA Minor Rest Areas',
        'WA','WA', JSON_ARRAY('rest_area'),
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50),
        'CC BY 3.0 AU','© Main Roads Western Australia — Minor Rest Area','trusted_review',
        'url','GeoJSON','as published','assist_ric_package',NULL,
        'https://data.gov.au/data/dataset/mrwa-minor-rest-area',
        JSON_OBJECT('role','ric_ready_pack'),'rest_area',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_minor_rest_areas'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'wa_heavy_vehicle_rest_areas','Main Roads Western Australia','WA Heavy Vehicle Rest Areas',
        'WA','WA', JSON_ARRAY('rest_area'),
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50),
        'CC BY 3.0 AU','© Main Roads Western Australia — Heavy Vehicle Rest Area','trusted_review',
        'url','GeoJSON','as published','assist_ric_package',NULL,
        'https://data.gov.au/data/dataset/mrwa-heavy-vehicle-rest-area',
        JSON_OBJECT('role','ric_ready_pack'),'rest_area',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_heavy_vehicle_rest_areas'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'wa_road_stopping_places','Main Roads Western Australia','WA Road Stopping Places',
        'WA','WA', JSON_ARRAY('rest_area'),
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50),
        'CC BY 3.0 AU','© Main Roads Western Australia — Road Stopping Places','trusted_review',
        'url','GeoJSON','as published','assist_ric_package',NULL,
        'https://data.gov.au/data/dataset/mrwa-road-stopping-places',
        JSON_OBJECT('role','ric_ready_pack'),'rest_area',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_road_stopping_places'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'wa_public_boat_ramps','Department of Transport (WA)','WA Public Boat Ramps',
        'WA','WA', JSON_ARRAY('boat_ramp'),
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40),
        'CC BY 4.0','© Government of Western Australia — DOT-033 Boat Ramps','trusted_review',
        'url','SHP','as published','assist_ric_package',NULL,
        'https://catalogue.data.wa.gov.au/',
        JSON_OBJECT('role','ric_ready_pack'),'boat_ramp',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_public_boat_ramps'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'gold_coast_boat_ramps','City of Gold Coast','Gold Coast Boat Ramps',
        'QLD — City of Gold Coast','QLD', JSON_ARRAY('boat_ramp'),
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40),
        'CC BY 3.0 AU','© City of Gold Coast — Boat Ramps','trusted_review',
        'url','GeoJSON','as published','assist_ric_package',NULL,
        'https://data.gov.au/data/dataset/gold-coast-boat-ramps',
        JSON_OBJECT('role','ric_ready_pack'),'boat_ramp',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'gold_coast_boat_ramps'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'geelong_boat_ramps','City of Greater Geelong','Geelong Boat Ramps',
        'VIC — Greater Geelong','VIC', JSON_ARRAY('boat_ramp'),
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40),
        'CC BY 3.0 AU','© City of Greater Geelong — Boat Ramps','trusted_review',
        'url','GeoJSON','as published','assist_ric_package',NULL,
        'https://data.gov.au/data/dataset/boat-ramps-greater-geelong',
        JSON_OBJECT('role','ric_ready_pack'),'boat_ramp',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'geelong_boat_ramps'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'geelong_caravan_parks','City of Greater Geelong','Geelong Caravan Parks',
        'VIC — Greater Geelong','VIC', JSON_ARRAY('caravan_park'),
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')),
        'CC BY 3.0 AU','© City of Greater Geelong — Caravan Parks','trusted_review',
        'url','CSV','as published','assist_ric_package',NULL,
        'https://data.gov.au/data/dataset/geelong-caravan-parks',
        JSON_OBJECT('role','ric_ready_pack'),'other_essential',1,0,'indexed',
        'Assist RIC ready pack. Staged as facility candidates for human review.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'geelong_caravan_parks'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'shepparton_caravan_parking','City of Greater Shepparton','Shepparton Caravan Parking',
        'VIC — Greater Shepparton','VIC', JSON_ARRAY('caravan_parking'),
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')),
        'CC BY 4.0','© City of Greater Shepparton','trusted_review',
        'url','CSV','as published','assist_ric_package',NULL,
        'https://data.gov.au/',
        JSON_OBJECT('role','ric_ready_pack'),'other_essential',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'shepparton_caravan_parking'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'wa_fuelwatch_rss','FuelWatch WA','WA FuelWatch RSS',
        'WA','WA', JSON_ARRAY('fuel_station'),
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')),
        'CC BY 4.0','© Government of Western Australia — FuelWatch','trusted_review',
        'url','RSS','daily','assist_ric_package',NULL,
        'https://www.fuelwatch.wa.gov.au/',
        JSON_OBJECT('role','ric_ready_pack'),'fuel',1,0,'indexed',
        'Assist RIC ready pack. Ingest via /facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_fuelwatch_rss'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT * FROM (
    SELECT
        'qld_fuel_price_reporting_monthly','Queensland Government','QLD Fuel Price Reporting (monthly)',
        'QLD','QLD', JSON_ARRAY('fuel_station'),
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')),
        'CC BY 4.0','© The State of Queensland','trusted_review',
        'url','CSV','monthly','assist_ric_package',NULL,
        'https://www.data.qld.gov.au/',
        JSON_OBJECT('role','ric_ready_pack'),'fuel',1,0,'indexed',
        'Assist RIC ready pack. Large monthly extracts; chunked facility-imports.',NOW()
) AS seed WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'qld_fuel_price_reporting_monthly'
);

-- Enable known RIC-ready keys that already exist from 123_* but were inserted disabled.
UPDATE government_datasets
SET is_enabled = 1,
    notes = CONCAT(COALESCE(notes, ''), ' Enabled for Assist RIC facility-imports staging.')
WHERE dataset_key IN (
    'au_national_public_toilet_map',
    'nsw_rest_areas',
    'nsw_boat_ramps',
    'nsw_ev_charging_locations',
    'gold_coast_caravan_parks',
    'sa_rest_areas_state_maintained',
    'wa_major_rest_areas'
)
AND is_enabled = 0;
