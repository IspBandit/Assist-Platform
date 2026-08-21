-- DATA-008: street rods use their own construction, approval and registration
-- pathway. Remove the legacy street-rod tag from ordinary modification records;
-- migration 054 provides the dedicated jurisdiction records.
UPDATE regulatory_documents
SET vehicle_classes_json = JSON_REMOVE(
    vehicle_classes_json,
    JSON_UNQUOTE(JSON_SEARCH(vehicle_classes_json, 'one', 'street-rod'))
)
WHERE document_kind NOT IN ('street_rods', 'registration')
  AND JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('street-rod'));
