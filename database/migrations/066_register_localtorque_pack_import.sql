-- Register the resumable authoritative provider-pack import.

INSERT INTO scheduled_tasks (task_key, description, last_status)
VALUES (
    'import_localtorque_pack',
    'Import the authoritative LocalTorque provider pack in resumable batches',
    'never'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
