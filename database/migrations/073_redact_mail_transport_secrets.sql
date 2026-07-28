-- Remove credential material captured by the former Graph HTTP-status fallback.
UPDATE email_queue
SET last_error = 'Microsoft Graph transport error (sensitive response redacted); retry delivery test.'
WHERE last_error LIKE '%"access_token"%'
   OR last_error LIKE '%Bearer eyJ%';

UPDATE email_log
SET error = 'Microsoft Graph transport error (sensitive response redacted); retry delivery test.'
WHERE error LIKE '%"access_token"%'
   OR error LIKE '%Bearer eyJ%';

UPDATE system_logs
SET message = 'Microsoft Graph transport error (sensitive response redacted).',
    context_json = NULL
WHERE message LIKE '%"access_token"%'
   OR message LIKE '%Bearer eyJ%'
   OR context_json LIKE '%"access_token"%'
   OR context_json LIKE '%Bearer eyJ%';
