-- Register the VanAssist-only previous-month website performance report.

INSERT INTO scheduled_tasks (task_key, description, last_status)
VALUES (
    'vanassist_monthly_performance_email',
    'Queue the previous calendar month VanAssist website performance report for support',
    'never'
)
ON DUPLICATE KEY UPDATE description=VALUES(description);
