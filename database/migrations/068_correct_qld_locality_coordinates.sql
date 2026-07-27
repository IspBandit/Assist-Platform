-- Correct postcode-centroid errors with authoritative Queensland place points.
-- Source: QLD Place Names Gazetteer, population centres (Queensland Government).
-- https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Location/QldPlaceNames/MapServer/1

UPDATE towns t
JOIN states s ON s.id = t.state_id
JOIN regions r ON r.state_id = s.id AND r.slug = 'fitzroy'
SET t.latitude = -23.5797200,
    t.longitude = 149.0705600,
    t.region_id = r.id,
    t.updated_at = NOW()
WHERE s.abbreviation = 'QLD' AND t.slug = 'bluff';

UPDATE towns t
JOIN states s ON s.id = t.state_id
SET t.latitude = -23.5208300,
    t.longitude = 148.1619400,
    t.updated_at = NOW()
WHERE s.abbreviation = 'QLD' AND t.slug = 'emerald';

UPDATE towns t
JOIN states s ON s.id = t.state_id
SET t.latitude = -23.2592568,
    t.longitude = 150.8238435,
    t.updated_at = NOW()
WHERE s.abbreviation = 'QLD' AND t.slug = 'emu-park';

-- Cached neighbour distances derived from the old coordinates are unsafe.
-- Removing only affected rows is preferable to displaying a false 7 km claim.
DELETE tn
FROM town_neighbours tn
JOIN towns t ON t.id = tn.town_id OR t.id = tn.neighbour_town_id
JOIN states s ON s.id = t.state_id
WHERE s.abbreviation = 'QLD' AND t.slug IN ('bluff', 'emerald', 'emu-park');
