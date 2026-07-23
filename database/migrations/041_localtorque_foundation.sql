-- LocalTorque automotive-directory foundation.
-- Additive only: shared providers, accounts, memberships and reviews remain canonical.

INSERT INTO brands (
    id, brand_key, name, legal_name, status, default_locale,
    default_currency, storage_namespace, created_at
) VALUES (4, 'localtorque', 'LocalTorque', 'LocalTorque', 'private', 'en-AU', 'AUD', 'localtorque', NOW());

INSERT INTO brand_domains (brand_id, hostname, environment, is_primary, created_at)
VALUES (4, 'localtorque.test', 'local', 1, NOW());

INSERT INTO brand_provider_categories
    (brand_id, category_key, name, description, sort_order, is_active, created_at)
VALUES
    (4,'mechanics','Mechanics','General automotive mechanical repairs and servicing.',10,1,NOW()),
    (4,'mobile-mechanics','Mobile mechanics','Automotive mechanics who attend customers on site.',20,1,NOW()),
    (4,'auto-electricians','Auto electricians','Vehicle electrical diagnosis, wiring, batteries and accessories.',30,1,NOW()),
    (4,'diesel-specialists','Diesel specialists','Diesel vehicle diagnosis, servicing and repair.',40,1,NOW()),
    (4,'brake-clutch','Brake & clutch','Brake, clutch and related driveline specialists.',50,1,NOW()),
    (4,'suspension','Suspension','Suspension diagnosis, repair and upgrades.',60,1,NOW()),
    (4,'steering','Steering','Steering system diagnosis and repair.',70,1,NOW()),
    (4,'tyre-shops','Tyre shops','Tyres, balancing, puncture repair and related services.',80,1,NOW()),
    (4,'wheel-alignment','Wheel alignment','Wheel alignment and geometry services.',90,1,NOW()),
    (4,'windscreens','Windscreens','Automotive glass repair and replacement.',100,1,NOW()),
    (4,'air-conditioning','Air conditioning','Automotive air-conditioning diagnosis and service.',110,1,NOW()),
    (4,'radiator-specialists','Radiator specialists','Cooling-system, radiator and overheating specialists.',120,1,NOW()),
    (4,'exhaust-shops','Exhaust shops','Exhaust repair, replacement and fabrication.',130,1,NOW()),
    (4,'performance-workshops','Performance workshops','Vehicle performance diagnosis and upgrades.',140,1,NOW()),
    (4,'dyno-tuners','Dyno tuners','Dynamometer testing and vehicle tuning.',150,1,NOW()),
    (4,'transmission-specialists','Transmission specialists','Automatic and manual transmission service and repair.',160,1,NOW()),
    (4,'differential-specialists','Differential specialists','Differential, final-drive and axle specialists.',170,1,NOW()),
    (4,'engine-rebuilders','Engine rebuilders','Engine machining, reconditioning and rebuilding.',180,1,NOW()),
    (4,'ecu-tuning','ECU tuning','Engine-control diagnostics, calibration and tuning.',190,1,NOW()),
    (4,'caravan-repairs','Caravan repairs','Caravan, camper and RV repair services.',200,1,NOW()),
    (4,'trailer-repairs','Trailer repairs','Trailer servicing and repair specialists.',210,1,NOW()),
    (4,'fabrication','Fabrication','Automotive and light-engineering fabrication.',220,1,NOW()),
    (4,'welding','Welding','Mobile and workshop welding services.',230,1,NOW()),
    (4,'panel-beaters','Panel beaters','Collision and body repair specialists.',240,1,NOW()),
    (4,'paint-shops','Paint shops','Automotive refinishing and paint services.',250,1,NOW()),
    (4,'detailers','Detailers','Vehicle cleaning, detailing and paint protection.',260,1,NOW()),
    (4,'rust-repairs','Rust repairs','Vehicle rust treatment and structural repair.',270,1,NOW()),
    (4,'four-wheel-drive-specialists','4WD specialists','Four-wheel-drive servicing, repairs and preparation.',280,1,NOW()),
    (4,'suspension-lift-specialists','Suspension lift specialists','4WD suspension lift supply, installation and setup.',290,1,NOW()),
    (4,'bullbar-installation','Bullbar installation','Bullbar supply, installation and associated accessories.',300,1,NOW()),
    (4,'canopy-installation','Canopy installation','Ute canopy, tray and storage installation.',310,1,NOW()),
    (4,'accessory-installers','Accessory installers','Automotive and touring accessory installation.',320,1,NOW()),
    (4,'battery-specialists','Battery specialists','Vehicle batteries, testing, charging and replacement.',330,1,NOW()),
    (4,'auto-locksmiths','Auto locksmiths','Vehicle keys, locks, immobilisers and lockout services.',340,1,NOW()),
    (4,'vehicle-inspections','Vehicle inspections','Pre-purchase, condition and general vehicle inspections.',350,1,NOW()),
    (4,'roadworthy-inspections','Roadworthy inspections','Approved safety, roadworthy and registration inspections.',360,1,NOW()),
    (4,'fleet-maintenance','Fleet maintenance','Scheduled commercial fleet servicing and maintenance.',370,1,NOW()),
    (4,'agricultural-machinery','Agricultural machinery','Farm and agricultural machinery servicing and repairs.',380,1,NOW()),
    (4,'marine-mechanics','Marine mechanics','Boat engine and marine mechanical services.',390,1,NOW()),
    (4,'motorcycle-workshops','Motorcycle workshops','Motorcycle servicing, repair and specialist workshops.',400,1,NOW());
