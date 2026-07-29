-- Run provider discovery screening and safe publication without an open admin browser.

INSERT INTO scheduled_tasks (task_key,description,last_status)
VALUES ('process_provider_import_queue','Resume national discovery screening, merge safe duplicates, publish evidence-confirmed unclaimed listings, and refresh provider campaign drafts.','never')
ON DUPLICATE KEY UPDATE description=VALUES(description);

ALTER TABLE notifications
    ADD COLUMN provider_brand_category_id INT UNSIGNED NULL AFTER category_id,
    ADD KEY idx_notifications_brand_provider_category (brand_id,provider_brand_category_id,campaign_type,status),
    ADD CONSTRAINT fk_notifications_brand_provider_category FOREIGN KEY (provider_brand_category_id) REFERENCES brand_provider_categories (id) ON DELETE SET NULL;

UPDATE notifications n
INNER JOIN service_categories sc ON sc.id=n.category_id
INNER JOIN brand_provider_categories bpc ON bpc.brand_id=n.brand_id AND bpc.category_key=sc.slug
SET n.provider_brand_category_id=bpc.id
WHERE n.audience_type='provider_category' AND n.provider_brand_category_id IS NULL;
