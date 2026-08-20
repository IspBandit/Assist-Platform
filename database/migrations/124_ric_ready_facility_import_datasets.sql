-- Assist RIC ready free packs: ensure government_datasets keys exist for
-- POST /api/v1/admin/facility-imports staging. Review-first; not auto-published.

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key,
    v.publisher,
    v.title,
    v.coverage,
    v.jurisdiction,
    v.record_types_json,
    v.duplicate_rules_json,
    v.licence,
    v.attribution,
    v.trust_policy,
    v.fetch_method,
    v.source_format,
    v.update_frequency,
    v.connector_key,
    v.endpoint_url,
    v.source_url,
    v.settings_json,
    v.default_facility_type,
    v.is_enabled,
    v.auto_update_enabled,
    v.catalogue_status,
    v.notes,
    v.created_at
FROM (
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
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_national_formal_rest_areas'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'wa_minor_rest_areas' AS dataset_key,
        'Main Roads Western Australia' AS publisher,
        'WA Minor Rest Areas' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© Main Roads Western Australia — Minor Rest Area' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/mrwa-minor-rest-area' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_minor_rest_areas'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'wa_heavy_vehicle_rest_areas' AS dataset_key,
        'Main Roads Western Australia' AS publisher,
        'WA Heavy Vehicle Rest Areas' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© Main Roads Western Australia — Heavy Vehicle Rest Area' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/mrwa-heavy-vehicle-rest-area' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_heavy_vehicle_rest_areas'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'wa_road_stopping_places' AS dataset_key,
        'Main Roads Western Australia' AS publisher,
        'WA Road Stopping Places' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© Main Roads Western Australia — Road Stopping Places' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/mrwa-road-stopping-places' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_road_stopping_places'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'wa_public_boat_ramps' AS dataset_key,
        'Department of Transport (WA)' AS publisher,
        'WA Public Boat Ramps' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© Government of Western Australia — DOT-033 Boat Ramps' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'SHP' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://catalogue.data.wa.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_public_boat_ramps'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'gold_coast_boat_ramps' AS dataset_key,
        'City of Gold Coast' AS publisher,
        'Gold Coast Boat Ramps' AS title,
        'QLD — City of Gold Coast' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© City of Gold Coast — Boat Ramps' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/gold-coast-boat-ramps' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'gold_coast_boat_ramps'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'geelong_boat_ramps' AS dataset_key,
        'City of Greater Geelong' AS publisher,
        'Geelong Boat Ramps' AS title,
        'VIC — Greater Geelong' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© City of Greater Geelong — Boat Ramps' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/boat-ramps-greater-geelong' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'geelong_boat_ramps'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'geelong_caravan_parks' AS dataset_key,
        'City of Greater Geelong' AS publisher,
        'Geelong Caravan Parks' AS title,
        'VIC — Greater Geelong' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('caravan_park') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© City of Greater Geelong — Caravan Parks' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'CSV' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/geelong-caravan-parks' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'other_essential' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Staged as facility candidates for human review.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'geelong_caravan_parks'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'shepparton_caravan_parking' AS dataset_key,
        'City of Greater Shepparton' AS publisher,
        'Shepparton Caravan Parking' AS title,
        'VIC — Greater Shepparton' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('caravan_parking') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© City of Greater Shepparton' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'CSV' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'other_essential' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'shepparton_caravan_parking'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'wa_fuelwatch_rss' AS dataset_key,
        'FuelWatch WA' AS publisher,
        'WA FuelWatch RSS' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('fuel_station') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© Government of Western Australia — FuelWatch' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'RSS' AS source_format,
        'daily' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://www.fuelwatch.wa.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'fuel' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_fuelwatch_rss'
);

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, jurisdiction, record_types_json,
     duplicate_rules_json, licence, attribution, trust_policy, fetch_method,
     source_format, update_frequency, connector_key, endpoint_url, source_url,
     settings_json, default_facility_type, is_enabled, auto_update_enabled,
     catalogue_status, notes, created_at)
SELECT
    v.dataset_key, v.publisher, v.title, v.coverage, v.jurisdiction, v.record_types_json,
    v.duplicate_rules_json, v.licence, v.attribution, v.trust_policy, v.fetch_method,
    v.source_format, v.update_frequency, v.connector_key, v.endpoint_url, v.source_url,
    v.settings_json, v.default_facility_type, v.is_enabled, v.auto_update_enabled,
    v.catalogue_status, v.notes, v.created_at
FROM (
    SELECT
        'qld_fuel_price_reporting_monthly' AS dataset_key,
        'Queensland Government' AS publisher,
        'QLD Fuel Price Reporting (monthly)' AS title,
        'QLD' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('fuel_station') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('name_locality', 'geo_proximity')) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© The State of Queensland' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'CSV' AS source_format,
        'monthly' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://www.data.qld.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack') AS settings_json,
        'fuel' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Large monthly extracts; chunked facility-imports.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'qld_fuel_price_reporting_monthly'
);

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
