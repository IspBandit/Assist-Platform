-- VAN-011: Rebuild town_neighbours from measurable town coordinates.
-- The graph is computed by App\Services\Geography\TownNeighbourGraphBuilder
-- during php scripts/migrate.php (after town coordinate activation).
-- Clearing any stale fingerprint forces a rebuild on the next migrate run.

DELETE FROM site_settings
WHERE setting_key = 'town_neighbour_graph_fingerprint';
