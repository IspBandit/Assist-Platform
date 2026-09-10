-- Restore TowSmart and TrailerWise curated public categories after LocalTorque
-- taxonomy imports overwrote shared keys or added import-only rows at sort_order 100.

UPDATE brand_provider_categories
SET name = 'Weighing services',
    description = 'Public weighbridges and mobile vehicle/trailer weighing.',
    sort_order = 10,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'public-weighing';

UPDATE brand_provider_categories
SET name = 'Towing training',
    description = 'Practical towing instruction and safety education.',
    sort_order = 20,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'towing-training';

UPDATE brand_provider_categories
SET name = 'Towbars & hitches',
    description = 'Towbar, hitch, coupling and weight-distribution specialists.',
    sort_order = 30,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'towbars-hitches';

UPDATE brand_provider_categories
SET name = 'Brakes & controllers',
    description = 'Trailer brakes, brake controllers and breakaway systems.',
    sort_order = 40,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'brakes-controllers';

UPDATE brand_provider_categories
SET name = 'Suspension & payload',
    description = 'Vehicle suspension, load and payload specialists.',
    sort_order = 50,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'suspension-payload';

UPDATE brand_provider_categories
SET name = 'Towing electrical',
    description = 'Trailer wiring, plugs, cameras, lighting and auto electrical.',
    sort_order = 60,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'towing-electrical';

UPDATE brand_provider_categories
SET name = 'Tyres & wheels',
    description = 'Tyre, wheel and alignment businesses relevant to towing.',
    sort_order = 70,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'tyres-wheels';

UPDATE brand_provider_categories
SET name = 'Towing inspections',
    description = 'Combination checks, trailer inspections and compliance support.',
    sort_order = 80,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 2 AND category_key = 'towing-inspections';

UPDATE brand_provider_categories
SET name = 'Trailer repairs & servicing',
    description = 'General, mobile and workshop trailer repair services.',
    sort_order = 10,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'trailer-repairs';

UPDATE brand_provider_categories
SET name = 'Roadworthy & inspections',
    description = 'Approved inspections, safety certificates and compliance services.',
    sort_order = 20,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'roadworthy-inspections';

UPDATE brand_provider_categories
SET name = 'Tyres, wheels & bearings',
    description = 'Tyre shops, wheels, hubs, balancing and bearing services.',
    sort_order = 30,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'tyres-wheels-bearings';

UPDATE brand_provider_categories
SET name = 'Brakes, axles & suspension',
    description = 'Electric brakes, controllers, axles, springs and suspension.',
    sort_order = 40,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'brakes-axles-suspension';

UPDATE brand_provider_categories
SET name = 'Auto electrical',
    description = 'Trailer lighting, plugs, wiring, batteries and diagnostics.',
    sort_order = 50,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'auto-electrical';

UPDATE brand_provider_categories
SET name = 'Fabrication & engineering',
    description = 'Welding, chassis work, modifications and engineering.',
    sort_order = 60,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'fabrication-engineering';

UPDATE brand_provider_categories
SET name = 'Parts & accessories',
    description = 'Trailer components, replacement parts and upgrades.',
    sort_order = 70,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'parts-accessories';

UPDATE brand_provider_categories
SET name = 'Manufacturers & dealers',
    description = 'Trailer builders, dealers and authorised product support.',
    sort_order = 80,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'manufacturers-dealers';

UPDATE brand_provider_categories
SET name = 'Mobile trailer services',
    description = 'Providers able to attend trailers on site or roadside.',
    sort_order = 90,
    is_active = 1,
    updated_at = NOW()
WHERE brand_id = 3 AND category_key = 'mobile-trailer-services';
