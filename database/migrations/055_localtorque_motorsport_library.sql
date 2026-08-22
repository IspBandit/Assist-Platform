-- DATA-010 / LOC-004: LocalTorque motorsport rules catalogue.
-- Motorsport sanctioning-body rules are intentionally separate from government
-- road-registration law. Links remain owned and published by each authority.

CREATE TABLE motorsport_authorities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    authority_key VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    official_url VARCHAR(1000) NOT NULL,
    authority_role VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_motorsport_authority_key (authority_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorsport_families (
    family_key VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (family_key),
    UNIQUE KEY uq_motorsport_family_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorsport_disciplines (
    discipline_key VARCHAR(80) NOT NULL,
    family_key VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (discipline_key),
    KEY idx_motorsport_discipline_family (family_key, display_order),
    CONSTRAINT fk_motorsport_discipline_family FOREIGN KEY (family_key) REFERENCES motorsport_families (family_key) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorsport_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    authority_id INT UNSIGNED NOT NULL,
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(300) NOT NULL,
    summary TEXT NOT NULL,
    jurisdictions_json JSON NOT NULL,
    rule_types_json JSON NOT NULL,
    document_level ENUM('national','state','category','event') NOT NULL,
    source_url VARCHAR(1000) NOT NULL,
    download_url VARCHAR(1000) NULL,
    version_label VARCHAR(120) NULL,
    publication_status ENUM('current','review','superseded','withdrawn') NOT NULL DEFAULT 'current',
    official_document TINYINT(1) NOT NULL DEFAULT 1,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    content_hash CHAR(64) NULL,
    source_etag VARCHAR(500) NULL,
    source_last_modified VARCHAR(190) NULL,
    check_interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    change_detected_at DATETIME NULL,
    last_checked_at DATETIME NULL,
    next_check_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_motorsport_document_slug (slug),
    KEY idx_motorsport_document_public (is_public, publication_status, document_level),
    CONSTRAINT fk_motorsport_document_authority FOREIGN KEY (authority_id) REFERENCES motorsport_authorities (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorsport_document_families (
    document_id INT UNSIGNED NOT NULL,
    family_key VARCHAR(40) NOT NULL,
    PRIMARY KEY (document_id, family_key),
    KEY idx_motorsport_document_family (family_key, document_id),
    CONSTRAINT fk_motorsport_df_document FOREIGN KEY (document_id) REFERENCES motorsport_documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_motorsport_df_family FOREIGN KEY (family_key) REFERENCES motorsport_families (family_key) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO motorsport_authorities (authority_key,name,official_url,authority_role,created_at) VALUES
('motorsport-australia','Motorsport Australia','https://motorsport.org.au/regulations/','National sporting body publishing the Manual of Motorsport, competition rules, technical appendices and state or regional regulations.',NOW()),
('aasa','Australian Auto-Sport Alliance','https://www.aasa.com.au/general-and-category-regulations','Sanctioning body publishing general, circuit, rally, off-road, drift, hillclimb, burnout and category regulations for AASA events.',NOW()),
('andra','Australian National Drag Racing Association','https://www.andra.com.au/andra-rulebook-2/','National drag-racing body publishing competitor, vehicle, technical, licensing and safety rules for cars and motorcycles.',NOW()),
('speedway-australia','Speedway Australia','https://www.speedwayaustralia.org/','National speedway body publishing racing rules. Division-specific vehicle specifications may be issued separately.',NOW()),
('karting-australia','Karting Australia','https://www.karting.net.au/administration/rules/','National karting body publishing national competition, state, class, homologation, circuit and safety rules.',NOW()),
('motorcycling-australia','Motorcycling Australia','https://www.ma.org.au/licences-rules/rules/general-competition-rules/','National motorcycle-sport body publishing the General Competition Rules and Manual of Motorcycle Sport.',NOW());

INSERT INTO motorsport_families (family_key,name,display_order,created_at) VALUES
('circuit','Circuit, track & historic',10,NOW()),('rally-road','Rally & road events',20,NOW()),
('off-road','Off-road competition',30,NOW()),('speed-drift','Speed, drift & handling',40,NOW()),
('auto-test','Auto tests & participation events',50,NOW()),('drag','Drag racing',60,NOW()),
('speedway','Speedway & oval',70,NOW()),('karting','Karting',80,NOW()),('motorcycle','Motorcycle sport',90,NOW());

INSERT INTO motorsport_disciplines (discipline_key,family_key,name,display_order,created_at) VALUES
('circuit-racing','circuit','Circuit racing',10,NOW()),('regularity','circuit','Regularity trials',20,NOW()),('supersprint','circuit','Supersprint',30,NOW()),('superkart','circuit','Superkart',40,NOW()),('track-day-test-tune','circuit','Track day, practice & test-and-tune',50,NOW()),('historic-circuit','circuit','Historic circuit competition',60,NOW()),('electric-vehicle','circuit','Electric vehicle competition',70,NOW()),('esports','circuit','Motorsport esports',80,NOW()),
('rally','rally-road','Rally',10,NOW()),('rallysprint','rally-road','Rallysprint',20,NOW()),('tarmac-rally','rally-road','Tarmac rally',30,NOW()),('cross-country-rally','rally-road','Cross-country rally',40,NOW()),('rallycross','rally-road','Rallycross',50,NOW()),('touring-navigation','rally-road','Touring, navigational assembly & road events',60,NOW()),
('off-road-racing','off-road','Off-road racing',10,NOW()),('stadium-off-road','off-road','Stadium off-road',20,NOW()),('side-by-side','off-road','Side-by-side / SxS',30,NOW()),('off-road-kart','off-road','Off-road kart',40,NOW()),
('hillclimb','speed-drift','Hillclimb',10,NOW()),('sprint','speed-drift','Sprint',20,NOW()),('autocross','speed-drift','Autocross',30,NOW()),('drift','speed-drift','Drifting',40,NOW()),('roll-racing','speed-drift','Roll racing',50,NOW()),
('motorkhana','auto-test','Motorkhana',10,NOW()),('khanacross','auto-test','Khanacross',20,NOW()),('observed-section-trial','auto-test','Observed section trial',30,NOW()),('driftkhana','auto-test','Driftkhana',40,NOW()),('burnout','auto-test','Burnout competition',50,NOW()),('go-to-whoa','auto-test','Go-to-whoa',60,NOW()),('dyno','auto-test','Dyno competition',70,NOW()),('show-shine','auto-test','Show & shine',80,NOW()),
('drag-racing-cars','drag','Drag racing — cars',10,NOW()),('drag-racing-motorcycles','drag','Drag racing — motorcycles',20,NOW()),('junior-drag','drag','Junior dragster & junior drag bike',30,NOW()),
('speedway-oval','speedway','Speedway oval racing',10,NOW()),('sprintcars','speedway','Sprintcars',20,NOW()),('speedcars-midgets','speedway','Speedcars / midgets',30,NOW()),('sedans-stock-cars','speedway','Sedans & stock cars',40,NOW()),('modifieds','speedway','Modifieds',50,NOW()),('wingless','speedway','Wingless competition',60,NOW()),('demolition-derby','speedway','Demolition derby',70,NOW()),
('sprint-karting','karting','Sprint karting',10,NOW()),('endurance-karting','karting','Endurance karting',20,NOW()),
('motorcycle-road-race','motorcycle','Road race',10,NOW()),('motorcycle-historic-road-race','motorcycle','Historic road race',20,NOW()),('motocross','motorcycle','Motocross',30,NOW()),('supercross','motorcycle','Supercross',40,NOW()),('classic-motocross','motorcycle','Classic motocross',50,NOW()),('classic-dirt-track','motorcycle','Classic dirt track',60,NOW()),('enduro','motorcycle','Enduro',70,NOW()),('reliability-trials','motorcycle','Reliability trials',80,NOW()),('atv','motorcycle','ATV competition',90,NOW()),('motorcycle-speedway','motorcycle','Motorcycle speedway',100,NOW()),('dirt-track-track','motorcycle','Dirt track & track',110,NOW()),('supermoto','motorcycle','Supermoto',120,NOW()),('motorcycle-trial','motorcycle','Motorcycle trial',130,NOW()),('minikhana','motorcycle','Minikhana',140,NOW()),('electric-motorcycle','motorcycle','Electric motorcycle competition',150,NOW());

INSERT INTO motorsport_documents (authority_id,slug,title,summary,jurisdictions_json,rule_types_json,document_level,source_url,download_url,version_label,last_checked_at,next_check_at,created_at) VALUES
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-manual','2026 Motorsport Australia Manual','The current national manual hub for competition, general, judicial, technical and discipline appendices. Individual category and vehicle-class documents on this hub also apply.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('competition','technical','safety','licensing'),'national','https://motorsport.org.au/regulations/manual/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-general','Motorsport Australia general, licence and permit regulations','Competition licences, track licences, permits, medical, integrity, insurance and general administration requirements.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('licensing','safety','competition'),'national','https://motorsport.org.au/regulations/manual/general-regulations/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-nsw-act','2026 Motorsport Australia NSW & ACT regulations','State championship and regional circuit, rally, off-road, speed and auto-test regulations. Event supplementary regulations can add event-specific requirements.',JSON_ARRAY('NSW','ACT'),JSON_ARRAY('state','competition','technical','event'),'state','https://motorsport.org.au/regulations/sporting-technical/nsw/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-qld','2026 Motorsport Australia Queensland regulations','Queensland championship and regional circuit, rally, off-road, speed and auto-test regulations.',JSON_ARRAY('QLD'),JSON_ARRAY('state','competition','technical','event'),'state','https://motorsport.org.au/regulations/sporting-technical/qld/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-vic','2026 Motorsport Australia Victorian regulations','Victorian circuit, rally, hillclimb, khanacross, motorkhana, supersprint and other state regulations.',JSON_ARRAY('VIC'),JSON_ARRAY('state','competition','technical','event'),'state','https://motorsport.org.au/regulations/sporting-technical/victoria/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-sa-nt','2026 Motorsport Australia SA & NT regulations','South Australia and Northern Territory circuit, rally, off-road, speed and auto-test regulations.',JSON_ARRAY('SA','NT'),JSON_ARRAY('state','competition','technical','event'),'state','https://motorsport.org.au/regulations/sporting-technical/sa/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-tas','2026 Motorsport Australia Tasmanian regulations','Tasmanian circuit, rally, speed, motorkhana, regularity and supersprint regulations.',JSON_ARRAY('TAS'),JSON_ARRAY('state','competition','technical','event'),'state','https://motorsport.org.au/regulations/sporting-technical/tasmania/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorsport-australia'),'motorsport-australia-wa','2026 Motorsport Australia Western Australian regulations','Western Australian championship and regional circuit, rally, off-road, speed and auto-test regulations.',JSON_ARRAY('WA'),JSON_ARRAY('state','competition','technical','event'),'state','https://motorsport.org.au/regulations/sporting-technical/wa/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='aasa'),'aasa-general-category-regulations','AASA general and category regulations','AASA national competition, circuit, tarmac and gravel rally, off-road, hillclimb, drifting, burnout, super-truck and category regulations.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('competition','technical','safety','licensing','event'),'national','https://www.aasa.com.au/general-and-category-regulations',NULL,'Current online set',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='andra'),'andra-rulebook','Current ANDRA Rulebook and page updates','Official drag-racing competitor handbook for car and motorcycle classes. Page updates and technical bulletins can take effect during the published season.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('competition','technical','safety','licensing','event'),'national','https://www.andra.com.au/andra-rulebook-2/',NULL,'2025/2026 current with updates',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='andra'),'andra-technical-documents','ANDRA technical documents and bulletins','Current rollcage, fuel, chassis, vehicle inspection, logbook and driver-rider technical procedures that supplement the rulebook.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('technical','safety','licensing'),'category','https://www.andra.com.au/technical/technicaldocumentation/',NULL,'Current online set',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='speedway-australia'),'speedway-australia-rulebook','Speedway Australia Racing Rules & Regulations','National racing, conduct, safety, licensing and event rules. Competitors must also obtain the current technical specification for their exact racing division and event supplementary regulations.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('competition','safety','licensing','event'),'national','https://www.speedwayaustralia.org/updated-rulebook','https://www.speedwayaustralia.org/media.ashx/speedway-australia-rulebook.pdf','Current published rulebook',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='karting-australia'),'karting-australia-rules','2026 Australian Karting Manual and state regulations','National Competition Rules plus current NSW, Queensland, South Australia, Victoria, Tasmania and Western Australia state regulations. Check the rule hub for updates.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('competition','technical','safety','licensing','state','event'),'national','https://www.karting.net.au/administration/rules/',NULL,'2026 update 1 and current state versions',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='karting-australia'),'karting-australia-safety','Karting Australia circuit and safety resources','Current circuit regulations, inspection resources, signage and private-practice safety rules.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('safety','technical'),'category','https://www.karting.net.au/administration/safety/',NULL,'2026 circuit regulations',NOW(),DATE_ADD(NOW(),INTERVAL 24 HOUR),NOW()),
((SELECT id FROM motorsport_authorities WHERE authority_key='motorcycling-australia'),'motorcycling-australia-moms','2026 Manual of Motorcycle Sport','General Competition Rules covering road race, historic road race, motocross, supercross, classic motocross and dirt track, enduro, reliability trials, ATV, speedway, dirt track, track, supermoto, trial, minikhana and electric motorcycles.',JSON_ARRAY('AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'),JSON_ARRAY('competition','technical','safety','licensing','event'),'national','https://www.ma.org.au/licences-rules/rules/general-competition-rules/',NULL,'2026',NOW(),DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW());

INSERT INTO motorsport_document_families (document_id,family_key)
SELECT d.id,f.family_key FROM motorsport_documents d CROSS JOIN motorsport_families f
WHERE d.slug IN ('motorsport-australia-manual','motorsport-australia-general','motorsport-australia-nsw-act','motorsport-australia-qld','motorsport-australia-vic','motorsport-australia-sa-nt','motorsport-australia-tas','motorsport-australia-wa')
  AND f.family_key IN ('circuit','rally-road','off-road','speed-drift','auto-test');

INSERT INTO motorsport_document_families (document_id,family_key)
SELECT d.id,f.family_key FROM motorsport_documents d CROSS JOIN motorsport_families f
WHERE d.slug='aasa-general-category-regulations' AND f.family_key IN ('circuit','rally-road','off-road','speed-drift','auto-test');

INSERT INTO motorsport_document_families (document_id,family_key)
SELECT d.id,'drag' FROM motorsport_documents d WHERE d.slug IN ('andra-rulebook','andra-technical-documents');
INSERT INTO motorsport_document_families (document_id,family_key)
SELECT d.id,'speedway' FROM motorsport_documents d WHERE d.slug='speedway-australia-rulebook';
INSERT INTO motorsport_document_families (document_id,family_key)
SELECT d.id,'karting' FROM motorsport_documents d WHERE d.slug IN ('karting-australia-rules','karting-australia-safety');
INSERT INTO motorsport_document_families (document_id,family_key)
SELECT d.id,'motorcycle' FROM motorsport_documents d WHERE d.slug='motorcycling-australia-moms';
