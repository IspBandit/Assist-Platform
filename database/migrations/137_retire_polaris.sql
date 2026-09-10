-- Retire Polaris from the active Assist Platform product boundary.
-- Preserve historical catalogue data for audit/due-diligence purposes, but remove
-- the brand from active domains and prevent its listings from appearing publicly.

UPDATE brands
SET status='disabled', updated_at=NOW()
WHERE brand_key='polaris';

DELETE FROM brand_domains
WHERE brand_id=(SELECT id FROM brands WHERE brand_key='polaris');

UPDATE provider_brand_listings
SET status='suspended', search_visible=0, updated_at=NOW()
WHERE brand_id=(SELECT id FROM brands WHERE brand_key='polaris');
