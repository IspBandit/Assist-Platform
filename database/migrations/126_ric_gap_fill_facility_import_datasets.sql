-- Assist RIC gap-fill free packs: register / enable government_datasets keys for
-- POST /api/v1/admin/facility-imports staging. Review-first; not auto-published.
-- DATA-011A / DATA-012 companion to assist-ric Ready packs (2026-08-05).

-- Enable Toilet Map water/shower catalogue rows already inserted by migration 122.
UPDATE government_datasets
SET
    is_enabled = 1,
    catalogue_status = 'indexed',
    notes = CONCAT(
        COALESCE(notes, ''),
        ' Assist RIC gap-fill Ready pack path enabled for /facility-imports staging.'
    ),
    settings_json = JSON_SET(
        COALESCE(settings_json, JSON_OBJECT()),
        '$.role', 'ric_ready_pack',
        '$.default_facility_type', default_facility_type
    ),
    updated_at = NOW()
WHERE dataset_key IN (
    'au_national_toilet_map_drinking_water',
    'au_national_toilet_map_showers'
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
        'au_nmi_public_weighbridges' AS dataset_key,
        'National Measurement Institute' AS publisher,
        'NMI Public Weighbridge Locations' AS title,
        'AU national' AS coverage,
        'AU' AS jurisdiction,
        JSON_ARRAY('weighbridge') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY 2.5 AU' AS licence,
        'Commonwealth of Australia (National Measurement Institute) - Public Weighbridge Locations' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'CSV' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.gov.au/data/dataset/public-weighbridge-locations' AS source_url,
        JSON_OBJECT('role', 'ric_ready_pack', 'default_facility_type', 'weighbridge') AS settings_json,
        'weighbridge' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC gap-fill Ready pack. Ingest via /facility-imports; human review required.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_nmi_public_weighbridges'
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
        'au_openchargemap_export' AS dataset_key,
        'Open Charge Map contributors' AS publisher,
        'OpenChargeMap Australia export (open-provider subset)' AS title,
        'AU national' AS coverage,
        'AU' AS jurisdiction,
        JSON_ARRAY('ev_charging') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40) AS duplicate_rules_json,
        'Mixed — prefer OCM Contributors CC BY 4.0 subset' AS licence,
        'Open Charge Map contributors — https://openchargemap.org/' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'GeoJSON' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://github.com/openchargemap/ocm-export' AS source_url,
        JSON_OBJECT(
            'role', 'ric_ready_pack',
            'default_facility_type', 'ev_charging',
            'prefer_open_provider', true
        ) AS settings_json,
        'ev_charging' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC gap-fill Ready pack. Prefer open-provider GeoJSON; review licence per row.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'au_openchargemap_export'
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
        'nsw_npws_visitor_area' AS dataset_key,
        'NSW National Parks and Wildlife Service' AS publisher,
        'NSW NPWS Visitor Area' AS title,
        'NSW' AS coverage,
        'NSW' AS jurisdiction,
        JSON_ARRAY('picnic_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 50) AS duplicate_rules_json,
        'CC BY' AS licence,
        'State of New South Wales (NPWS / SEED)' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'SHP' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.nsw.gov.au/data/dataset/asset-infrastructure-visitor-area' AS source_url,
        JSON_OBJECT(
            'role', 'ric_ready_pack',
            'default_facility_type', 'picnic_area',
            'default_state_code', 'NSW',
            'map_facility_types_carefully', true
        ) AS settings_json,
        'picnic_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC gap-fill Ready pack. Visitor areas may include camping/picnic; map types on review.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'nsw_npws_visitor_area'
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
        'nsw_npws_facility_point' AS dataset_key,
        'NSW National Parks and Wildlife Service' AS publisher,
        'NSW NPWS Facility Point' AS title,
        'NSW' AS coverage,
        'NSW' AS jurisdiction,
        JSON_ARRAY('picnic_area') AS record_types_json,
        JSON_OBJECT('match_on', JSON_ARRAY('source_record_id', 'geo_proximity'), 'geo_metres', 40) AS duplicate_rules_json,
        'CC BY' AS licence,
        'State of New South Wales (NPWS / SEED)' AS attribution,
        'trusted_review' AS trust_policy,
        'url' AS fetch_method,
        'SHP' AS source_format,
        'as published' AS update_frequency,
        'assist_ric_package' AS connector_key,
        CAST(NULL AS CHAR) AS endpoint_url,
        'https://data.nsw.gov.au/data/dataset/asset-infrastructure-facility-point' AS source_url,
        JSON_OBJECT(
            'role', 'ric_ready_pack',
            'default_facility_type', 'picnic_area',
            'default_state_code', 'NSW',
            'map_facility_types_carefully', true
        ) AS settings_json,
        'picnic_area' AS default_facility_type,
        1 AS is_enabled,
        0 AS auto_update_enabled,
        'indexed' AS catalogue_status,
        'Assist RIC gap-fill Ready pack. Points may be BBQ/toilet/camp — map types on review.' AS notes,
        NOW() AS created_at
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM government_datasets g WHERE g.dataset_key = 'nsw_npws_facility_point'
);
