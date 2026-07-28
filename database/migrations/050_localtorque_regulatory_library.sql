-- DATA-008: shared authoritative Australian vehicle rules catalogue.
-- Official documents remain owned by their issuing authority. The platform
-- stores source metadata and fingerprints; public downloads use official URLs.

CREATE TABLE regulatory_authorities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    authority_key VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    jurisdiction_code VARCHAR(8) NOT NULL,
    official_url VARCHAR(1000) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regulatory_authority_key (authority_key),
    KEY idx_regulatory_authority_jurisdiction (jurisdiction_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regulatory_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    authority_id INT UNSIGNED NOT NULL,
    jurisdiction_code VARCHAR(8) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(300) NOT NULL,
    summary TEXT NOT NULL,
    document_kind ENUM('roadworthiness','inspection_manual','modifications','code_of_practice','design_rules','street_rods','towing','trailer_construction','load_restraint','registration') NOT NULL,
    vehicle_classes_json JSON NOT NULL,
    authority_level ENUM('legislation','mandatory_standard','approved_code','official_guidance') NOT NULL DEFAULT 'official_guidance',
    source_url VARCHAR(1000) NOT NULL,
    download_url VARCHAR(1000) NULL,
    source_format ENUM('web','pdf','docx') NOT NULL DEFAULT 'web',
    version_label VARCHAR(120) NULL,
    publication_status ENUM('current','upcoming','superseded','withdrawn','review') NOT NULL DEFAULT 'current',
    effective_from DATE NULL,
    effective_to DATE NULL,
    official_document TINYINT(1) NOT NULL DEFAULT 1,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    licence_status ENUM('link_only','rehost_permitted','unknown') NOT NULL DEFAULT 'link_only',
    content_hash CHAR(64) NULL,
    source_etag VARCHAR(500) NULL,
    source_last_modified VARCHAR(190) NULL,
    last_checked_at DATETIME NULL,
    next_check_at DATETIME NULL,
    check_interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    change_detected_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regulatory_document_slug (jurisdiction_code, slug),
    KEY idx_regulatory_public_library (is_public, publication_status, jurisdiction_code, document_kind),
    KEY idx_regulatory_source_due (is_public, next_check_at),
    CONSTRAINT fk_regulatory_document_authority FOREIGN KEY (authority_id) REFERENCES regulatory_authorities (id) ON DELETE RESTRICT,
    CONSTRAINT fk_regulatory_document_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regulatory_source_checks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    checked_at DATETIME NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    result ENUM('baseline','unchanged','changed','failed') NOT NULL,
    observed_hash CHAR(64) NULL,
    source_etag VARCHAR(500) NULL,
    source_last_modified VARCHAR(190) NULL,
    error_message VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    KEY idx_regulatory_check_document (document_id, checked_at),
    KEY idx_regulatory_check_result (result, checked_at),
    CONSTRAINT fk_regulatory_check_document FOREIGN KEY (document_id) REFERENCES regulatory_documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regulatory_document_brands (
    document_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (document_id, brand_id),
    KEY idx_regulatory_brand_library (brand_id, is_featured, document_id),
    CONSTRAINT fk_regulatory_brand_document FOREIGN KEY (document_id) REFERENCES regulatory_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_regulatory_brand_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO regulatory_authorities (authority_key, name, jurisdiction_code, official_url, created_at) VALUES
('ditrdca', 'Australian Government Department of Infrastructure, Transport, Regional Development, Communications and the Arts', 'AUS', 'https://www.infrastructure.gov.au/infrastructure-transport-vehicles/vehicles', NOW()),
('nhvr', 'National Heavy Vehicle Regulator', 'AUS', 'https://www.nhvr.gov.au/', NOW()),
('act-access-canberra', 'Access Canberra', 'ACT', 'https://www.accesscanberra.act.gov.au/driving-transport-and-parking', NOW()),
('nsw-transport', 'Transport for NSW', 'NSW', 'https://www.nsw.gov.au/driving-boating-and-transport', NOW()),
('vic-transport', 'Transport Victoria', 'VIC', 'https://transport.vic.gov.au/', NOW()),
('qld-tmr', 'Queensland Department of Transport and Main Roads', 'QLD', 'https://www.tmr.qld.gov.au/', NOW()),
('sa-dit', 'South Australian Department for Infrastructure and Transport', 'SA', 'https://www.sa.gov.au/topics/driving-and-transport', NOW()),
('wa-transport', 'Western Australian Department of Transport and Major Infrastructure', 'WA', 'https://transport.wa.gov.au/', NOW()),
('tas-transport', 'Tasmanian Department of State Growth — Transport Services', 'TAS', 'https://www.transport.tas.gov.au/', NOW()),
('nt-transport', 'Northern Territory Government', 'NT', 'https://nt.gov.au/driving', NOW());

INSERT INTO regulatory_documents (authority_id, jurisdiction_code, slug, title, summary, document_kind, vehicle_classes_json, authority_level, source_url, download_url, source_format, version_label, publication_status, effective_from, next_check_at, created_at) VALUES
((SELECT id FROM regulatory_authorities WHERE authority_key='ditrdca'), 'AUS', 'vsb14-light-vehicle-modifications', 'Vehicle Standards Bulletin 14 — National Code of Practice for Light Vehicle Construction and Modification', 'National technical code for construction and modification of light vehicles up to 4.5 tonnes. State and territory approval and registration processes still apply.', 'code_of_practice', JSON_ARRAY('car','light-truck','motorcycle','trailer','individually-constructed'), 'approved_code', 'https://www.infrastructure.gov.au/infrastructure-transport-vehicles/vehicles/vehicle-design-regulation/rvs/bulletins/ncop', NULL, 'web', 'Live document collection', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='ditrdca'), 'AUS', 'national-street-rod-manual', 'National Guidelines for the Construction and Modification of Street Rods', 'Nationally endorsed construction and modification guidance for pre-1949 vehicles and qualifying replicas. Jurisdiction registration requirements remain applicable.', 'street_rods', JSON_ARRAY('street-rod','car'), 'approved_code', 'https://www.infrastructure.gov.au/infrastructure-transport-vehicles/vehicles/vehicle-design-regulation/rvs/bulletins/street-rod-manual', 'https://www.infrastructure.gov.au/sites/default/files/migrated/vehicles/vehicle_regulation/bulletin/files/AMVCB_Final_Approval_Street_Rod_Manual.pdf', 'pdf', 'Second edition', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='ditrdca'), 'AUS', 'australian-design-rules', 'Australian Design Rules', 'National standards for vehicle safety, anti-theft and emissions for new vehicles supplied to Australia.', 'design_rules', JSON_ARRAY('car','light-truck','heavy-vehicle','motorcycle','trailer'), 'mandatory_standard', 'https://www.infrastructure.gov.au/infrastructure-transport-vehicles/vehicles/vehicle-design-regulation/australian-design-rules', NULL, 'web', 'Current series', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nhvr'), 'AUS', 'vsb6-heavy-vehicle-modifications', 'Vehicle Standards Bulletin 6 — National Code of Practice for Heavy Vehicle Modifications', 'National modification code and certification guidance for heavy vehicles.', 'code_of_practice', JSON_ARRAY('heavy-vehicle','heavy-truck','bus','heavy-trailer'), 'approved_code', 'https://www.nhvr.gov.au/safety-accreditation-compliance/vehicle-standards-and-modifications/vehicle-standards-bulletin-6', NULL, 'web', 'Version 3.2', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nhvr'), 'AUS', 'national-heavy-vehicle-inspection-manual-v31', 'National Heavy Vehicle Inspection Manual', 'Inspection criteria for heavy vehicles. Version 3.1 remains current until 1 August 2026.', 'inspection_manual', JSON_ARRAY('heavy-vehicle','heavy-truck','bus','heavy-trailer'), 'approved_code', 'https://www.nhvr.gov.au/safety-accreditation-compliance/vehicle-standards-and-modifications/national-heavy-vehicle-inspection-manual', NULL, 'web', 'Version 3.1', 'current', NULL, '2026-07-28 00:00:00', NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nhvr'), 'AUS', 'national-heavy-vehicle-inspection-manual-v32', 'National Heavy Vehicle Inspection Manual — upcoming edition', 'Version 3.2 becomes effective on 1 August 2026 and is shown in advance so operators can prepare.', 'inspection_manual', JSON_ARRAY('heavy-vehicle','heavy-truck','bus','heavy-trailer'), 'approved_code', 'https://www.nhvr.gov.au/safety-accreditation-compliance/vehicle-standards-and-modifications/national-heavy-vehicle-inspection-manual', NULL, 'web', 'Version 3.2', 'upcoming', '2026-08-01', '2026-07-28 00:00:00', NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='act-access-canberra'), 'ACT', 'light-vehicle-inspection-manual', 'ACT Light Vehicle Inspection Manual', 'Official ACT roadworthiness inspection requirements for light vehicles.', 'inspection_manual', JSON_ARRAY('car','light-truck','motorcycle','trailer'), 'official_guidance', 'https://www.accesscanberra.act.gov.au/driving-transport-and-parking/registration/roadworthy-inspections', 'https://www.accesscanberra.act.gov.au/__data/assets/pdf_file/0010/2239129/Light-vehicle-inspection-manual.pdf', 'pdf', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='act-access-canberra'), 'ACT', 'vehicle-modifications-and-street-rods', 'ACT modified vehicles and street rod inspection requirements', 'ACT inspection and approval pathway for modified vehicles and street rods, including inspections at the Hume examination station.', 'modifications', JSON_ARRAY('car','light-truck','motorcycle','street-rod'), 'official_guidance', 'https://www.accesscanberra.act.gov.au/driving-transport-and-parking/registration/roadworthy-inspections', 'https://www.accesscanberra.act.gov.au/__data/assets/pdf_file/0003/2237934/Authorised-Examiner-Scheme-Information.pdf', 'pdf', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nsw-transport'), 'NSW', 'light-vehicle-safety-inspections', 'NSW light vehicle safety inspections and pink slips', 'Official NSW inspection requirements and validity information for registration renewal.', 'roadworthiness', JSON_ARRAY('car','light-truck','motorcycle','trailer'), 'official_guidance', 'https://www.service.nsw.gov.au/transaction/get-a-safety-inspection-report-pink-slip', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nsw-transport'), 'NSW', 'vsi06-light-vehicle-modifications', 'NSW VSI 06 — Light vehicle modifications', 'Transport for NSW guidance distinguishing owner-certified and licensed-certifier modifications for light vehicles.', 'modifications', JSON_ARRAY('car','light-truck','motorcycle','trailer','street-rod'), 'official_guidance', 'https://www.nsw.gov.au/driving-boating-and-transport/vehicle-registration/how-to-register/vehicle-standards-guidelines-for-registration/vehicle-standards-information', 'https://www.nsw.gov.au/sites/default/files/2021-02/RMS-13.464-Light-vehicle-modifications-Vehicle-Standards-Information-No-6-November-2013.pdf', 'pdf', 'VSI 06', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='vic-transport'), 'VIC', 'vsi26-roadworthiness-requirements', 'Victoria VSI 26 — Roadworthiness requirements', 'Official Victorian guideline for vehicle roadworthiness inspections and certificates.', 'inspection_manual', JSON_ARRAY('car','light-truck','motorcycle','trailer'), 'official_guidance', 'https://transport.vic.gov.au/road-and-active-transport/registration-and-licensing/registration/standard-and-non-standard-vehicle-information/vehicle-standards-information/roadworthiness-requirements', NULL, 'web', 'VSI 26', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='vic-transport'), 'VIC', 'vsi8-guide-to-modifications', 'Victoria VSI 8 — Guide to modification for motor vehicles', 'Official Victorian guideline for modifications to vehicles with a GVM of 4.5 tonnes or less.', 'modifications', JSON_ARRAY('car','light-truck','trailer','street-rod'), 'approved_code', 'https://transport.vic.gov.au/road-and-active-transport/registration-and-licensing/registration/standard-and-non-standard-vehicle-information/vehicle-standards-information/guide-to-modification-for-motor-vehicles', NULL, 'web', 'October 2021', 'current', '2021-10-01', NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='vic-transport'), 'VIC', 'vsi4-motorcycles-and-mopeds', 'Victoria VSI 4 — Motorcycles and mopeds', 'Official registration and standards requirements for motorcycles and mopeds in Victoria.', 'modifications', JSON_ARRAY('motorcycle'), 'official_guidance', 'https://transport.vic.gov.au/road-and-active-transport/registration-and-licensing/registration/standard-and-non-standard-vehicle-information/vehicle-standards-information/summary-standards-registration-requirements-motorcycles-mopeds', NULL, 'web', 'VSI 4', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='qld-tmr'), 'QLD', 'light-vehicle-inspection-manual', 'Queensland Light Vehicle Inspection Manual', 'Official inspection requirements for light vehicles used by approved inspection stations.', 'inspection_manual', JSON_ARRAY('car','light-truck','motorcycle','trailer'), 'approved_code', 'https://www.qld.gov.au/transport/registration/roadworthy', 'https://www.tmr.qld.gov.au/-/media/Safety/Vehicle-standards-and-modifications/Vehicle-modifications/queensland-light-vehicle-inspection-manual.pdf', 'pdf', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='qld-tmr'), 'QLD', 'road-vehicle-modification-handbook', 'Queensland Road Vehicle Modification Handbook', 'Queensland approved modification and certification codes for in-service light and heavy vehicles.', 'modifications', JSON_ARRAY('car','light-truck','heavy-vehicle','motorcycle','trailer','street-rod'), 'approved_code', 'https://www.tmr.qld.gov.au/safety/vehicle-standards-and-safety/vehicle-modifications', 'https://www.tmr.qld.gov.au/-/media/Safety/Vehicle-standards-and-modifications/Vehicle-modifications/Queensland-Road-Vehicle-Modification-Handbook.pdf?la=en', 'pdf', 'September 2024', 'current', '2024-09-01', NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='sa-dit'), 'SA', 'roadworthy-inspections', 'South Australia roadworthy inspections', 'Official South Australian guidance on when a vehicle requires a roadworthy inspection.', 'roadworthiness', JSON_ARRAY('car','light-truck','heavy-vehicle','motorcycle','trailer'), 'official_guidance', 'https://www.sa.gov.au/topics/driving-and-transport/vehicles/vehicle-inspections/roadworthy-inspections', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='sa-dit'), 'SA', 'modification-of-light-vehicles', 'South Australia — Modification of light vehicles', 'Official guidance for approval and certification of light vehicle modifications in South Australia.', 'modifications', JSON_ARRAY('car','light-truck','motorcycle','trailer','street-rod'), 'official_guidance', 'https://www.sa.gov.au/topics/driving-and-transport/vehicles/vehicle-standards-and-modifications/cars', 'https://www.sa.gov.au/__data/assets/pdf_file/0017/10727/MR1457-Modification-of-Light-Vehicles-10.20.pdf', 'pdf', 'MR1457', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='wa-transport'), 'WA', 'vehicle-inspections', 'Western Australia vehicle inspections', 'Official Western Australian requirements for vehicle examinations and roadworthiness.', 'roadworthiness', JSON_ARRAY('car','light-truck','heavy-vehicle','motorcycle','trailer'), 'official_guidance', 'https://www.transport.wa.gov.au/licensing/get-a-vehicle-inspected', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='wa-transport'), 'WA', 'ib134-light-vehicle-modification-standards', 'Western Australia IB-134 — Light Vehicle Modification Standards', 'Western Australian modification categories, applicable standards and inspection requirements for light vehicles.', 'modifications', JSON_ARRAY('car','light-truck','motorcycle','trailer','street-rod'), 'official_guidance', 'https://transport.wa.gov.au/licensing/vehicle/modify-construct/simple', 'https://transport.wa.gov.au/getmedia/85981b8e-c787-4ce9-98f9-f956372ce248/dvs_vs_ib_134.pdf', 'pdf', 'IB-134', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='tas-transport'), 'TAS', 'light-vehicle-inspection-manual', 'Tasmania Light Vehicle Inspection Manual — Reasons for Rejection', 'Official Tasmanian light vehicle roadworthiness rejection criteria and inspection manual resources.', 'inspection_manual', JSON_ARRAY('car','light-truck','motorcycle','trailer'), 'approved_code', 'https://www.transport.tas.gov.au/vehicles_and_vehicle_inspections/vehicle_inspections_ais/ais_inspection_manuals', NULL, 'web', 'Current manual collection', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='tas-transport'), 'TAS', 'vehicle-modification-certification-manual', 'Tasmania Vehicle Modification Certification Manual', 'Official Tasmanian certification requirements for vehicle modifications.', 'modifications', JSON_ARRAY('car','light-truck','motorcycle','trailer','street-rod'), 'approved_code', 'https://www.transport.tas.gov.au/vehicles_and_vehicle_inspections/information_for_avcais_and_avcs', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nt-transport'), 'NT', 'light-vehicle-inspection-manual', 'Northern Territory Light Vehicle Inspection Manual', 'Official Northern Territory inspection standards and reasons for rejection for light vehicles.', 'inspection_manual', JSON_ARRAY('car','light-truck','motorcycle','trailer'), 'approved_code', 'https://nt.gov.au/driving/rego/vehicle-compliance-and-modification/light-vehicle-standards-for-registration', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nt-transport'), 'NT', 'modified-vehicles-appendix', 'Northern Territory inspection manual — modified vehicles appendix', 'Official Northern Territory inspection requirements specifically for modified light vehicles.', 'modifications', JSON_ARRAY('car','light-truck','motorcycle','trailer','street-rod'), 'approved_code', 'https://nt.gov.au/driving/rego/vehicle-compliance-and-modification/vehicle-modifications/approval-processes-for-modifications', 'https://nt.gov.au/__data/assets/pdf_file/0006/1042449/nt-inspection-manual-for-light-vehicles-appendix-c-modified-vehicles.pdf', 'pdf', 'Appendix C', 'current', NULL, NOW(), NOW());

INSERT INTO regulatory_documents (authority_id, jurisdiction_code, slug, title, summary, document_kind, vehicle_classes_json, authority_level, source_url, download_url, source_format, version_label, publication_status, effective_from, next_check_at, created_at) VALUES
((SELECT id FROM regulatory_authorities WHERE authority_key='ditrdca'), 'AUS', 'vsb1-revision-6-low-atm-trailers', 'Vehicle Standards Bulletin 1 — Trailers with an ATM of 4.5 tonnes or less', 'Official national guidance helping low-ATM trailer manufacturers understand applicable Australian Design Rule requirements under the Road Vehicle Standards legislation.', 'trailer_construction', JSON_ARRAY('trailer'), 'approved_code', 'https://www.infrastructure.gov.au/department/media/publications/vehicle-standards-bulletin-1-revision-6-trailers-aggregate-trailer-mass-45-tonnes-or-less', NULL, 'web', 'Revision 6 — July 2025', 'current', '2025-07-29', NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nhvr'), 'AUS', 'load-restraint-guide-2025', 'Load Restraint Guide 2025', 'Official best-practice load restraint guidance covering loading performance standards, systems and worked examples for road vehicles.', 'load_restraint', JSON_ARRAY('light-truck','heavy-vehicle','trailer'), 'official_guidance', 'https://www.nhvr.gov.au/road-access/loading/load-restraint-guide', NULL, 'web', '2025 edition', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nsw-transport'), 'NSW', 'towing-a-trailer-or-caravan', 'NSW towing a trailer or caravan', 'Official NSW rules and guidance for towing capacity, braking, couplings, safety chains, loads, dimensions and licence restrictions.', 'towing', JSON_ARRAY('car','light-truck','trailer'), 'official_guidance', 'https://www.nsw.gov.au/driving-boating-and-transport/roads-safety-and-rules/vehicle-safety-and-compliance/towing-a-caravan', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nsw-transport'), 'NSW', 'register-a-trailer-or-caravan', 'NSW trailer and caravan registration', 'Official registration pathway and evidence requirements for trailers and caravans used on NSW roads.', 'registration', JSON_ARRAY('trailer'), 'official_guidance', 'https://www.service.nsw.gov.au/transaction/apply-to-register-a-trailer-or-caravan', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='vic-transport'), 'VIC', 'caravan-towing-rules', 'Victoria caravan towing rules and safety', 'Official Victorian towing safety and road-rule guidance for caravans and trailer combinations.', 'towing', JSON_ARRAY('car','light-truck','trailer'), 'official_guidance', 'https://transport.vic.gov.au/road-rules-and-safety/caravans', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='qld-tmr'), 'QLD', 'towing-vehicles-and-trailers', 'Queensland towing vehicles and trailers', 'Official Queensland requirements for suitable tow vehicles, roadworthy trailers, couplings, loaded mass limits and safe operation.', 'towing', JSON_ARRAY('car','light-truck','trailer'), 'official_guidance', 'https://www.qld.gov.au/transport/vehicle-safety/towing/towing-vehicles-and-trailers', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='sa-dit'), 'SA', 'light-vehicle-towing-regulations', 'South Australia light vehicle towing and trailer regulations', 'Official South Australian limits and requirements for towing mass, brakes, couplings, loading and safe trailer operation.', 'towing', JSON_ARRAY('car','light-truck','trailer'), 'official_guidance', 'https://www.sa.gov.au/topics/driving-and-transport/vehicles/vehicle-standards-and-modifications/loads-and-towing/light-vehicle-towing-regulations', NULL, 'web', 'MR25', 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='sa-dit'), 'SA', 'light-trailers-and-caravans', 'South Australia light trailers and caravans', 'Official South Australian registration, RAV and inspection information for light trailers and caravans.', 'registration', JSON_ARRAY('trailer'), 'official_guidance', 'https://www.sa.gov.au/topics/driving-and-transport/vehicles/vehicle-standards-and-modifications/light-trailers-and-caravans', NULL, 'web', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='wa-transport'), 'WA', 'safe-towing-guide', 'Western Australia Safe Towing Guide', 'Official Western Australian guide to trailer mass, brakes, couplings, safety chains, speed and roadworthy towing combinations.', 'towing', JSON_ARRAY('car','light-truck','trailer'), 'official_guidance', 'https://www.transport.wa.gov.au/licensing/vehicle/safety-standards-security', 'https://www.transport.wa.gov.au/getmedia/412A9DF8-1773-47C4-B29F-51F70F697C7B/DVS_P_Safe_Towing_Guide.pdf', 'pdf', NULL, 'current', NULL, NOW(), NOW()),
((SELECT id FROM regulatory_authorities WHERE authority_key='nt-transport'), 'NT', 'road-users-handbook-towing', 'Northern Territory road users handbook — towing', 'Official Northern Territory road rules for towing trailers and caravans, including registration, roadworthiness, chains, loads and following distance.', 'towing', JSON_ARRAY('car','light-truck','trailer'), 'official_guidance', 'https://nt.gov.au/driving/safety/road-safety/nt-road-users-handbook', 'https://cmsexternal.nt.gov.au/__data/assets/pdf_file/0018/263034/section-5-general-road-rules-road-users-handbook.pdf', 'pdf', 'General road rules section', 'current', NULL, NOW(), NOW());

INSERT INTO regulatory_document_brands (document_id, brand_id, is_featured, created_at)
SELECT id, 4, IF(document_kind IN ('modifications','roadworthiness','inspection_manual'),1,0), NOW()
FROM regulatory_documents;

INSERT INTO regulatory_document_brands (document_id, brand_id, is_featured, created_at)
SELECT id, 2, IF(document_kind IN ('towing','load_restraint'),1,0), NOW()
FROM regulatory_documents
WHERE document_kind IN ('towing','load_restraint','design_rules','code_of_practice','inspection_manual')
  AND document_kind <> 'street_rods'
  AND (
      JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('car'))
      OR JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('light-truck'))
      OR JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('heavy-vehicle'))
      OR JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('trailer'))
  );

INSERT INTO regulatory_document_brands (document_id, brand_id, is_featured, created_at)
SELECT id, 3, IF(document_kind IN ('trailer_construction','registration','inspection_manual'),1,0), NOW()
FROM regulatory_documents
WHERE document_kind <> 'street_rods'
  AND JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('trailer'));

INSERT INTO regulatory_document_brands (document_id, brand_id, is_featured, created_at)
SELECT id, 1, IF(document_kind IN ('towing','roadworthiness','inspection_manual'),1,0), NOW()
FROM regulatory_documents
WHERE document_kind IN ('towing','load_restraint','roadworthiness','inspection_manual','modifications','code_of_practice','trailer_construction','registration')
  AND document_kind <> 'street_rods'
  AND (
      JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('car'))
      OR JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('light-truck'))
      OR JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('trailer'))
  );
