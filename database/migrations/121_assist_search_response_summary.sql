-- Privacy-safe Ask VanAssist learning record.
-- Stores the user-visible response summary, never raw GPS or contact details.

ALTER TABLE assist_searches
    ADD COLUMN response_summary JSON NULL AFTER fallback_reason;
