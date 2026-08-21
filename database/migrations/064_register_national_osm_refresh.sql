-- Register the resumable national source refresh independently of full seeds.

INSERT INTO scheduled_tasks (task_key, description, last_status)
VALUES (
    'refresh_osm',
    'Refresh national OpenStreetMap provider and fuel-station data one state or city at a time',
    'never'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
