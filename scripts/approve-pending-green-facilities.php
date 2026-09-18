<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';
use App\Core\Config;
use App\Helpers\Env;
use App\Services\GovernmentDatasetService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$service = new GovernmentDatasetService();
$keys = $argv;
array_shift($keys);
if ($keys === []) {
    $keys = [
        'nsw_boat_ramps',
        'vic_recreation_sites',
        'wa_heavy_vehicle_rest_areas',
        'wa_major_rest_areas',
        'wa_minor_rest_areas',
        'sa_rest_areas_state_maintained',
        'tas_roadside_stops',
        'tas_boat_ramps',
        'act_public_toilet_assets',
        'qld_operational_boat_facilities',
        'au_national_public_toilet_map',
    ];
}
foreach ($keys as $key) {
    $dataset = $service->findDatasetByKey($key);
    if ($dataset === null) {
        fwrite(STDERR, "{$key}: missing catalogue\n");
        continue;
    }
    $n = $service->approvePendingForDataset((int) $dataset['id'], null, 'Archived GREEN source import');
    echo "{$key}: approved_pending={$n}\n";
}
