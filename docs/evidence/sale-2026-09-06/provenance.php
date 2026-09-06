<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH',getcwd());require BASE_PATH.'/bootstrap/autoload.php';App\Helpers\Env::load(BASE_PATH.'/.env');App\Core\Config::load(BASE_PATH.'/config');$db=App\Core\Database::connection();$db->exec('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();$out=[];
$out['brands']=$db->query('SELECT id,brand_key,status FROM brands')->fetchAll(PDO::FETCH_ASSOC);
$out['provider_sources']=$db->query('SELECT source_type,source_licence,COUNT(*) records,SUM(source_url IS NULL OR source_url="") missing_source_url FROM providers WHERE deleted_at IS NULL GROUP BY source_type,source_licence')->fetchAll(PDO::FETCH_ASSOC);
$out['stay_sources']=$db->query('SELECT source_type,COUNT(*) records,SUM(source_url IS NULL OR source_url="") missing_source_url FROM caravan_parks WHERE deleted_at IS NULL GROUP BY source_type')->fetchAll(PDO::FETCH_ASSOC);
$out['active_listings']=$db->query("SELECT b.brand_key,COUNT(*) listings FROM provider_brand_listings p JOIN brands b ON b.id=p.brand_id WHERE b.brand_key IN ('vanassist','towsmart','trailerwise') AND p.deleted_at IS NULL AND p.search_visible=1 AND p.status='active' GROUP BY b.brand_key")->fetchAll(PDO::FETCH_ASSOC);
$out['email_status']=$db->query('SELECT status,COUNT(*) records FROM email_queue GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
$db->rollBack();echo json_encode($out,JSON_PRETTY_PRINT);
