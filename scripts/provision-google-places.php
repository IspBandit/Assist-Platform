#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Provision GOOGLE_PLACES_API_KEY into the encrypted connector vault and
 * optionally enable ADR 0042 Places rescue (VAN-011).
 *
 * Usage:
 *   php scripts/provision-google-places.php
 *   php scripts/provision-google-places.php --enable-rescue
 *   php scripts/provision-google-places.php --budget=50 --limit=100
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
use App\Platform\AiSearch\Support\PlacesRescueFeature;
use App\Services\DataSources\GooglePlacesCredentialProvisioner;
use App\Services\FeatureFlag;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$enableRescue = in_array('--enable-rescue', $args, true);
$budget = 50.0;
$limit = 100;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--budget=')) {
        $budget = max(0, (float) substr($arg, 9));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

try {
    (new GooglePlacesCredentialProvisioner())->provisionFromEnv(true, $budget, $limit);
    fwrite(STDOUT, "Google Places connector activated from GOOGLE_PLACES_API_KEY (budget A\${$budget}, limit {$limit}/day).\n");
} catch (Throwable $e) {
    $envKey = trim((string) Env::get('GOOGLE_PLACES_API_KEY', ''));
    if ($envKey === '') {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    Database::query(
        "UPDATE data_source_connectors SET status='active', daily_request_limit=?, daily_budget_aud=?, updated_at=NOW() "
        . "WHERE connector_key='google_places'",
        [$limit, $budget]
    );
    fwrite(STDOUT, 'Vault provision skipped (' . $e->getMessage()
        . "); connector activated for GOOGLE_PLACES_API_KEY environment fallback.\n");
}

if ($enableRescue) {
    FeatureFlag::set(PlacesRescueFeature::FLAG, true);
    fwrite(STDOUT, 'Feature flag ' . PlacesRescueFeature::FLAG . " enabled.\n");
}

exit(0);
