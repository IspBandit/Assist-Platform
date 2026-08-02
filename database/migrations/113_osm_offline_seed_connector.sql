-- Migration 097: Offline OSM seed connector for Ask/dataset staging (no live Overpass).

INSERT INTO data_source_connectors (connector_key, name, connector_class, status, daily_request_limit, daily_budget_aud, created_at, updated_at)
VALUES (
    'osm_offline_seed',
    'OpenStreetMap offline AU seed',
    'App\\Platform\\DataSources\\Connectors\\OsmOfflineSeedConnector',
    'configured',
    0,
    0.00,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    connector_class = VALUES(connector_class),
    updated_at = NOW();
