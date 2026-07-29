<?php

declare(strict_types=1);

/**
 * Validate Queensland offline coverage artefacts (no DB writes).
 *
 *   php scripts/validate-qld-coverage.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
$root = BASE_PATH . '/database/seeds/qld-coverage';
$errors = [];
$warnings = [];

function loadJson(string $path): mixed
{
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    return json_decode($raw, true);
}

$required = [
    'coverage-summary.json',
    'import-summary.json',
    'checkpoint.json',
    'providers-candidates.json',
    'providers-publishable.json',
    'providers-review-queue.json',
    'source-licence-records.json',
    'zero-coverage.jsonl',
    'weak-coverage.jsonl',
];

foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = "Missing {$file}";
    }
}

$cats = loadJson(BASE_PATH . '/database/seeds/localtorque/categories.json');
$catIds = [];
if (is_array($cats) && isset($cats['groups'])) {
    foreach ($cats['groups'] as $g) {
        foreach ($g['categories'] ?? [] as $c) {
            $catIds[$c['id']] = $c;
        }
    }
}

$publishable = loadJson($root . '/providers-publishable.json');
if (!is_array($publishable)) {
    $errors[] = 'providers-publishable.json invalid';
} else {
    $ids = [];
    foreach ($publishable as $i => $p) {
        if (!is_array($p)) {
            $errors[] = "publishable[{$i}] not object";
            continue;
        }
        $id = (string) ($p['id'] ?? '');
        if ($id === '') {
            $errors[] = "publishable[{$i}] missing id";
        } elseif (isset($ids[$id])) {
            $errors[] = "duplicate publishable id {$id}";
        } else {
            $ids[$id] = true;
        }
        if (($p['state'] ?? '') !== 'QLD') {
            $errors[] = "publishable {$id} state not QLD";
        }
        foreach ($p['category_slugs'] ?? [] as $slug) {
            if (!isset($catIds[$slug])) {
                $errors[] = "publishable {$id} unknown category {$slug}";
            }
        }
        $lat = $p['latitude'] ?? null;
        $lng = $p['longitude'] ?? null;
        if ($lat !== null && ($lat < -29.5 || $lat > -9.0)) {
            $warnings[] = "publishable {$id} latitude outside QLD-ish bounds";
        }
        if ($lng !== null && ($lng < 138.0 || $lng > 154.0)) {
            $warnings[] = "publishable {$id} longitude outside QLD-ish bounds";
        }
        $phone = (string) ($p['phone'] ?? '');
        if ($phone !== '' && !preg_match('/^[0-9+\s()-]{8,20}$/', $phone)) {
            $warnings[] = "publishable {$id} unusual phone";
        }
        $email = (string) ($p['public_email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "publishable {$id} invalid email";
        }
        if (!empty($p['publishable']) && empty($p['category_slugs'])) {
            $errors[] = "publishable {$id} has no categories";
        }
        // Brand routing must come from taxonomy
        foreach ($p['brand_visibility'] ?? [] as $brand) {
            $ok = false;
            foreach ($p['category_slugs'] ?? [] as $slug) {
                if (in_array($brand, $catIds[$slug]['brands'] ?? [], true)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok && $brand !== 'localtorque') {
                $warnings[] = "publishable {$id} brand {$brand} not justified by categories";
            }
        }
    }
}

$summary = loadJson($root . '/coverage-summary.json');
if (!is_array($summary)) {
    $errors[] = 'coverage-summary.json invalid';
} else {
    foreach (['towns_suburbs_processed', 'service_categories_processed', 'zero_coverage_cells'] as $k) {
        if (!isset($summary[$k])) {
            $errors[] = "summary missing {$k}";
        }
    }
}

$batches = glob($root . '/by-batch/*.json') ?: [];
if (count($batches) < 1) {
    $warnings[] = 'no by-batch summaries yet';
}

echo "QLD coverage validation\n";
echo 'Errors: ' . count($errors) . "\n";
echo 'Warnings: ' . count($warnings) . "\n";
foreach ($errors as $e) {
    echo "ERROR: {$e}\n";
}
foreach (array_slice($warnings, 0, 30) as $w) {
    echo "WARN: {$w}\n";
}
if (count($warnings) > 30) {
    echo 'WARN: … ' . (count($warnings) - 30) . " more\n";
}

exit(count($errors) > 0 ? 1 : 0);
