-- LOC-004: verified motorsport venue and calendar sources.
-- A venue URL is recorded only when known; event calendars remain on the
-- venue, club or governing-body source so date changes are not copied stale.

CREATE TABLE motorsport_venues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(190) NOT NULL,
    name VARCHAR(220) NOT NULL,
    jurisdiction_code VARCHAR(8) NOT NULL,
    locality VARCHAR(190) NOT NULL,
    venue_type ENUM('permanent','temporary','event_route','club_network') NOT NULL DEFAULT 'permanent',
    description VARCHAR(700) NOT NULL,
    website_url VARCHAR(1000) NULL,
    calendar_url VARCHAR(1000) NULL,
    calendar_source ENUM('venue','governing_body','club','none') NOT NULL DEFAULT 'none',
    source_url VARCHAR(1000) NOT NULL,
    content_hash CHAR(64) NULL,
    source_etag VARCHAR(500) NULL,
    source_last_modified VARCHAR(190) NULL,
    check_interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    change_detected_at DATETIME NULL,
    last_checked_at DATETIME NULL,
    next_check_at DATETIME NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_motorsport_venue_slug (slug),
    KEY idx_motorsport_venue_directory (is_public,jurisdiction_code,venue_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorsport_source_checks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_type ENUM('document','venue') NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    checked_at DATETIME NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    result ENUM('baseline','unchanged','changed','failed') NOT NULL,
    observed_hash CHAR(64) NULL,
    source_etag VARCHAR(500) NULL,
    source_last_modified VARCHAR(190) NULL,
    error_message VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    KEY idx_motorsport_check_source (source_type,source_id,checked_at),
    KEY idx_motorsport_check_result (result,checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorsport_venue_families (
    venue_id INT UNSIGNED NOT NULL,
    family_key VARCHAR(40) NOT NULL,
    PRIMARY KEY (venue_id,family_key),
    KEY idx_motorsport_vf_family (family_key,venue_id),
    CONSTRAINT fk_motorsport_vf_venue FOREIGN KEY (venue_id) REFERENCES motorsport_venues (id) ON DELETE CASCADE,
    CONSTRAINT fk_motorsport_vf_family FOREIGN KEY (family_key) REFERENCES motorsport_families (family_key) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO motorsport_venues (slug,name,jurisdiction_code,locality,venue_type,description,website_url,calendar_url,calendar_source,source_url,last_checked_at,next_check_at,created_at) VALUES
('sydney-motorsport-park','Sydney Motorsport Park','NSW','Eastern Creek','permanent','Multi-configuration circuit, skid circuit and motorsport precinct hosting car and motorcycle competition, track activity and training.','https://www.sydneymotorsportpark.com.au/','https://www.sydneymotorsportpark.com.au/pages/calendar','venue','https://www.sydneymotorsportpark.com.au/pages/calendar',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('phillip-island-grand-prix-circuit','Phillip Island Grand Prix Circuit','VIC','Phillip Island','permanent','International circuit hosting car and motorcycle racing. Check the circuit and organising club calendars for current activity.','https://www.phillipislandcircuit.com.au/','https://www.phillipislandcircuit.com.au/','venue','https://www.phillipislandcircuit.com.au/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('the-bend-motorsport-park','Shell V-Power Motorsport Park at The Bend','SA','Tailem Bend','permanent','Integrated circuit, dragway, karting and rally/off-road facility with a venue-published interactive calendar.','https://www.thebend.com.au/','https://www.thebend.com.au/event-calendar','venue','https://www.thebend.com.au/event-calendar',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('queensland-raceway','Queensland Raceway','QLD','Willowbank','permanent','Circuit venue publishing a live calendar for car, motorcycle, drift, roll-racing and track activity.','https://www.qldraceways.com.au/','https://www.qldraceways.com.au/calendar','venue','https://www.qldraceways.com.au/calendar',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('winton-motor-raceway','Winton Motor Raceway','VIC','Winton','permanent','Circuit venue hosting racing, sprints, test-and-tune, track days and show-and-shine events.','https://www.wintonraceway.com.au/','https://www.wintonraceway.com.au/calendar','venue','https://www.wintonraceway.com.au/calendar',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('willowbank-raceway','Willowbank Raceway','QLD','Willowbank','permanent','Dedicated drag-racing venue with a venue-published season calendar, test-and-tune and motorcycle dates.','https://www.willowbankraceway.com.au/','https://willowbankraceway.com.au/news/2026-calendar/','venue','https://willowbankraceway.com.au/news/2026-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('perth-motorplex','Perth Motorplex','WA','Kwinana Beach','permanent','Combined drag-racing and speedway venue with current event listings on its official site.','https://www.motorplex.com.au/','https://www.motorplex.com.au/events-racing/','venue','https://www.motorplex.com.au/events-racing/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),

('coffs-harbour-kart-track','Coffs Harbour Kart Racing Club','NSW','Coffs Harbour','permanent','Karting venue listed on Karting Australia’s 2026 national calendar.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('ipswich-kart-track','Ipswich karting venue','QLD','Ipswich','permanent','Karting venue listed on Karting Australia’s 2026 national calendar.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('townsville-kart-track','Townsville karting venue','QLD','Townsville','permanent','Karting venue listed on Karting Australia’s 2026 national calendar.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('seymour-kart-track','Seymour karting venue','VIC','Seymour','permanent','Karting venue listed on Karting Australia’s 2026 national calendar.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('bolivar-kart-track','Bolivar karting venue','SA','Bolivar','permanent','Karting venue listed on Karting Australia’s 2026 national calendar.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('albury-wodonga-kart-club','Albury Wodonga Kart Club','VIC','Albury Wodonga','permanent','Kart club venue listed for nationally recognised and state competition on the official calendar.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('darwin-kart-track','Darwin karting venue','NT','Darwin','permanent','Host location listed for the 2026 Northern Territory Kart Championship.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('bundaberg-kart-track','Bundaberg karting venue','QLD','Bundaberg','permanent','Host location listed for the 2026 Queensland Kart Championship.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('geraldton-kart-track','Geraldton karting venue','WA','Geraldton','permanent','Host location listed for the 2026 Western Australian Kart Championship.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('eastern-creek-kart-track','Eastern Creek karting venue','NSW','Eastern Creek','permanent','Host location listed for the 2026 New South Wales Kart Championship.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('monarto-kart-track','Monarto karting venue','SA','Monarto','permanent','Host location listed for the 2026 South Australian Kart Championship.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('smithton-kart-track','Smithton karting venue','TAS','Smithton','permanent','Host location listed for the 2026 Tasmanian Kart Championship.',NULL,'https://www.karting.net.au/ka-calendar/','governing_body','https://www.karting.net.au/ka-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),

('diamond-park-wodonga','Diamond Park','VIC','Wodonga','permanent','Motorcycle dirt-track venue listed by Motorcycling Australia for the 2026 Australian Junior Dirt Track Championship.',NULL,'https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/','governing_body','https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('fairbairn-park-canberra','Fairbairn Park','ACT','Canberra','permanent','Motorcycle venue listed by Motorcycling Australia for the 2026 Australian Senior Dirt Track Championship.',NULL,'https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/','governing_body','https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('brandon-sports-reserve','Brandon Sports Reserve','QLD','Ayr','permanent','Motorcycle venue listed by Motorcycling Australia for the 2026 Australian Senior Track Championship.',NULL,'https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/','governing_body','https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
('allen-park-somersby','Allen Park','NSW','Somersby','permanent','Motorcycle venue listed by Motorcycling Australia for the 2026 Australian Junior Track Championship.',NULL,'https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/','governing_body','https://www.ma.org.au/2026-australian-track-and-dirt-track-calendar/',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),

('rally-road-event-locations','Rally and road-event locations','AUS','Event-specific locations','event_route','Rally, rallysprint, tarmac and navigational events commonly use approved event-specific roads, stages or forest routes rather than a permanent venue.',NULL,'https://motorsport.org.au/events/championships/state/','governing_body','https://motorsport.org.au/events/championships/state/',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW()),
('off-road-event-locations','Off-road event locations','AUS','Event-specific locations','event_route','Off-road events use approved desert, bush, stadium or private-property courses published with the event permit and supplementary regulations.',NULL,'https://motorsport.org.au/events/championships/state/','governing_body','https://motorsport.org.au/events/championships/state/',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW()),
('auto-test-club-locations','Auto-test and club-event locations','AUS','Club and event-specific locations','club_network','Motorkhana, khanacross, observed-section and participation formats may operate at club grounds, skid pans or temporary approved sites.',NULL,'https://motorsport.org.au/events/championships/state/','governing_body','https://motorsport.org.au/events/championships/state/',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW());

INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,f.family_key FROM motorsport_venues v CROSS JOIN motorsport_families f
WHERE v.slug='sydney-motorsport-park' AND f.family_key IN ('circuit','speed-drift','motorcycle');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,f.family_key FROM motorsport_venues v CROSS JOIN motorsport_families f
WHERE v.slug='phillip-island-grand-prix-circuit' AND f.family_key IN ('circuit','motorcycle');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,f.family_key FROM motorsport_venues v CROSS JOIN motorsport_families f
WHERE v.slug='the-bend-motorsport-park' AND f.family_key IN ('circuit','drag','karting','speed-drift','off-road','motorcycle');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,f.family_key FROM motorsport_venues v CROSS JOIN motorsport_families f
WHERE v.slug IN ('queensland-raceway','winton-motor-raceway') AND f.family_key IN ('circuit','speed-drift','motorcycle');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,'drag' FROM motorsport_venues v WHERE v.slug='willowbank-raceway';
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,f.family_key FROM motorsport_venues v CROSS JOIN motorsport_families f
WHERE v.slug='perth-motorplex' AND f.family_key IN ('drag','speedway');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,'karting' FROM motorsport_venues v WHERE v.slug LIKE '%kart%' OR v.slug IN ('albury-wodonga-kart-club');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,'motorcycle' FROM motorsport_venues v WHERE v.slug IN ('diamond-park-wodonga','fairbairn-park-canberra','brandon-sports-reserve','allen-park-somersby');
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,'rally-road' FROM motorsport_venues v WHERE v.slug='rally-road-event-locations';
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,'off-road' FROM motorsport_venues v WHERE v.slug='off-road-event-locations';
INSERT INTO motorsport_venue_families (venue_id,family_key)
SELECT v.id,'auto-test' FROM motorsport_venues v WHERE v.slug='auto-test-club-locations';
