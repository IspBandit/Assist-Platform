-- DATA-008 follow-up: complete the official street-rod pathway beyond the
-- national construction manual. Registration and approval remain jurisdiction
-- administered, so each record links to the issuing government's current page
-- and, where available, its genuine downloadable instrument or application.

INSERT INTO regulatory_documents
    (authority_id, jurisdiction_code, slug, title, summary, document_kind,
     vehicle_classes_json, authority_level, source_url, download_url,
     source_format, version_label, publication_status, effective_from,
     next_check_at, created_at)
VALUES
((SELECT id FROM regulatory_authorities WHERE authority_key='act-access-canberra'), 'ACT',
 'street-rod-inspection-registration', 'ACT street rod inspection and registration pathway',
 'Access Canberra requires modified vehicles and street rods to be inspected at the Hume Motor Vehicle Inspection Station before registration can be established.',
 'registration', JSON_ARRAY('street-rod'), 'official_guidance',
 'https://www.accesscanberra.act.gov.au/driving-transport-and-parking/registration/roadworthy-inspections',
 'https://www.accesscanberra.act.gov.au/__data/assets/pdf_file/0010/2239129/Light-vehicle-inspection-manual.pdf',
 'pdf', 'Current inspection pathway', 'current', NULL, NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='nsw-transport'), 'NSW',
 'street-rod-guidelines-nsw-supplement', 'NSW supplement to the National Street Rod Guidelines',
 'NSW construction and performance requirements used with the national manual for street rods seeking full registration, including the licensed-certifier pathway.',
 'street_rods', JSON_ARRAY('street-rod'), 'approved_code',
 'https://www.nsw.gov.au/driving-boating-and-transport/vehicle-registration/how-to-register/vehicle-standards-guidelines-for-registration/transport-for-nsw-standard-compliance-specifications',
 'https://www.nsw.gov.au/sites/default/files/2021-02/RMZS-infosheet-street-rod-guidelines-nsw-supplement.pdf',
 'pdf', 'Version 2.0 — July 2025', 'current', '2025-07-01', NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='nsw-transport'), 'NSW',
 'street-rod-registration-sheet', 'NSW street rod registration requirements',
 'Official conditional and general registration pathways, inspections, declarations, certification and operating conditions for street rods in NSW.',
 'registration', JSON_ARRAY('street-rod'), 'official_guidance',
 'https://www.nsw.gov.au/driving-boating-and-transport/vehicle-registration/conditional-and-seasonal/vehicle-sheets/street-rod',
 'https://www.nsw.gov.au/sites/default/files/noindex/2025-10/conditional-registration-street-rod-september-2025.pdf',
 'pdf', 'September 2025', 'current', '2025-09-01', NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='vic-transport'), 'VIC',
 'build-modify-street-rod-icv', 'Victoria street rod construction and VASS approval',
 'Official Victorian eligibility, construction, VASS certification and first-registration guidance for street rods and individually constructed vehicles.',
 'street_rods', JSON_ARRAY('street-rod','individually-constructed'), 'official_guidance',
 'https://transport.vic.gov.au/road-and-active-transport/registration-and-licensing/registration/standard-and-non-standard-vehicle-information/modified-and-non-standard-vehicles/build-or-modify-a-street-rod-or-individually-constructed-vehicle',
 NULL, 'web', 'Current pathway', 'current', NULL, NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='qld-tmr'), 'QLD',
 'qcop-lh10-street-rod-certification', 'Queensland Code LH10 — Street Rod Certification (Full)',
 'Queensland certification scope and technical requirements for converting a qualifying pre-1949 vehicle or constructing a street rod.',
 'street_rods', JSON_ARRAY('street-rod'), 'approved_code',
 'https://www.tmr.qld.gov.au/safety/vehicle-standards-and-safety/vehicle-modifications',
 'https://www.publications.qld.gov.au/dataset/58da77ae-5764-414a-864a-b72b2bbb1563/resource/6e794497-32a7-40c1-8240-fa35bab7b7cc/download/lh10-street-rod-certification-full-sep-21.pdf',
 'pdf', 'LH10 — September 2021', 'current', '2021-09-01', NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='qld-tmr'), 'QLD',
 'special-interest-street-rod-registration', 'Queensland street rod special-interest registration',
 'Official eligibility, club evidence, LH9/LH10 certification and restricted-use conditions for the Special Interest Vehicle Concession Scheme.',
 'registration', JSON_ARRAY('street-rod'), 'official_guidance',
 'https://www.qld.gov.au/transport/registration/fees/concession/special-interest/apply',
 NULL, 'web', 'Current concession pathway', 'current', NULL, NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='sa-dit'), 'SA',
 'building-a-street-rod', 'South Australia — building and approving a street rod',
 'Official pre-build application, technical evidence, engineering-report and exemption pathway required before registration in South Australia.',
 'street_rods', JSON_ARRAY('street-rod'), 'official_guidance',
 'https://www.sa.gov.au/topics/driving-and-transport/vehicles/vehicle-standards-and-modifications/building-a-street-rod',
 NULL, 'web', 'MR640 pathway', 'current', NULL, NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='sa-dit'), 'SA',
 'conditional-registration-street-rods', 'South Australia conditional registration code — street rods',
 'Official eligibility, recognised-club, logbook and restricted-use requirements for street rods under South Australia’s conditional registration scheme.',
 'registration', JSON_ARRAY('street-rod'), 'approved_code',
 'https://www.sa.gov.au/topics/driving-and-transport/registration/conditional-registration',
 'https://www.sa.gov.au/__data/assets/pdf_file/0017/10439/Code-of-Practice-for-the-Conditional-Registration-Scheme.pdf',
 'pdf', '2025 code of practice', 'current', NULL, NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='wa-transport'), 'WA',
 'construct-register-street-rod', 'Western Australia street rod construction and licensing',
 'Official pre-construction application, examiner support, inspection, modification and conditional B-class licensing pathway for street rods in WA.',
 'street_rods', JSON_ARRAY('street-rod','individually-constructed'), 'official_guidance',
 'https://www.transport.wa.gov.au/licensing/vehicle/modify-construct/individually-constructed',
 NULL, 'web', 'Current pathway', 'current', NULL, NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='tas-transport'), 'TAS',
 'vintage-street-rod-club-registration', 'Tasmania Vintage & Street Rod Club Event registration',
 'Official eligibility, roadworthiness, modification certification, club declaration, operating conditions and application pathway for Tasmanian street rods.',
 'registration', JSON_ARRAY('street-rod'), 'official_guidance',
 'https://www.transport.tas.gov.au/registration/vehicle_registration_and_permits/vintage_and_street_rod_club_event_registration',
 'https://www.transport.tas.gov.au/__data/assets/pdf_file/0005/607568/Vintage-and-Street-Rod-Club-event-Application-for-Registration-MR196.pdf',
 'pdf', 'MR196 09/25', 'current', '2025-09-01', NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='nt-transport'), 'NT',
 'street-rod-tac-engineering-approval', 'Northern Territory street rod TAC and engineering approval',
 'Official pre-build Technical Advisory Committee assessment, engineering-certification and inspection pathway for street rods in the Northern Territory.',
 'street_rods', JSON_ARRAY('street-rod','individually-constructed'), 'official_guidance',
 'https://nt.gov.au/driving/rego/vehicle-compliance-and-modification/vehicle-modifications/apply-for-technical-advisory-committee-approval',
 'https://nt.gov.au/_media/docs/driving%2C-transport-and-marine/vehicle-information-bulletins-and-forms/information-bulletins/v32lv-light-vehicle-modifications.pdf',
 'pdf', 'V32(lv) — 17 April 2024', 'current', '2024-04-17', NOW(), NOW()),

((SELECT id FROM regulatory_authorities WHERE authority_key='nt-transport'), 'NT',
 'street-rod-club-registration', 'Northern Territory street rod club registration',
 'Official enthusiast-club registration eligibility and operating pathway for street rods certified to the national construction guidelines.',
 'registration', JSON_ARRAY('street-rod'), 'official_guidance',
 'https://nt.gov.au/driving/rego/getting-an-nt-registration/motor-club-vehicles/apply-for-club-vehicle-registration',
 'https://nt.gov.au/__data/assets/pdf_file/0015/164310/nt-motor-vehicle-enthusiast-club-registration-scheme-guidelines-final-29-march-2019.pdf',
 'pdf', 'Club scheme guidelines', 'current', NULL, NOW(), NOW());

INSERT INTO regulatory_document_brands (document_id, brand_id, is_featured, created_at)
SELECT d.id, b.id, 1, NOW()
FROM regulatory_documents d
INNER JOIN brands b ON b.brand_key = 'localtorque'
WHERE d.slug IN (
    'street-rod-inspection-registration',
    'street-rod-guidelines-nsw-supplement',
    'street-rod-registration-sheet',
    'build-modify-street-rod-icv',
    'qcop-lh10-street-rod-certification',
    'special-interest-street-rod-registration',
    'building-a-street-rod',
    'conditional-registration-street-rods',
    'construct-register-street-rod',
    'vintage-street-rod-club-registration',
    'street-rod-tac-engineering-approval',
    'street-rod-club-registration'
);
