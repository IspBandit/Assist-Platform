-- 018: provider street address (for on-site display + mobile maps/navigation)
-- Stores the source location string for each listing. Workshop businesses have a
-- real street address (used to build "Get directions" links that open the
-- visitor's native maps app and route from their GPS position); mobile
-- businesses store their service-area text instead.
ALTER TABLE providers
    ADD COLUMN street_address VARCHAR(255) NULL AFTER region_id;
