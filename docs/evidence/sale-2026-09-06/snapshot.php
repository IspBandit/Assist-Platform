<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', getcwd());
require BASE_PATH . '/bootstrap/autoload.php';
App\Helpers\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(BASE_PATH . '/config');
$db=App\Core\Database::connection();
$db->exec('SET SESSION TRANSACTION READ ONLY');
$db->beginTransaction();
$out=['captured_at_utc'=>gmdate('c'),'schema'=>$db->query('SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC),'counts'=>[]];
$tables=array_column($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM),0);
foreach(['users','providers','provider_brand_listings','provider_claims','stays','towns','service_requests','page_views','invoices','payments','refunds','tow_vehicles','tow_combinations','caravans','assist_searches'] as $t){if(in_array($t,$tables,true))$out['counts'][$t]=(int)$db->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();}
$out['tasks']=$db->query('SELECT task_key,last_status,last_run_at,last_duration_ms FROM scheduled_tasks ORDER BY task_key')->fetchAll(PDO::FETCH_ASSOC);
$out['brands']=$db->query('SELECT id,name FROM brands')->fetchAll(PDO::FETCH_ASSOC);
$out['events_30_days']=$db->query('SELECT event_type,COUNT(*) AS events FROM page_views WHERE created_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY GROUP BY event_type')->fetchAll(PDO::FETCH_ASSOC);
$db->rollBack();
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
