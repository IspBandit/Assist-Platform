<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Forbidden\n"); }

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Services\DataSourceService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$brandId=(int)Database::scalar("SELECT id FROM brands WHERE slug='vanassist' LIMIT 1");
$queue=(new DataSourceService())->eligibleQueueSummary($brandId,[]);
$result=[
    'brand_id'=>$brandId,
    'providers_active'=>(int)Database::scalar("SELECT COUNT(DISTINCT p.id) FROM providers p INNER JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=? AND pbl.status='active' AND pbl.deleted_at IS NULL WHERE p.status='active' AND p.deleted_at IS NULL",[$brandId]),
    'providers_with_email'=>(int)Database::scalar("SELECT COUNT(DISTINCT p.id) FROM providers p INNER JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=? AND pbl.status='active' AND pbl.deleted_at IS NULL WHERE p.status='active' AND p.deleted_at IS NULL AND COALESCE(NULLIF(TRIM(p.email),''),NULLIF(TRIM(p.public_email),'')) IS NOT NULL",[$brandId]),
    'pending_total'=>$queue['pending'],
    'eligible_remaining'=>$queue['eligible'],
    'review_required'=>$queue['blocked'],
    'active_import_jobs'=>(int)Database::scalar("SELECT COUNT(*) FROM data_source_import_jobs j INNER JOIN data_source_connectors c ON c.id=j.connector_id WHERE j.brand_id=? AND c.connector_key='national_route_places' AND j.status IN ('queued','running')",[$brandId]),
    'factual_campaigns'=>(int)Database::scalar("SELECT COUNT(*) FROM notifications WHERE brand_id=? AND campaign_type='directory_accuracy'",[$brandId]),
    'marketing_campaigns'=>(int)Database::scalar("SELECT COUNT(*) FROM notifications WHERE brand_id=? AND campaign_type='provider_marketing'",[$brandId]),
    'email_queue_pending'=>(int)Database::scalar("SELECT COUNT(*) FROM email_queue WHERE brand_id=? AND status='pending'",[$brandId]),
];
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
