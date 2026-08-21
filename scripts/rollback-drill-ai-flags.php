<?php

declare(strict_types=1);

/**
 * Rollback drill for VanAssist AI flags (readiness §8).
 * Forces Ask / facilities / datasets / paid AI off and records evidence.
 * Does not delete facility data.
 *
 *   php scripts/rollback-drill-ai-flags.php
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
use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Platform\AiSearch\Support\DatasetSearchFeature;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$started = microtime(true);
$before = [
    'assist_ai_search' => FeatureFlag::enabled(AiSearchFeature::FLAG, false),
    'assist_ai_traveller_facilities' => FeatureFlag::enabled(TravellerFacilitiesFeature::FLAG, false),
    'assist_ai_datasets' => FeatureFlag::enabled(DatasetSearchFeature::FLAG, false),
];
$aiRow = Database::selectOne('SELECT ai_enabled, openai_enabled FROM ai_settings WHERE id = 1 LIMIT 1');
$before['ai_enabled'] = $aiRow !== null && (int) ($aiRow['ai_enabled'] ?? 0) === 1;
$before['openai_enabled'] = $aiRow !== null && (int) ($aiRow['openai_enabled'] ?? 0) === 1;

FeatureFlag::set(AiSearchFeature::FLAG, false);
FeatureFlag::set(TravellerFacilitiesFeature::FLAG, false);
FeatureFlag::set(DatasetSearchFeature::FLAG, false);
Database::query('UPDATE ai_settings SET ai_enabled = 0, openai_enabled = 0 WHERE id = 1');

$after = [
    'assist_ai_search' => FeatureFlag::enabled(AiSearchFeature::FLAG, false),
    'assist_ai_traveller_facilities' => FeatureFlag::enabled(TravellerFacilitiesFeature::FLAG, false),
    'assist_ai_datasets' => FeatureFlag::enabled(DatasetSearchFeature::FLAG, false),
];
$aiAfter = Database::selectOne('SELECT ai_enabled, openai_enabled FROM ai_settings WHERE id = 1 LIMIT 1');
$after['ai_enabled'] = $aiAfter !== null && (int) ($aiAfter['ai_enabled'] ?? 0) === 1;
$after['openai_enabled'] = $aiAfter !== null && (int) ($aiAfter['openai_enabled'] ?? 0) === 1;

$elapsedMs = (int) round((microtime(true) - $started) * 1000);
$safe = !$after['assist_ai_search']
    && !$after['assist_ai_traveller_facilities']
    && !$after['assist_ai_datasets']
    && !$after['ai_enabled']
    && !$after['openai_enabled'];

$report = [
    'drill' => 'AI_FLAG_ROLLBACK',
    'started_at' => gmdate('c'),
    'elapsed_ms' => $elapsedMs,
    'before' => $before,
    'after' => $after,
    'result' => $safe ? 'PASS' : 'FAIL',
    'notes' => [
        'Confirm GET /ask → 404 on VanAssist when Ask flag off (manual/browser).',
        'Confirm GET /find still healthy (manual/browser).',
        'Facility rows were not deleted; soft-unpublish separately if needed.',
    ],
];

$out = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo $out;
$evidenceDir = BASE_PATH . '/docs/evidence/vanassist-readiness-2026-08-02';
if (is_dir($evidenceDir) || @mkdir($evidenceDir, 0775, true)) {
    file_put_contents($evidenceDir . '/ROLLBACK_DRILL_AI_FLAGS.json', $out);
}

exit($safe ? 0 : 1);
