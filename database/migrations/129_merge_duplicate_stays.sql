-- Canonical source aliases prevent a merged import record being recreated.
CREATE TABLE IF NOT EXISTS caravan_park_source_aliases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id INT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    source_url VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_park_source_alias (source_type, external_id),
    KEY idx_park_source_alias_park (park_id),
    CONSTRAINT fk_park_source_alias_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Only merge high-confidence pairs: same normalised name and state, within 2 km.
-- Authority/operator records win, then verified/community records, then oldest id.
CREATE TEMPORARY TABLE stay_duplicate_merge_map (
    duplicate_id INT UNSIGNED NOT NULL PRIMARY KEY,
    survivor_id INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO stay_duplicate_merge_map (duplicate_id, survivor_id)
SELECT loser.id, winner.id
FROM caravan_parks loser
JOIN caravan_parks winner
  ON winner.id <> loser.id
 AND winner.state_id <=> loser.state_id
 AND LOWER(TRIM(winner.name)) = LOWER(TRIM(loser.name))
 AND winner.deleted_at IS NULL AND loser.deleted_at IS NULL
 AND winner.latitude IS NOT NULL AND winner.longitude IS NOT NULL
 AND loser.latitude IS NOT NULL AND loser.longitude IS NOT NULL
 AND (111.045 * DEGREES(ACOS(LEAST(1.0,
       COS(RADIANS(loser.latitude)) * COS(RADIANS(winner.latitude))
       * COS(RADIANS(loser.longitude) - RADIANS(winner.longitude))
       + SIN(RADIANS(loser.latitude)) * SIN(RADIANS(winner.latitude)))))) <= 2
 AND ((CASE winner.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END)
      > (CASE loser.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END)
   OR ((CASE winner.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END)
      = (CASE loser.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END) AND winner.id < loser.id))
WHERE NOT EXISTS (
    SELECT 1 FROM caravan_parks better
    WHERE better.id <> loser.id AND better.deleted_at IS NULL
      AND better.state_id <=> loser.state_id
      AND LOWER(TRIM(better.name)) = LOWER(TRIM(loser.name))
      AND better.latitude IS NOT NULL AND better.longitude IS NOT NULL
      AND (111.045 * DEGREES(ACOS(LEAST(1.0,
          COS(RADIANS(loser.latitude)) * COS(RADIANS(better.latitude))
          * COS(RADIANS(loser.longitude) - RADIANS(better.longitude))
          + SIN(RADIANS(loser.latitude)) * SIN(RADIANS(better.latitude)))))) <= 2
      AND ((CASE better.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END)
          > (CASE winner.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END)
       OR ((CASE better.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END)
          = (CASE winner.verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END) AND better.id < winner.id))
);

INSERT IGNORE INTO caravan_park_source_aliases (park_id, source_type, external_id, source_url, created_at, updated_at)
SELECT m.survivor_id, p.source_type, p.external_id, p.source_url, NOW(), NOW()
FROM stay_duplicate_merge_map m JOIN caravan_parks p ON p.id=m.duplicate_id
WHERE p.source_type IS NOT NULL AND p.external_id IS NOT NULL;

INSERT IGNORE INTO caravan_park_users (park_id,user_id,role,created_at)
SELECT m.survivor_id,u.user_id,u.role,u.created_at FROM caravan_park_users u JOIN stay_duplicate_merge_map m ON m.duplicate_id=u.park_id;
DELETE u FROM caravan_park_users u JOIN stay_duplicate_merge_map m ON m.duplicate_id=u.park_id;
UPDATE service_requests x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_park_documents x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_park_service_day_requests x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_park_claims x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_stay_import_candidates x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.duplicate_park_id SET x.duplicate_park_id=m.survivor_id;
UPDATE caravan_stay_import_candidates x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE facility_contributions x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;

UPDATE stay_facility_claims loser
JOIN stay_duplicate_merge_map m ON m.duplicate_id=loser.park_id
JOIN stay_facility_claims winner ON winner.park_id=m.survivor_id
 AND winner.source_type=loser.source_type AND winner.source_record_id=loser.source_record_id
 AND winner.superseded_at IS NULL
SET loser.superseded_at=COALESCE(loser.superseded_at,NOW()), loser.updated_at=NOW()
WHERE loser.source_record_id IS NOT NULL;
UPDATE stay_facility_claims x JOIN stay_duplicate_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;

INSERT INTO audit_logs (user_id,action,object_type,object_id,previous_value,new_value,created_at)
SELECT NULL,'stay.duplicate_merged','caravan_park',CAST(m.survivor_id AS CHAR),
 JSON_OBJECT('duplicate_id',m.duplicate_id,'source_type',p.source_type,'external_id',p.external_id),
 JSON_OBJECT('survivor_id',m.survivor_id,'rule','same_name_state_within_2km','automatic',TRUE),NOW()
FROM stay_duplicate_merge_map m JOIN caravan_parks p ON p.id=m.duplicate_id;

UPDATE caravan_parks p JOIN stay_duplicate_merge_map m ON m.duplicate_id=p.id
SET p.public_page_enabled=0,p.status='rejected',p.deleted_at=NOW(),p.updated_at=NOW();

DROP TEMPORARY TABLE stay_duplicate_merge_map;
