-- Register the VanAssist-only previous-day website performance report.

INSERT INTO scheduled_tasks (task_key, description, last_status)
VALUES (
    'vanassist_daily_performance_email',
    'Queue the previous day VanAssist website performance report for support',
    'never'
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
