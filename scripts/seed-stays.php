<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Models\CaravanPark;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
$file = BASE_PATH . '/database/seeds/stays_osm.json';
if (!is_file($file)) { fwrite(STDERR, "Missing database/seeds/stays_osm.json. Run node tools/osm-stays-import.js first.\n"); exit(1); }
$payload = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$states = [];
foreach (Database::select('SELECT id, abbreviation FROM states') as $state) { $states[$state['abbreviation']] = (int) $state['id']; }
$created = 0; $updated = 0; $skipped = 0;
foreach (($payload['stays'] ?? []) as $stay) {
    $stateId = $states[$stay['state'] ?? ''] ?? null;
    if ($stateId === null || empty($stay['name']) || empty($stay['external_id'])) { $skipped++; continue; }
    $town = Database::selectOne(
        'SELECT id, region_id FROM towns WHERE state_id = ? AND LOWER(name) = LOWER(?) ORDER BY is_active DESC, id LIMIT 1',
        [$stateId, (string) ($stay['town'] ?? '')]
    );
    $existing = Database::selectOne('SELECT id, slug FROM caravan_parks WHERE source_type = ? AND external_id = ?', ['openstreetmap', $stay['external_id']]);
    $bool = static fn(mixed $v): ?int => $v === true ? 1 : ($v === false ? 0 : null);
    $values = [
        (string) $stay['name'], trim((string) ($stay['address'] ?? '')) ?: null, $town['id'] ?? null,
        $town['region_id'] ?? null, $stateId, $stay['latitude'], $stay['longitude'],
        trim((string) ($stay['phone'] ?? '')) ?: null, trim((string) ($stay['email'] ?? '')) ?: null,
        trim((string) ($stay['website'] ?? '')) ?: null, trim((string) ($stay['booking_url'] ?? '')) ?: null,
        $stay['stay_type'] ?? 'other', $stay['price_type'] ?? 'unknown',
        $bool($stay['powered_sites'] ?? null), $bool($stay['unpowered_sites'] ?? null), $bool($stay['toilets'] ?? null),
        $bool($stay['showers'] ?? null), $bool($stay['potable_water'] ?? null), $bool($stay['dump_point'] ?? null),
        $bool($stay['pets_allowed'] ?? null), (string) ($stay['source_url'] ?? ''), 'community',
    ];
    if ($existing !== null) {
        Database::query(
            'UPDATE caravan_parks SET name=?, address=?, town_id=?, region_id=?, state_id=?, latitude=?, longitude=?, phone=?, email=?, website=?, booking_url=?, stay_type=?, price_type=?, powered_sites=?, unpowered_sites=?, toilets=?, showers=?, potable_water=?, dump_point=?, pets_allowed=?, source_url=?, verification_type=?, source_checked_at=NOW(), updated_at=NOW() WHERE id=?',
            [...$values, (int) $existing['id']]
        );
        $updated++;
    } else {
        Database::query(
            'INSERT INTO caravan_parks (name,slug,address,town_id,region_id,state_id,latitude,longitude,phone,email,website,booking_url,stay_type,price_type,powered_sites,unpowered_sites,toilets,showers,potable_water,dump_point,pets_allowed,source_url,verification_type,source_type,external_id,description,public_page_enabled,status,created_at,updated_at,source_checked_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())',
            [$values[0], CaravanPark::uniqueSlug($values[0] . '-' . ($stay['town'] ?? '')), ...array_slice($values, 1),
                'openstreetmap', (string) $stay['external_id'], 'Community-sourced directory listing. Confirm access, fees, facilities and restrictions before arrival.', 1, 'active']
        );
        $created++;
    }
}
echo json_encode(['created'=>$created,'updated'=>$updated,'skipped'=>$skipped,'total'=>$created+$updated], JSON_PRETTY_PRINT) . "\n";
