-- Assist RIC third-wave free packs: register government_datasets keys for
-- POST /api/v1/admin/facility-imports staging. Review-first; not auto-published.
-- DATA-011A / DATA-012 companion to assist-ric Ready packs (2026-08-05).

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
        'au_healthdirect_hospitals' AS dataset_key,
        'Geoscience Australia / HealthDirect' AS publisher,
        'National HealthDirect — Hospitals' AS title,
        'AU national' AS coverage,
        'AU' AS jurisdiction,
        JSON_ARRAY('hospital') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0 (Geoscience Australia; incorporates G-NAF)' AS licence,
        '© Commonwealth of Australia (Geoscience Australia) — National HealthDirect Health Facilities' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/national-healthdirect-health-facilities' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'hospital') AS settings_json,
        'hospital' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_healthdirect_hospitals'
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
        'au_healthdirect_pharmacies' AS dataset_key,
        'Geoscience Australia / HealthDirect' AS publisher,
        'National HealthDirect — Pharmacies' AS title,
        'AU national' AS coverage,
        'AU' AS jurisdiction,
        JSON_ARRAY('pharmacy') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0 (Geoscience Australia; incorporates G-NAF)' AS licence,
        '© Commonwealth of Australia (Geoscience Australia) — National HealthDirect Health Facilities' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/national-healthdirect-health-facilities' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'pharmacy') AS settings_json,
        'pharmacy' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_healthdirect_pharmacies'
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
        'au_healthdirect_general_practices' AS dataset_key,
        'Geoscience Australia / HealthDirect' AS publisher,
        'National HealthDirect — General Practices' AS title,
        'AU national' AS coverage,
        'AU' AS jurisdiction,
        JSON_ARRAY('medical_centre') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0 (Geoscience Australia; incorporates G-NAF)' AS licence,
        '© Commonwealth of Australia (Geoscience Australia) — National HealthDirect Health Facilities' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/national-healthdirect-health-facilities' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'medical_centre') AS settings_json,
        'medical_centre' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_healthdirect_general_practices'
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
        'qld_roadside_amenities' AS dataset_key,
        'Transport and Main Roads (Queensland)' AS publisher,
        'QLD TMR Roadside Amenities / Rest Areas' AS title,
        'QLD' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        '© State of Queensland (Transport and Main Roads) — confirm portal licence on use' AS licence,
        '© State of Queensland (Transport and Main Roads) — Roadside amenities' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Transportation/StateRoadInformation/MapServer/17' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'qld_roadside_amenities'
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
        'qld_operational_boat_facilities' AS dataset_key,
        'Transport and Main Roads (Queensland)' AS publisher,
        'QLD Operational Boat Facilities' AS title,
        'QLD' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        '© State of Queensland (Transport and Main Roads) — confirm portal licence on use' AS licence,
        '© State of Queensland (Transport and Main Roads) — Operational boat facilities' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Transportation/StateRoadInformation/MapServer/55' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'boat_ramp') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'qld_operational_boat_facilities'
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
        'qld_ev_super_highway' AS dataset_key,
        'Transport and Main Roads (Queensland)' AS publisher,
        'QLD EV Super Highway Charging Stations' AS title,
        'QLD' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('ev_charging') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© The State of Queensland (Transport and Main Roads) — EV Super Highway' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'CSV' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://www.data.qld.gov.au/dataset/find-a-charging-station-electric-vehicle' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'ev_charging') AS settings_json,
        'ev_charging' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Sparse Super Highway subset.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'qld_ev_super_highway'
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
        'tas_roadside_stops' AS dataset_key,
        'Department of State Growth (Tasmania)' AS publisher,
        'Tasmania Roadside Stops (State owned)' AS title,
        'TAS' AS coverage,
        'TAS' AS jurisdiction,
        JSON_ARRAY('rest_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'See LIST / State Growth attribution' AS licence,
        '© State of Tasmania — Roadside Stops (State owned)' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.stategrowth.tas.gov.au/ags/rest/services/PUBLIC/ROADSIDESTOPS/MapServer/0' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'rest_area') AS settings_json,
        'rest_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'tas_roadside_stops'
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
        'tas_boat_ramps' AS dataset_key,
        'Marine and Safety Tasmania / the LIST' AS publisher,
        'Tasmania Boat Ramps (MAST / LIST)' AS title,
        'TAS' AS coverage,
        'TAS' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'See the LIST licence / attribution' AS licence,
        '© State of Tasmania (the LIST) — Boat Ramps' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://services.thelist.tas.gov.au/arcgis/rest/services/Public/TopographyAndRelief/MapServer/33' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'boat_ramp') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'tas_boat_ramps'
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
        'wa_roadhouse' AS dataset_key,
        'Main Roads Western Australia' AS publisher,
        'WA Roadhouse' AS title,
        'WA' AS coverage,
        'WA' AS jurisdiction,
        JSON_ARRAY('fuel') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 3.0 AU' AS licence,
        '© Main Roads Western Australia — Roadhouse' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/mrwa-roadhouse' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'fuel') AS settings_json,
        'fuel' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'wa_roadhouse'
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
        'melbourne_public_barbecues' AS dataset_key,
        'City of Melbourne' AS publisher,
        'City of Melbourne Public Barbecues' AS title,
        'Melbourne' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('barbecue') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 30) AS duplicate_rules_json,
        'Other (Open) — City of Melbourne' AS licence,
        '© City of Melbourne — Public barbecues' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/public-barbecues' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'barbecue') AS settings_json,
        'barbecue' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'melbourne_public_barbecues'
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
        'noosa_public_barbecues' AS dataset_key,
        'Noosa Shire Council' AS publisher,
        'Noosa BBQ Locations' AS title,
        'Noosa' AS coverage,
        'QLD' AS jurisdiction,
        JSON_ARRAY('barbecue') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 30) AS duplicate_rules_json,
        'CC BY 2.5 AU' AS licence,
        '© Noosa Shire Council — BBQ Locations' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/noosa-bbq-locations' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'barbecue') AS settings_json,
        'barbecue' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'noosa_public_barbecues'
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
        'act_boat_ramp_assets' AS dataset_key,
        'ACT Government' AS publisher,
        'ACT Boat Ramp Assets' AS title,
        'ACT' AS coverage,
        'ACT' AS jurisdiction,
        JSON_ARRAY('boat_ramp') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'ACT open data terms (cite ACT Government)' AS licence,
        '© Australian Capital Territory — Boat Ramp Assets' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/actgov-boat-ramp-assets' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'boat_ramp', 'note', 'Upstream FeatureServer often returns null geometry') AS settings_json,
        'boat_ramp' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Geometry may be null upstream — review before publish.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'act_boat_ramp_assets'
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
        'act_public_toilet_assets' AS dataset_key,
        'ACT Government' AS publisher,
        'ACT Public Toilet Assets' AS title,
        'ACT' AS coverage,
        'ACT' AS jurisdiction,
        JSON_ARRAY('public_toilet') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40) AS duplicate_rules_json,
        'ACT open data terms (cite ACT Government)' AS licence,
        '© Australian Capital Territory — Public Toilet Assets' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/actgov-public-toilet-assets' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'public_toilet') AS settings_json,
        'public_toilet' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Complements National Toilet Map.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'act_public_toilet_assets'
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
        'parks_victoria_campgrounds' AS dataset_key,
        'Parks Victoria / DataVic' AS publisher,
        'Parks Victoria Camp Grounds (GovHack vintage)' AS title,
        'VIC' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('picnic_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 80) AS duplicate_rules_json,
        'CC BY 4.0 — mark stale/GovHack vintage on review' AS licence,
        '© State of Victoria (Parks Victoria / DEECA) — Camp Grounds and Huts' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'SHP' AS source_format,
        'unknown / stale' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://discover.data.vic.gov.au/dataset/parks-victoria-camp-grounds-and-huts' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'picnic_area', 'stale', true) AS settings_json,
        'picnic_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. GovHack vintage — do not treat as current parks booking data.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'parks_victoria_campgrounds'
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
        'vic_recreation_sites' AS dataset_key,
        'DEECA / DataVic' AS publisher,
        'Victoria State Forest Recreation Sites' AS title,
        'VIC' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('picnic_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 80) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© State of Victoria (DEECA) — Recreation Sites' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://discover.data.vic.gov.au/dataset/recreation-sites' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'picnic_area', 'wfs_may_timeout', true) AS settings_json,
        'picnic_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. DataVic WFS may 504 — retry Download in RIC before upload.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'vic_recreation_sites'
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
        'vic_gov_funded_ev_chargers' AS dataset_key,
        'DEECA / DataVic' AS publisher,
        'Victoria Government Funded Public EV Chargers' AS title,
        'VIC' AS coverage,
        'VIC' AS jurisdiction,
        JSON_ARRAY('ev_charging') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 4.0' AS licence,
        '© State of Victoria (DEECA) — Government Funded Public EV Chargers' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://discover.data.vic.gov.au/dataset/government-funded-public-ev-chargers' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'ev_charging', 'wfs_may_timeout', true) AS settings_json,
        'ev_charging' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC third-wave ready pack. Gov-funded subset; DataVic WFS may 504.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'vic_gov_funded_ev_chargers'
);
