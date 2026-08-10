-- Forward-only residual duplicate cleanup. Migration 129 remains immutable.
-- This catches exact-name records missed because one coordinate was absent or
-- because an imported state assignment disagreed with the authority record.
CREATE TEMPORARY TABLE residual_stay_merge_map (
    duplicate_id INT UNSIGNED NOT NULL PRIMARY KEY,
    survivor_id INT UNSIGNED NOT NULL,
    merge_rule VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE residual_stay_candidates (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    state_id INT UNSIGNED NULL,
    normalised_name VARCHAR(190) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    source_type VARCHAR(40) NULL,
    external_id VARCHAR(100) NULL,
    trust_rank TINYINT UNSIGNED NOT NULL,
    KEY idx_residual_stay_name (normalised_name, trust_rank, id),
    KEY idx_residual_stay_source (source_type, external_id, trust_rank, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO residual_stay_candidates
    (id,state_id,normalised_name,latitude,longitude,source_type,external_id,trust_rank)
SELECT id,state_id,LOWER(TRIM(name)),latitude,longitude,source_type,external_id,
       CASE verification_type WHEN 'authority' THEN 4 WHEN 'operator' THEN 3 WHEN 'community' THEN 2 ELSE 1 END
FROM caravan_parks
WHERE deleted_at IS NULL AND status = 'active' AND TRIM(name) <> '';

CREATE TEMPORARY TABLE residual_stay_winners LIKE residual_stay_candidates;
INSERT INTO residual_stay_winners SELECT * FROM residual_stay_candidates;
CREATE TEMPORARY TABLE residual_stay_betters LIKE residual_stay_candidates;
INSERT INTO residual_stay_betters SELECT * FROM residual_stay_candidates;

-- Exact normalised name and coordinates within 2 km. State is deliberately
-- not part of the identity: imported boundary/state assignments can be wrong.
INSERT INTO residual_stay_merge_map (duplicate_id,survivor_id,merge_rule)
SELECT loser.id,winner.id,'same_name_within_2km'
FROM residual_stay_candidates loser
JOIN residual_stay_winners winner
  ON winner.id <> loser.id
 AND winner.normalised_name = loser.normalised_name
 AND loser.latitude IS NOT NULL AND loser.longitude IS NOT NULL
 AND winner.latitude IS NOT NULL AND winner.longitude IS NOT NULL
 AND (111.045 * DEGREES(ACOS(LEAST(1.0,
       COS(RADIANS(loser.latitude)) * COS(RADIANS(winner.latitude))
       * COS(RADIANS(loser.longitude) - RADIANS(winner.longitude))
       + SIN(RADIANS(loser.latitude)) * SIN(RADIANS(winner.latitude)))))) <= 2
 AND (winner.trust_rank > loser.trust_rank OR (winner.trust_rank = loser.trust_rank AND winner.id < loser.id))
WHERE NOT EXISTS (
    SELECT 1 FROM residual_stay_betters better
    WHERE better.id <> loser.id
      AND better.normalised_name = loser.normalised_name
      AND better.latitude IS NOT NULL AND better.longitude IS NOT NULL
      AND (111.045 * DEGREES(ACOS(LEAST(1.0,
          COS(RADIANS(loser.latitude)) * COS(RADIANS(better.latitude))
          * COS(RADIANS(loser.longitude) - RADIANS(better.longitude))
          + SIN(RADIANS(loser.latitude)) * SIN(RADIANS(better.latitude)))))) <= 2
      AND (better.trust_rank > winner.trust_rank OR (better.trust_rank = winner.trust_rank AND better.id < winner.id))
);

-- Repeated imports with the same source identity are duplicates even when one
-- row has incomplete coordinates.
INSERT IGNORE INTO residual_stay_merge_map (duplicate_id,survivor_id,merge_rule)
SELECT loser.id,winner.id,'same_source_identity'
FROM residual_stay_candidates loser
JOIN residual_stay_winners winner
  ON winner.id <> loser.id
 AND winner.source_type = loser.source_type
 AND winner.external_id = loser.external_id
 AND loser.source_type IS NOT NULL AND loser.external_id IS NOT NULL
 AND (winner.trust_rank > loser.trust_rank OR (winner.trust_rank = loser.trust_rank AND winner.id < loser.id))
WHERE NOT EXISTS (
    SELECT 1 FROM residual_stay_betters better
    WHERE better.id <> loser.id
      AND better.source_type = loser.source_type AND better.external_id = loser.external_id
      AND (better.trust_rank > winner.trust_rank OR (better.trust_rank = winner.trust_rank AND better.id < winner.id))
);

-- An authority record may intentionally omit a point coordinate. Within the
-- same state, an exact-name imported record supplies a safe residual match
-- only when the authority address itself repeats that full distinctive name.
INSERT IGNORE INTO residual_stay_merge_map (duplicate_id,survivor_id,merge_rule)
SELECT loser.id,winner.id,'authority_name_state_missing_coordinates'
FROM residual_stay_candidates loser
JOIN residual_stay_winners winner
  ON winner.id <> loser.id
 AND winner.normalised_name = loser.normalised_name
 AND (winner.state_id <=> loser.state_id OR winner.state_id IS NULL OR loser.state_id IS NULL)
 AND winner.trust_rank = 4 AND loser.trust_rank < 4
 AND (winner.latitude IS NULL OR winner.longitude IS NULL OR loser.latitude IS NULL OR loser.longitude IS NULL)
JOIN caravan_parks authority_park ON authority_park.id=winner.id
 AND LOWER(COALESCE(authority_park.address,'')) LIKE CONCAT('%',loser.normalised_name,'%');

-- Prevent chains from moving a loser into another loser. MariaDB temporary
-- tables cannot be reopened under two aliases, so use a separate copy and
-- conservatively retain only direct-to-terminal merges.
CREATE TEMPORARY TABLE residual_stay_merge_parents LIKE residual_stay_merge_map;
INSERT INTO residual_stay_merge_parents SELECT * FROM residual_stay_merge_map;
DELETE m FROM residual_stay_merge_map m
JOIN residual_stay_merge_parents parent ON parent.duplicate_id=m.survivor_id;
DELETE m FROM residual_stay_merge_map m WHERE m.duplicate_id=m.survivor_id;

-- Preserve the best available point on the trusted survivor. This is vital
-- for nearby search: authority pages often provide an address but no
-- coordinates, while the merged geospatial import has a usable point.
UPDATE caravan_parks survivor
JOIN residual_stay_merge_map m ON m.survivor_id=survivor.id
JOIN caravan_parks duplicate ON duplicate.id=m.duplicate_id
SET survivor.latitude=COALESCE(survivor.latitude,duplicate.latitude),
    survivor.longitude=COALESCE(survivor.longitude,duplicate.longitude),
    survivor.town_id=COALESCE(survivor.town_id,duplicate.town_id),
    survivor.region_id=COALESCE(survivor.region_id,duplicate.region_id),
    survivor.state_id=COALESCE(survivor.state_id,duplicate.state_id),
    survivor.address=COALESCE(NULLIF(survivor.address,''),duplicate.address),
    survivor.updated_at=NOW();

INSERT IGNORE INTO caravan_park_source_aliases (park_id,source_type,external_id,source_url,created_at,updated_at)
SELECT m.survivor_id,p.source_type,p.external_id,p.source_url,NOW(),NOW()
FROM residual_stay_merge_map m JOIN caravan_parks p ON p.id=m.duplicate_id
WHERE p.source_type IS NOT NULL AND p.external_id IS NOT NULL;

INSERT IGNORE INTO caravan_park_users (park_id,user_id,role,created_at)
SELECT m.survivor_id,u.user_id,u.role,u.created_at FROM caravan_park_users u JOIN residual_stay_merge_map m ON m.duplicate_id=u.park_id;
DELETE u FROM caravan_park_users u JOIN residual_stay_merge_map m ON m.duplicate_id=u.park_id;
UPDATE service_requests x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_park_documents x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_park_service_day_requests x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_park_claims x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE caravan_stay_import_candidates x JOIN residual_stay_merge_map m ON m.duplicate_id=x.duplicate_park_id SET x.duplicate_park_id=m.survivor_id;
UPDATE caravan_stay_import_candidates x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;
UPDATE facility_contributions x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;

UPDATE stay_facility_claims loser
JOIN residual_stay_merge_map m ON m.duplicate_id=loser.park_id
JOIN stay_facility_claims winner ON winner.park_id=m.survivor_id
 AND winner.source_type=loser.source_type AND winner.source_record_id=loser.source_record_id
 AND winner.superseded_at IS NULL
SET loser.superseded_at=COALESCE(loser.superseded_at,NOW()),loser.updated_at=NOW()
WHERE loser.source_record_id IS NOT NULL;
UPDATE stay_facility_claims x JOIN residual_stay_merge_map m ON m.duplicate_id=x.park_id SET x.park_id=m.survivor_id;

INSERT INTO audit_logs (user_id,action,object_type,object_id,previous_value,new_value,created_at)
SELECT NULL,'stay.duplicate_merged','caravan_park',CAST(m.survivor_id AS CHAR),
 JSON_OBJECT('duplicate_id',m.duplicate_id,'source_type',p.source_type,'external_id',p.external_id),
 JSON_OBJECT('survivor_id',m.survivor_id,'rule',m.merge_rule,'automatic',TRUE,
             'location_fields_preserved',TRUE),NOW()
FROM residual_stay_merge_map m JOIN caravan_parks p ON p.id=m.duplicate_id;

UPDATE caravan_parks p JOIN residual_stay_merge_map m ON m.duplicate_id=p.id
SET p.public_page_enabled=0,p.status='rejected',p.deleted_at=NOW(),p.updated_at=NOW();

DROP TEMPORARY TABLE residual_stay_merge_map;
DROP TEMPORARY TABLE residual_stay_candidates;
DROP TEMPORARY TABLE residual_stay_winners;
DROP TEMPORARY TABLE residual_stay_betters;
DROP TEMPORARY TABLE residual_stay_merge_parents;
