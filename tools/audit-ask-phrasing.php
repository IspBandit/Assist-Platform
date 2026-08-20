<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;

$engine = new IntentRuleEngine();
$queries = [
    'where can I get my car serviced near Emerald',
    'looking for a mechanic near Karratha',
    'need help with my caravan near Roma',
    'anywhere to stay near Coober Pedy',
    'where do I find a dump point near Batemans Bay',
    'grey water dump near Alice Springs',
    'black water disposal near Darwin',
    'servo near Mount Isa',
    'petrol station near Broome',
    'who can fix my fridge near Cairns',
    'help near Tennant Creek',
    'caravan repairs near Port Hedland',
    'I need a tyre shop near Newman',
    'where to get LPG near Kalgoorlie',
    'camping near Exmouth',
    'somewhere to camp near Longreach',
    'roadside help near Broken Hill',
    'where to empty toilet near Charleville',
    'fix my awning near Mackay',
    'where to weigh near Townsville',
    '4wd service near Alice Springs',
    'motorhome service near Darwin',
    'rv service near Cairns',
    'where to stay tonight near Emerald',
    'where to get a mechanic near Mount Isa',
    'find a caravan repairer near Karratha',
    'need somewhere to camp near Coober Pedy',
    'water fill near Alice Springs',
    'gas bottle near Broome',
    'my van needs a service near Roma',
];

foreach ($queries as $q) {
    $i = $engine->interpret($q);
    $status = $i->intentType === Intent::TYPE_UNKNOWN ? 'UNKNOWN' : 'OK     ';
    echo $status . ' | ' . $q . ' | loc=' . ($i->locationText ?? '-') . PHP_EOL;
}

// Town suffix corruption scan
$towns = ['Mount Isa', 'Port Augusta', 'Santa Teresa', 'Acton', 'Wagga Wagga', 'Casuarina', 'Fannie Bay', 'Tenant Creek'];
echo PHP_EOL . 'Town location extraction:' . PHP_EOL;
foreach ($towns as $town) {
    $i = $engine->interpret('mobile mechanic near ' . $town);
    echo $town . ' => ' . ($i->locationText ?? 'null') . PHP_EOL;
}
