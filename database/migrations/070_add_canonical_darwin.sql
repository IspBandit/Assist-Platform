-- The Australia Post-derived delivery list omitted canonical Darwin while
-- retaining postal facility variants. Restore the official population centre.
-- Source: Composite Gazetteer of Australia, NT authority record NT_12218.
INSERT INTO towns (
    state_id,region_id,name,slug,primary_postcode,latitude,longitude,
    coordinate_source,coordinate_confidence,coordinate_reference,coordinate_verified_at,
    is_active,is_featured,is_launch_town,noindex,created_at,updated_at
)
SELECT s.id,r.id,'Darwin','darwin','0800',-12.4615000,130.8425000,
       'nt-place-names-register','authoritative','NT_12218',CURRENT_DATE,
       1,1,1,0,NOW(),NOW()
FROM states s
JOIN regions r ON r.state_id=s.id AND r.slug='darwin-top-end'
WHERE s.abbreviation='NT'
  AND NOT EXISTS (SELECT 1 FROM towns t WHERE t.state_id=s.id AND t.slug='darwin');
