-- National caravan-route discovery review workflow.
-- Google Places candidates remain evidence-required and expire after 30 days.

ALTER TABLE data_source_import_candidates
    MODIFY review_status ENUM('pending','held','approved','merged','rejected','ignored') NOT NULL DEFAULT 'pending',
    ADD COLUMN candidate_state CHAR(3) NULL AFTER longitude,
    ADD COLUMN route_hub VARCHAR(190) NULL AFTER candidate_state,
    ADD COLUMN evidence_status ENUM('required','confirmed','claimed') NOT NULL DEFAULT 'required' AFTER raw_json,
    ADD COLUMN evidence_url VARCHAR(500) NULL AFTER evidence_status,
    ADD COLUMN review_notes VARCHAR(1000) NULL AFTER evidence_url,
    ADD COLUMN hold_reason VARCHAR(500) NULL AFTER review_notes,
    ADD KEY idx_data_source_candidate_filters (brand_id, review_status, candidate_state, category_id),
    ADD KEY idx_data_source_candidate_evidence (brand_id, evidence_status, expires_at);

INSERT INTO brand_provider_categories
    (brand_id, category_key, name, description, sort_order, is_active, created_at, updated_at)
VALUES
    (1,'caravan-gas-appliances','Caravan gas, refrigeration & appliances','Qualified caravan gas, refrigeration, air-conditioning and appliance specialists.',55,1,NOW(),NOW()),
    (1,'trailer-brakes-suspension','Trailer brakes, bearings & suspension','Trailer brake, bearing, axle and suspension repair services.',60,1,NOW(),NOW()),
    (1,'mobile-diesel-mechanics','Mobile & diesel mechanics','Mobile and diesel mechanical help for tow vehicles and motorhomes.',70,1,NOW(),NOW()),
    (1,'fuel-travel-stops','Fuel & travel stops','Fuel stations and travel stops relevant to touring vehicles.',80,1,NOW(),NOW()),
    (1,'ev-charging','EV charging','Electric vehicle charging locations along touring routes.',90,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
    name=VALUES(name), description=VALUES(description), is_active=1, updated_at=NOW();

INSERT INTO service_categories
    (name,slug,short_description,sort_order,is_active,created_at,updated_at)
VALUES
    ('General caravan repairs','general-caravan-repairs','General caravan and RV repair services.',10,1,NOW(),NOW()),
    ('Auto electrical and batteries','auto-electrical-and-batteries','Vehicle and caravan electrical and battery services.',260,1,NOW(),NOW()),
    ('Tyres and wheels','tyres-and-wheels','Tyre and wheel services for touring vehicles and trailers.',150,1,NOW(),NOW()),
    ('Roadside assistance','roadside-assistance','Roadside help for tow vehicles, caravans and motorhomes.',270,1,NOW(),NOW()),
    ('Brakes and bearings','brakes-and-bearings','Trailer brake and bearing inspection and repair.',130,1,NOW(),NOW()),
    ('Gas appliance servicing','gas-appliance-servicing','Qualified caravan gas and appliance servicing.',90,1,NOW(),NOW()),
    ('Diesel mechanics','diesel-mechanics','Diesel servicing and repairs for tow vehicles and motorhomes.',250,1,NOW(),NOW()),
    ('Fuel and travel stops','fuel-and-travel-stops','Fuel stops suitable for touring vehicles.',110,1,NOW(),NOW()),
    ('EV charging','ev-charging','Charging locations for electric touring vehicles.',120,1,NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),short_description=VALUES(short_description),is_active=1,updated_at=NOW();

INSERT INTO data_source_connectors
    (connector_key, name, connector_class, status, daily_request_limit,
     daily_budget_aud, estimated_request_cost_aud, settings_json, created_at, updated_at)
VALUES
    ('national_route_places', 'National caravan-route discovery',
     'App\\Platform\\DataSources\\Connectors\\OfflineNationalRouteConnector',
     'configured', 1, 0, 0,
     JSON_OBJECT('offline', TRUE, 'evidence_required', TRUE, 'retention_days', 30),
     NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name=VALUES(name), connector_class=VALUES(connector_class),
    settings_json=VALUES(settings_json), updated_at=NOW();

-- Caravan-suitable overnight locations remain separate from hotels and motels.
ALTER TABLE caravan_parks
    MODIFY stay_type ENUM('caravan_park','campground','free_camp','national_park','showground','rest_area','council_camp','farm_stay','station_stay','other') NOT NULL DEFAULT 'caravan_park';

-- Existing OSM records were already tagged as caravan/camp sites. Their names
-- only choose a more useful subtype here and do not claim legal authority.
UPDATE caravan_parks SET stay_type = CASE
    WHEN LOWER(name) REGEXP 'national park|(^|[^a-z])np([^a-z]|$)' THEN 'national_park'
    WHEN LOWER(name) LIKE '%showground%' OR LOWER(name) LIKE '%showgrounds%' THEN 'showground'
    WHEN LOWER(name) LIKE '%rest area%' THEN 'rest_area'
    WHEN LOWER(name) REGEXP 'station stay|station camp' THEN 'station_stay'
    WHEN LOWER(name) REGEXP 'farm stay|farmstay' THEN 'farm_stay'
    ELSE stay_type END
WHERE source_type='openstreetmap';
