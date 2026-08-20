<?php

declare(strict_types=1);

/**
 * Generate the deterministic Ask VanAssist regression corpus (1,000 questions).
 *
 * Usage:
 *   php tools/generate-ask-question-corpus.php
 *   php tools/generate-ask-question-corpus.php --check   # fail if fixture drift
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$outPath = $root . '/tests/fixtures/ask-question-corpus.json';
$checkOnly = in_array('--check', $argv, true);

/** @var list<string> */
$towns = [
    'Karratha', 'Coober Pedy', 'Emerald', 'Roma', 'Longreach', 'Mount Isa', 'Alice Springs',
    'Broome', 'Port Hedland', 'Newman', 'Kalgoorlie', 'Esperance', 'Carnarvon', 'Exmouth',
    'Darwin', 'Katherine', 'Tennant Creek', 'Charleville', 'Birdsville', 'Broken Hill',
    'Dubbo', 'Tamworth', 'Coffs Harbour', 'Mackay', 'Townsville',
];

/** @var list<array{id:string,template:string,intent_type:string,adapters:list<string>}> */
$templates = [
    ['id' => 'stay-where-to-stay', 'template' => 'where to stay in {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-where-can-i-stay', 'template' => 'where can I stay in {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-places-to-stay', 'template' => 'places to stay near {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-accommodation', 'template' => 'accommodation in {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-free-camp', 'template' => 'free camp near {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-caravan-park', 'template' => 'caravan park near {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-overnight', 'template' => 'overnight in {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-powered-site', 'template' => 'powered site near {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-cheap-camp', 'template' => 'cheap camp around {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'stay-rest-area', 'template' => 'rest area near {town}', 'intent_type' => 'find_stay', 'adapters' => ['stays']],
    ['id' => 'facility-dump', 'template' => 'dump point near {town}', 'intent_type' => 'find_traveller_facility', 'adapters' => ['providers', 'traveller_facilities']],
    ['id' => 'facility-toilet', 'template' => 'public toilets near {town}', 'intent_type' => 'find_traveller_facility', 'adapters' => ['traveller_facilities'], 'allows_clarification' => true],
    ['id' => 'facility-water', 'template' => 'drinking water near {town}', 'intent_type' => 'find_traveller_facility', 'adapters' => ['providers', 'traveller_facilities']],
    ['id' => 'facility-shower', 'template' => 'where can I shower near {town}', 'intent_type' => 'find_traveller_facility', 'adapters' => ['traveller_facilities']],
    ['id' => 'facility-lpg', 'template' => 'LPG refill near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-mobile-mechanic', 'template' => 'mobile mechanic near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-car-service', 'template' => 'where to service my car near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-caravan-repair', 'template' => 'mobile caravan repair near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-auto-electrician', 'template' => 'auto electrician near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-tyres', 'template' => 'flat tyre near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-brakes', 'template' => 'caravan brakes near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-towing', 'template' => 'tow truck near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-fuel', 'template' => 'fuel stop near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-fridge', 'template' => 'caravan fridge not cooling near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-solar', 'template' => 'solar not working near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-aircon', 'template' => 'air con not cold near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-diesel', 'template' => 'diesel mechanic near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-roadside', 'template' => 'breakdown near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-weighbridge', 'template' => 'weigh my caravan near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-general-service', 'template' => 'caravan service near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-parts', 'template' => 'caravan spare parts near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-recovery', 'template' => 'bogged near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-locksmith', 'template' => 'locked out of caravan near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-hot-water', 'template' => 'no hot water in caravan near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-awning', 'template' => 'awning repair near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-insurance', 'template' => 'insurance repair near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-vet', 'template' => 'vet near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-ev', 'template' => 'ev charging near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-welder', 'template' => 'mobile welder near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
    ['id' => 'provider-storage', 'template' => 'caravan storage near {town}', 'intent_type' => 'find_provider', 'adapters' => ['providers']],
];

$entries = [];
foreach ($templates as $template) {
    foreach ($towns as $town) {
        $entries[] = [
            'id' => $template['id'] . '-' . strtolower(str_replace(' ', '-', $town)),
            'query' => str_replace('{town}', $town, $template['template']),
            'expect' => [
                'intent_type' => $template['intent_type'],
                'location_text' => $town,
                'adapters' => $template['adapters'],
                'allows_clarification' => (bool) ($template['allows_clarification'] ?? false),
            ],
        ];
    }
}

$payload = [
    'version' => '1.0.0',
    'engine_version' => \App\Platform\AiSearch\Intent\IntentRuleEngine::VERSION,
    'generated_at' => gmdate('c'),
    'count' => count($entries),
    'entries' => $entries,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

if ($checkOnly) {
    if (!is_file($outPath)) {
        fwrite(STDERR, "Missing fixture: {$outPath}\nRun: php tools/generate-ask-question-corpus.php\n");
        exit(1);
    }
    $existing = file_get_contents($outPath);
    if ($existing !== $json) {
        fwrite(STDERR, "ask-question-corpus.json is out of date. Regenerate with php tools/generate-ask-question-corpus.php\n");
        exit(1);
    }
    echo "ask-question-corpus.json is current ({$payload['count']} entries).\n";
    exit(0);
}

if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0775, true);
}
file_put_contents($outPath, $json);
echo "Wrote {$payload['count']} Ask corpus entries to tests/fixtures/ask-question-corpus.json\n";
