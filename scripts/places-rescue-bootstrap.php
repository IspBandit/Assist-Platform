#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * National hub Places bootstrap for high-demand VanAssist categories (ADR 0042).
 *
 * Shares ZeroResultProviderRescueService / PlacesUnclaimedPublisher with live
 * traveller rescue. Requires provider_places_rescue flag ON and an active
 * Google Places connector with credentials and budget.
 *
 * Usage:
 *   php scripts/places-rescue-bootstrap.php --dry-run
 *   php scripts/places-rescue-bootstrap.php --apply --limit-towns=20
 *   php scripts/places-rescue-bootstrap.php --apply --town="Charters Towers" --category=refrigeration
 *
 * Production apply is an ops decision (Places spend + Quality Gate). This script
 * does not enable the feature flag or deploy.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Models\ServiceCategory;
use App\Models\Town;
use App\Platform\AiSearch\Support\PlacesRescueFeature;
use App\Services\DataSources\GooglePlacesCredentialProvisioner;
use App\Services\Search\ZeroResultProviderRescueService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$dryRun = !in_array('--apply', $args, true);
$limitTowns = 40;
$onlyTown = null;
$onlyCategory = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--limit-towns=')) {
        $limitTowns = max(1, min(200, (int) substr($arg, 14)));
    }
    if (str_starts_with($arg, '--town=')) {
        $onlyTown = trim(substr($arg, 7), " \t\"'");
    }
    if (str_starts_with($arg, '--category=')) {
        $onlyCategory = trim(substr($arg, 11), " \t\"'");
    }
}

$hubs = [
    ['Charters Towers', 'QLD'],
    ['Townsville', 'QLD'],
    ['Longreach', 'QLD'],
    ['Mount Isa', 'QLD'],
    ['Cairns', 'QLD'],
    ['Mackay', 'QLD'],
    ['Rockhampton', 'QLD'],
    ['Emerald', 'QLD'],
    ['Gladstone', 'QLD'],
    ['Bundaberg', 'QLD'],
    ['Brisbane', 'QLD'],
    ['Toowoomba', 'QLD'],
    ['Sydney', 'NSW'],
    ['Dubbo', 'NSW'],
    ['Broken Hill', 'NSW'],
    ['Melbourne', 'VIC'],
    ['Mildura', 'VIC'],
    ['Adelaide', 'SA'],
    ['Perth', 'WA'],
    ['Broome', 'WA'],
    ['Darwin', 'NT'],
    ['Alice Springs', 'NT'],
    ['Hobart', 'TAS'],
    ['Canberra', 'ACT'],
];

$categories = [
    'refrigeration',
    'air-conditioning',
    'gas-appliance-servicing',
    'general-caravan-repairs',
    'mobile-mechanics',
    'auto-electrical-and-batteries',
    'brakes-and-bearings',
    'diesel-mechanics',
];
if ($onlyCategory !== null && $onlyCategory !== '') {
    $categories = [$onlyCategory];
}

$brandId = (int) Database::scalar("SELECT id FROM brands WHERE brand_key = 'vanassist' AND status = 'active' LIMIT 1");
if ($brandId < 1) {
    $brandId = (int) Database::scalar("SELECT id FROM brands WHERE brand_key = 'vanassist' LIMIT 1");
}
if ($brandId < 1) {
    fwrite(STDERR, "VanAssist brand row not found.\n");
    exit(1);
}

if (!$dryRun && !PlacesRescueFeature::enabled()) {
    fwrite(STDERR, "provider_places_rescue is OFF. Enable with:\n");
    fwrite(STDERR, "  php scripts/provision-google-places.php --enable-rescue\n");
    exit(1);
}

if (!$dryRun) {
    $credLen = (int) Database::scalar(
        "SELECT LENGTH(cr.encrypted_value) FROM data_source_credentials cr "
        . "JOIN data_source_connectors c ON c.id = cr.connector_id "
        . "WHERE c.connector_key = 'google_places' AND cr.credential_key = 'api_key'"
    );
    $connectorStatus = (string) Database::scalar(
        "SELECT status FROM data_source_connectors WHERE connector_key = 'google_places' LIMIT 1"
    );
    $envKey = trim((string) Env::get('GOOGLE_PLACES_API_KEY', ''));
    if ($connectorStatus !== 'active' || $credLen < 1) {
        if ($envKey === '') {
            fwrite(STDERR, "Places connector is not ready and GOOGLE_PLACES_API_KEY is not set.\n");
            exit(1);
        }
        try {
            (new GooglePlacesCredentialProvisioner())->provisionFromEnv(true);
            fwrite(STDOUT, "Provisioned Google Places connector from GOOGLE_PLACES_API_KEY.\n");
        } catch (\Throwable $e) {
            Database::query(
                "UPDATE data_source_connectors SET status = 'active', "
                . 'daily_request_limit = GREATEST(daily_request_limit, 100), '
                . 'daily_budget_aud = GREATEST(daily_budget_aud, 50), updated_at = NOW() '
                . "WHERE connector_key = 'google_places'"
            );
            fwrite(STDOUT, 'Vault provision skipped (' . $e->getMessage()
                . "); using GOOGLE_PLACES_API_KEY from environment.\n");
        }
    }
}

$rescue = new ZeroResultProviderRescueService();
$created = 0;
$merged = 0;
$externals = 0;
$processed = 0;

foreach ($hubs as [$name, $state]) {
    if ($onlyTown !== null && strcasecmp($onlyTown, $name) !== 0) {
        continue;
    }
    if ($processed >= $limitTowns) {
        break;
    }
    $matches = Town::searchActive($name . ' ' . $state, 3);
    $town = $matches[0] ?? null;
    if ($town === null) {
        fwrite(STDOUT, "[skip] town not found: {$name}, {$state}\n");
        continue;
    }
    ++$processed;
    $lat = isset($town['latitude']) && is_numeric($town['latitude']) ? (float) $town['latitude'] : null;
    $lng = isset($town['longitude']) && is_numeric($town['longitude']) ? (float) $town['longitude'] : null;

    foreach ($categories as $slug) {
        if (ServiceCategory::findActiveBySlug($slug) === null) {
            fwrite(STDOUT, "[skip] category missing: {$slug}\n");
            continue;
        }
        $label = (string) $town['name'] . ' / ' . $slug;
        if ($dryRun) {
            fwrite(STDOUT, "[dry-run] would rescue {$label}\n");
            continue;
        }
        $result = $rescue->rescue([$slug], $town, $lat, $lng, $brandId, 300);
        $created += $result['created'];
        $merged += $result['merged'];
        $externals += count($result['externals']);
        fwrite(STDOUT, sprintf(
            "[ok] %s created=%d merged=%d externals=%d\n",
            $label,
            $result['created'],
            $result['merged'],
            count($result['externals'])
        ));
        usleep(250000);
    }
}

fwrite(STDOUT, $dryRun
    ? "Dry-run complete for {$processed} hub towns.\n"
    : "Bootstrap complete. hubs={$processed} created={$created} merged={$merged} externals={$externals}\n");
exit(0);
