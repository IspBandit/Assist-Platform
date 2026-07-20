-- Product modules for all three brands now have real public journeys.
-- Activate existing rows without changing stable brand IDs or namespaces.
UPDATE brands
SET status = 'active', updated_at = NOW()
WHERE id IN (1, 2, 3) AND status IN ('private', 'coming_soon', 'disabled');
