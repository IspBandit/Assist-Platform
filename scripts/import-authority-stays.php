<?php
// Import council/road-authority supplied stays. A .gov.au source URL is
// mandatory before a record can receive the "authority confirmed" badge.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASE_PATH', dirname(__DIR__)); require BASE_PATH.'/bootstrap/autoload.php';
use App\Core\Config; use App\Core\Database; use App\Helpers\Env; use App\Models\CaravanPark;
Env::load(BASE_PATH.'/.env'); Config::load(BASE_PATH.'/config');
$arguments=isset($_SERVER['argv'])&&is_array($_SERVER['argv'])?array_values(array_filter($_SERVER['argv'],'is_string')):[];
$path=$arguments[1]??'';$apply=in_array('--apply',$arguments,true); if(!is_file($path)){fwrite(STDERR,"Usage: php scripts/import-authority-stays.php authority-stays.csv [--apply]\nDefault is a transactionally rolled-back dry run.\n");exit(1);}
$pdo=Database::connection();$pdo->beginTransaction();
$fh=fopen($path,'rb'); $headers=fgetcsv($fh); if(!is_array($headers)){exit(1);} $headers=array_map('trim',$headers);
$created=0;$updated=0;$matched=0;$rejected=0;
while(($row=fgetcsv($fh))!==false){$r=array_combine($headers,array_pad($row,count($headers),''));if(!is_array($r))continue;
    $source=trim((string)($r['source_url']??''));$host=strtolower((string)parse_url($source,PHP_URL_HOST));
    if($source===''||!str_ends_with($host,'.gov.au')){$rejected++;continue;}
    $state=Database::selectOne('SELECT id FROM states WHERE abbreviation=?',[strtoupper(trim((string)($r['state']??'')))]);
    $town=$state?Database::selectOne('SELECT id,region_id FROM towns WHERE state_id=? AND LOWER(name)=LOWER(?) ORDER BY is_active DESC,id LIMIT 1',[(int)$state['id'],trim((string)($r['town']??''))]):null;
    if(!$state||empty($r['name'])||empty($r['external_id'])){$rejected++;continue;}
    $existing=Database::selectOne('SELECT id FROM caravan_parks WHERE source_type=? AND external_id=?',['authority',(string)$r['external_id']]);
    if(!$existing&&is_numeric($r['latitude']??null)&&is_numeric($r['longitude']??null)){
        $existing=Database::selectOne('SELECT id FROM caravan_parks WHERE state_id=? AND LOWER(TRIM(name))=LOWER(TRIM(?)) AND latitude IS NOT NULL AND longitude IS NOT NULL AND ABS(latitude-?)<=0.001 AND ABS(longitude-?)<=0.001 AND deleted_at IS NULL ORDER BY id LIMIT 1',[(int)$state['id'],trim((string)$r['name']),(float)$r['latitude'],(float)$r['longitude']]);
        if($existing){$matched++;}
    }
    $data=[trim((string)$r['name']),$town['id']??null,$town['region_id']??null,(int)$state['id'],is_numeric($r['latitude']??null)?(float)$r['latitude']:null,is_numeric($r['longitude']??null)?(float)$r['longitude']:null,trim((string)($r['address']??''))?:null,trim((string)($r['website']??''))?:null,trim((string)($r['stay_type']??''))?:'free_camp',trim((string)($r['price_type']??''))?:'free',$source];
    if($existing){Database::query("UPDATE caravan_parks SET name=?,town_id=COALESCE(?,town_id),region_id=COALESCE(?,region_id),state_id=?,latitude=COALESCE(?,latitude),longitude=COALESCE(?,longitude),address=COALESCE(?,address),website=COALESCE(?,website),stay_type=?,price_type=?,source_url=?,verification_type=IF(verification_type='operator','operator','authority'),verified_at=COALESCE(verified_at,NOW()),source_checked_at=NOW(),public_page_enabled=1,status='active',updated_at=NOW() WHERE id=?",[...$data,(int)$existing['id']]);$updated++;}
    else{Database::query("INSERT INTO caravan_parks (name,slug,town_id,region_id,state_id,latitude,longitude,address,website,stay_type,price_type,source_url,verification_type,verified_at,source_checked_at,source_type,external_id,public_page_enabled,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'authority',NOW(),NOW(),'authority',?,1,'active',NOW(),NOW())",[$data[0],CaravanPark::uniqueSlug($data[0].'-'.($r['town']??'')),...array_slice($data,1),(string)$r['external_id']]);$created++;}
}
fclose($fh);if($apply){$pdo->commit();}else{$pdo->rollBack();}echo json_encode(['mode'=>$apply?'applied':'dry-run','created'=>$created,'updated'=>$updated,'matched_existing_by_name_and_location'=>$matched,'rejected'=>$rejected],JSON_PRETTY_PRINT)."\n";
