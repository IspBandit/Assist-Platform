<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', getcwd());require BASE_PATH.'/bootstrap/autoload.php';App\Helpers\Env::load(BASE_PATH.'/.env');App\Core\Config::load(BASE_PATH.'/config');$db=App\Core\Database::connection();$db->exec('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();$out=[];
$out['analytics_30d']=$db->query("SELECT brand_id,event_name,COUNT(*) events FROM analytics_events WHERE is_excluded=0 AND created_at>=NOW()-INTERVAL 30 DAY GROUP BY brand_id,event_name")->fetchAll(PDO::FETCH_ASSOC);
$out['brand_listings']=$db->query('SELECT brand_id,COUNT(*) listings FROM provider_brand_listings GROUP BY brand_id')->fetchAll(PDO::FETCH_ASSOC);
$out['mfa_enrolled_users']=$db->query('SELECT COUNT(DISTINCT user_id) FROM user_mfa_methods WHERE enabled_at IS NOT NULL')->fetchColumn();
$out['counts']=[];foreach(['caravan_parks','towing_combinations','provider_claim_tokens','caravan_park_claims'] as $t){$out['counts'][$t]=$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();}
$db->rollBack();echo json_encode($out,JSON_PRETTY_PRINT);
