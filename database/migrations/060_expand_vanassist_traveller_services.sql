-- Restore VanAssist as a whole-of-journey directory, not only a repair list.
-- These remain selectable service records so searches also create measurable
-- demand gaps in areas where a provider or facility has not yet been onboarded.

INSERT INTO service_categories
    (name, slug, short_description, sort_order, is_active, created_at, updated_at)
VALUES
    ('Fuel and travel stops', 'fuel-and-travel-stops', 'Find fuel stops suitable for tow vehicles, motorhomes and caravans.', 110, 1, NOW(), NOW()),
    ('EV charging', 'ev-charging', 'Charging locations and access information for electric tow vehicles and motorhomes.', 120, 1, NOW(), NOW()),
    ('LPG refills and bottle exchange', 'lpg-refills-and-bottle-exchange', 'LPG refills, swaps and caravan gas bottle supplies.', 130, 1, NOW(), NOW()),
    ('Potable water refill', 'potable-water-refill', 'Traveller-accessible drinking water and tank refill points.', 140, 1, NOW(), NOW()),
    ('Dump points', 'dump-points', 'Public and commercial caravan waste dump points.', 150, 1, NOW(), NOW()),
    ('Rest areas and RV-friendly parking', 'rest-areas-and-rv-friendly-parking', 'Rest stops and parking suitable for longer caravan and RV combinations.', 160, 1, NOW(), NOW()),
    ('Caravan parks and campgrounds', 'caravan-parks-and-campgrounds', 'Caravan parks, campgrounds and powered or unpowered sites.', 170, 1, NOW(), NOW()),
    ('Free and low-cost camps', 'free-and-low-cost-camps', 'Free and low-cost overnight options where camping is permitted.', 180, 1, NOW(), NOW()),
    ('Groceries and travel supplies', 'groceries-and-travel-supplies', 'Useful food, hardware and traveller supply stops.', 190, 1, NOW(), NOW()),
    ('Emergency accommodation', 'emergency-accommodation', 'Nearby accommodation when a caravan or RV cannot be used.', 200, 1, NOW(), NOW()),
    ('Pet-friendly travel and veterinary', 'pet-friendly-travel-and-veterinary', 'Pet-friendly traveller services and veterinary help on the road.', 210, 1, NOW(), NOW()),
    ('Towing and vehicle recovery', 'towing-and-vehicle-recovery', 'Recovery for tow vehicles, caravans, trailers and motorhomes.', 220, 1, NOW(), NOW()),
    ('4WD and remote-area recovery', '4wd-and-remote-area-recovery', 'Specialist recovery for remote roads, tracks and difficult access.', 230, 1, NOW(), NOW()),
    ('Mobile mechanics', 'mobile-mechanics', 'Mechanical diagnosis and repairs at a campsite, roadside or property.', 240, 1, NOW(), NOW()),
    ('Diesel mechanics', 'diesel-mechanics', 'Diesel servicing and repairs for tow vehicles and motorhomes.', 250, 1, NOW(), NOW()),
    ('Auto electrical and batteries', 'auto-electrical-and-batteries', 'Starting, charging, battery and tow-vehicle electrical help.', 260, 1, NOW(), NOW()),
    ('Locksmith and security', 'locksmith-and-security', 'Vehicle, caravan and RV lock, key and security assistance.', 270, 1, NOW(), NOW()),
    ('Windscreen and auto glass', 'windscreen-and-auto-glass', 'Windscreen, window and automotive glass repair or replacement.', 280, 1, NOW(), NOW()),
    ('Caravan and RV parts', 'caravan-and-rv-parts', 'Parts and consumables for caravans, campers and motorhomes.', 290, 1, NOW(), NOW()),
    ('Vehicle parts and accessories', 'vehicle-parts-and-accessories', 'Automotive parts, consumables and accessories for tow vehicles.', 295, 1, NOW(), NOW()),
    ('Towing equipment and accessories', 'towing-equipment-and-accessories', 'Hitches, brake controllers, mirrors, wiring and towing accessories.', 300, 1, NOW(), NOW()),
    ('Weighbridges and mobile weighing', 'weighbridges-and-mobile-weighing', 'Vehicle and caravan weighing for safer, compliant travel.', 310, 1, NOW(), NOW()),
    ('Vehicle and caravan washing', 'vehicle-and-caravan-washing', 'Wash bays and cleaning services suitable for caravans and RVs.', 320, 1, NOW(), NOW()),
    ('Caravan storage', 'caravan-storage', 'Short- and long-term secure caravan, trailer and motorhome storage.', 330, 1, NOW(), NOW()),
    ('Mobile welding and fabrication', 'mobile-welding-and-fabrication', 'On-site metal repairs and fabrication for touring equipment.', 340, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    short_description = VALUES(short_description),
    is_active = 1,
    updated_at = NOW();
