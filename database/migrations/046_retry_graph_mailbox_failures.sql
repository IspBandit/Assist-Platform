-- COM-001 production recovery: retry only messages rejected by Microsoft Graph
-- after brand aliases were incorrectly used as /users/{mailbox}/sendMail targets.
-- The normal queue worker will claim these rows after deployment. Other failed
-- mail remains untouched for deliberate administrator review.

UPDATE email_queue
SET status = 'pending', attempts = 0, leased_until = NULL,
    lease_token = NULL, next_attempt_at = NULL
WHERE status = 'failed'
  AND last_error LIKE 'Microsoft Graph request failed%';
