-- DATA-008 follow-up: expose 4WD as a reader-friendly regulatory journey.
-- Australian 4WDs are regulated through the applicable passenger/light-vehicle
-- source scope rather than a standalone legal vehicle class. Keep the explicit
-- discovery label mapped only to records already scoped to cars or light trucks.

UPDATE regulatory_documents
SET vehicle_classes_json = JSON_ARRAY_APPEND(vehicle_classes_json, '$', '4wd'),
    updated_at = NOW()
WHERE NOT JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('4wd'))
  AND (
      JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('car'))
      OR JSON_CONTAINS(vehicle_classes_json, JSON_QUOTE('light-truck'))
  );
