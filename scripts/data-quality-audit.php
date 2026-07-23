<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

/** @return array<mixed> */
function readJson(string $path): array
{
    $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($decoded) ? $decoded : [];
}

/** @param array<int,array<string,mixed>> $items */
function catalogueIssues(array $items, string $type): array
{
    $issues = ['missing_source_url' => 0, 'invalid_weight' => 0, 'payload_mismatch' => 0, 'duplicate_identity' => 0];
    $identities = [];
    foreach ($items as $item) {
        if (trim((string) ($item['source_url'] ?? '')) === '') {
            $issues['missing_source_url']++;
        }
        foreach (['atm', 'tare', 'gvm', 'gcm', 'kerb_weight', 'payload', 'towing_capacity'] as $field) {
            if (isset($item[$field]) && (!is_numeric($item[$field]) || (float) $item[$field] < 0)) {
                $issues['invalid_weight']++;
            }
        }
        if (isset($item['gvm'], $item['kerb_weight'], $item['payload'])
            && (int) $item['gvm'] - (int) $item['kerb_weight'] !== (int) $item['payload']) {
            $issues['payload_mismatch']++;
        }
        if (isset($item['atm'], $item['tare']) && (int) $item['atm'] < (int) $item['tare']) {
            $issues['invalid_weight']++;
        }
        $identity = mb_strtolower(trim(implode('|', [
            (string) ($item['brand'] ?? ''), (string) ($item['name'] ?? ''),
            (string) ($item['years'] ?? ''), (string) ($item['type'] ?? ''),
        ])));
        if (isset($identities[$identity])) {
            $issues['duplicate_identity']++;
        }
        $identities[$identity] = true;
    }
    return ['type' => $type, 'records' => count($items), 'issues' => $issues];
}

$vehicles = readJson(BASE_PATH . '/resources/towsmart/catalog/vehicles.json');
$trailers = readJson(BASE_PATH . '/resources/towsmart/catalog/trailers.json');
$report = [
    'generated_at' => gmdate(DATE_ATOM),
    'policy' => 'Imported/discovered records are not operator-verified unless verification evidence is recorded.',
    'catalogues' => [catalogueIssues(array_values($vehicles), 'vehicles'), catalogueIssues(array_values($trailers), 'trailers')],
];

try {
    $scalar = static fn (string $sql): int => (int) Database::scalar($sql);
    $report['database'] = [
        'towns' => [
            'total' => $scalar('SELECT COUNT(*) FROM towns'),
            'missing_postcode' => $scalar("SELECT COUNT(*) FROM towns WHERE primary_postcode IS NULL OR primary_postcode = ''"),
            'missing_coordinates' => $scalar('SELECT COUNT(*) FROM towns WHERE latitude IS NULL OR longitude IS NULL'),
            'duplicate_name_state' => $scalar('SELECT COUNT(*) FROM (SELECT state_id, LOWER(TRIM(name)) n FROM towns GROUP BY state_id, n HAVING COUNT(*) > 1) x'),
        ],
        'providers' => [
            'active' => $scalar("SELECT COUNT(*) FROM providers WHERE status = 'active' AND deleted_at IS NULL"),
            'operator_verified' => $scalar("SELECT COUNT(*) FROM providers WHERE is_verified = 1 AND is_unclaimed = 0 AND deleted_at IS NULL"),
            'unclaimed_missing_source' => $scalar("SELECT COUNT(*) FROM providers WHERE is_unclaimed = 1 AND (source_url IS NULL OR source_url = '') AND deleted_at IS NULL"),
            'missing_brand_listing' => $scalar("SELECT COUNT(*) FROM providers p WHERE p.status = 'active' AND p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM provider_brand_listings pbl WHERE pbl.provider_id = p.id)"),
            'duplicate_name_town_candidates' => $scalar('SELECT COUNT(*) FROM (SELECT base_town_id, LOWER(TRIM(business_name)) n FROM providers WHERE deleted_at IS NULL GROUP BY base_town_id, n HAVING COUNT(*) > 1) x'),
        ],
        'parks' => [
            'active' => $scalar("SELECT COUNT(*) FROM caravan_parks WHERE status = 'active' AND deleted_at IS NULL"),
            'operator_verified' => $scalar("SELECT COUNT(*) FROM caravan_parks WHERE verification_type = 'operator' AND verified_at IS NOT NULL AND deleted_at IS NULL"),
            'missing_source' => $scalar("SELECT COUNT(*) FROM caravan_parks WHERE (source_url IS NULL OR source_url = '') AND deleted_at IS NULL"),
            'duplicate_name_state_candidates' => $scalar('SELECT COUNT(*) FROM (SELECT state_id, LOWER(TRIM(name)) n FROM caravan_parks WHERE deleted_at IS NULL GROUP BY state_id, n HAVING COUNT(*) > 1) x'),
        ],
        'integrity' => [
            'provider_brand_duplicates' => $scalar('SELECT COUNT(*) FROM (SELECT provider_id, brand_id FROM provider_brand_listings GROUP BY provider_id, brand_id HAVING COUNT(*) > 1) x'),
            'orphan_provider_services' => $scalar('SELECT COUNT(*) FROM provider_services ps LEFT JOIN providers p ON p.id=ps.provider_id LEFT JOIN service_categories sc ON sc.id=ps.category_id WHERE p.id IS NULL OR sc.id IS NULL'),
        ],
    ];
} catch (Throwable $error) {
    $report['database_unavailable'] = $error->getMessage();
}

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo ($json === false ? '{"error":"report encoding failed"}' : $json) . PHP_EOL;
