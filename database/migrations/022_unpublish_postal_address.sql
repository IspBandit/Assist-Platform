-- =====================================================================
-- 022 Remove the public postal address from the Contact page. Data-only.
-- The address is still kept privately in site_settings (business_address) and
-- tax_settings (billing_address) for tax invoices; this only stops it being
-- published on the public website. Targeted REPLACE so an admin's other edits
-- to the page are preserved.
-- =====================================================================

UPDATE content_pages
   SET body = REPLACE(body, '<li><strong>Postal:</strong> 5 Kindilan Court, Boyne Island QLD 4680</li>', ''),
       updated_at = NOW()
 WHERE page_key = 'contact'
   AND body LIKE '%<strong>Postal:</strong>%';
