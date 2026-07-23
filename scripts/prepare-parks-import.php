<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$input = $argv[1] ?? '';
$output = $argv[2] ?? '';
if (!is_file($input) || $output === '') {
    fwrite(STDERR, "Usage: php scripts/prepare-parks-import.php stays_master_deduped.csv authority-stays.csv\n");
    exit(1);
}

$in = fopen($input, 'rb');
$out = fopen($output, 'wb');
if ($in === false || $out === false) {
    fwrite(STDERR, "Unable to open input or output.\n");
    exit(1);
}

$headers = fgetcsv($in);
if (!is_array($headers)) {
    fwrite(STDERR, "The source CSV has no header.\n");
    exit(1);
}
$headers = array_map(static fn (string $value): string => trim($value), $headers);
$target = ['external_id', 'name', 'state', 'town', 'latitude', 'longitude', 'address', 'website', 'stay_type', 'price_type', 'source_url'];
fputcsv($out, $target);

$accepted = 0;
$rejectedCommercial = 0;
$rejectedInvalid = 0;
$bySource = [];
while (($values = fgetcsv($in)) !== false) {
    $row = array_combine($headers, array_pad($values, count($headers), ''));
    if (!is_array($row)) {
        $rejectedInvalid++;
        continue;
    }
    $url = trim((string) ($row['url'] ?? ''));
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $official = $host !== '' && ($host === 'gov.au' || str_ends_with($host, '.gov.au'));
    if (!$official) {
        $rejectedCommercial++;
        continue;
    }
    $name = trim((string) ($row['name'] ?? ''));
    $state = strtoupper(trim((string) ($row['state'] ?? '')));
    $lat = filter_var($row['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($row['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($name === '' || !in_array($state, ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'], true)
        || $lat === false || $lng === false || $lat > -9 || $lat < -44 || $lng < 112 || $lng > 154) {
        $rejectedInvalid++;
        continue;
    }

    $source = trim((string) ($row['source'] ?? 'authority'));
    $sourceId = trim((string) ($row['source_id'] ?? ''));
    $externalId = substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($source)) . ':' . $sourceId, 0, 100);
    if ($sourceId === '') {
        $externalId = substr(hash('sha256', $source . '|' . $name . '|' . $state . '|' . $lat . '|' . $lng), 0, 64);
    }
    $category = strtolower(trim((string) ($row['category'] ?? '')));
    $stayType = match ($category) {
        'caravan-park' => 'caravan_park',
        'national-park', 'campsite' => 'campground',
        'free' => 'free_camp',
        'showground' => 'showground',
        'rest-area' => 'rest_area',
        default => 'other',
    };
    $fee = strtolower(trim((string) ($row['fee_status'] ?? '')));
    $priceType = str_contains($fee, 'free') ? 'free'
        : (str_contains($fee, 'donation') ? 'donation'
            : (str_contains($fee, 'budget') ? 'low_cost'
                : (str_contains($fee, 'fee') ? 'paid' : 'unknown')));

    fputcsv($out, [
        $externalId, $name, $state, trim((string) ($row['town'] ?? '')), $lat, $lng,
        trim((string) ($row['address'] ?? '')), trim((string) ($row['website'] ?? '')),
        $stayType, $priceType, $url,
    ]);
    $accepted++;
    $bySource[$source] = ($bySource[$source] ?? 0) + 1;
}
fclose($in);
fclose($out);

ksort($bySource);
echo json_encode([
    'accepted_authority_records' => $accepted,
    'rejected_unlicensed_or_non_authority_records' => $rejectedCommercial,
    'rejected_invalid_records' => $rejectedInvalid,
    'by_source' => $bySource,
    'output' => $output,
    'important' => 'Preparation does not prove current operator details. The database importer records the official source and check date.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
