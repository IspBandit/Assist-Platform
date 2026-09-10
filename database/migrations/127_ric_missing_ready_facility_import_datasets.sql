-- Assist RIC: INSERT government_datasets rows that migration 124 only tried to
-- UPDATE (they never existed). Required for POST /facility-imports.
-- Review-first staging; not auto-published.

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
        'nsw_rest_areas' AS dataset_key,
        'Transport for NSW' AS publisher,
        'NSW Rest Areas' AS title,
        'NSW' AS coverage,
        'NSW' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'Transport for NSW open data attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://opendata.transport.nsw.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'nsw_rest_areas'
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
        'nsw_ev_charging_locations' AS dataset_key,
        'Transport for NSW' AS publisher,
        'NSW EV Charging Locations' AS title,
        'NSW' AS coverage,
        'NSW' AS jurisdiction,
        JSON_ARRAY('ev_charging') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'Transport for NSW open data attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://opendata.transport.nsw.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'ev_charging') AS settings_json,
        'ev_charging' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'nsw_ev_charging_locations'
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
        'sa_rest_areas_state_maintained' AS dataset_key,
        'Department for Infrastructure and Transport (SA)' AS publisher,
        'SA State Maintained Rest Areas' AS title,
        'SA' AS coverage,
        'SA' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'SA Government open data attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'SHP' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.sa.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'sa_rest_areas_state_maintained'
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
        'wa_major_rest_areas' AS dataset_key,
        'Main Roads Western Australia' AS publisher,
        'WA Major Rest Areas' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'Main Roads WA open data attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://portal-mainroads.opendata.arcgis.com/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_major_rest_areas'
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
        'nsw_boat_ramps' AS dataset_key,
        'Transport for NSW' AS publisher,
        'NSW Boat Ramps' AS title,
        'NSW' AS coverage,
        'NSW' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'Transport for NSW open data attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://opendata.transport.nsw.gov.au/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'boat_ramp') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'nsw_boat_ramps'
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
        'gold_coast_caravan_parks' AS dataset_key,
        'City of Gold Coast' AS publisher,
        'Gold Coast Caravan Parks' AS title,
        'Gold Coast QLD' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('caravan_park') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        'City of Gold Coast open data attribution as published' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data-goldcoast.opendata.arcgis.com/' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'caravan_park') AS settings_json,
        'caravan_park' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'gold_coast_caravan_parks'
);

UPDATE government_datasets
SET is_enabled = 1,
    notes = CONCAT(COALESCE(notes, ''), ' Enabled for Assist RIC facility-imports staging.')
WHERE dataset_key IN (
    'nsw_rest_areas',
    'nsw_ev_charging_locations',
    'sa_rest_areas_state_maintained',
    'wa_major_rest_areas',
    'nsw_boat_ramps',
    'gold_coast_caravan_parks',
    'au_national_public_toilet_map'
)
AND is_enabled = 0;
