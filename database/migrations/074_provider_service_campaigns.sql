-- Separate provider-only service campaigns from the existing mixed customer
-- and provider category audience. Existing values and behaviour are preserved.

ALTER TABLE notifications
    MODIFY COLUMN audience_type ENUM('town','region','category','provider_category','providers','customers_open','all') NOT NULL;
