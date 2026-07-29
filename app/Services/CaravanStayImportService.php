<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CaravanPark;
use App\Models\Town;
use RuntimeException;
use Throwable;

final class CaravanStayImportService
{
    private const MAX_UPLOAD_BYTES = 10_000_000;
    private const STAGING_DIR = 'storage/imports/qld-stay-coverage/staged';
    /** @var array<int,string> */
    private const STAY_TYPES = ['caravan_park','campground','free_camp','national_park','showground','rest_area','council_camp','farm_stay','station_stay','other'];
    /** @var array<int,string> */
    private const PRICE_TYPES = ['free','donation','low_cost','paid','unknown'];
    /** @var array<int,string> */
    private const AUTHORITY_TYPES = ['free_camp','national_park','showground','rest_area','council_camp'];

    public function stageUpload(array $file, int $brandId, int $userId): int
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($error === UPLOAD_ERR_NO_FILE ? 'Choose the Queensland stay JSONL file first.' : 'The stay discovery upload failed.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('The uploaded stay discovery file could not be verified.');
        }
        return $this->stageFile($tmp, (string)($file['name'] ?? ''), (int)($file['size'] ?? 0), $brandId, $userId, true);
    }

    public function stageLocalFile(string $path, int $brandId, int $userId = 0): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('The stay discovery file was not found.');
        }
        return $this->stageFile($path, basename($path), (int)filesize($path), $brandId, $userId, false);
    }

    /** @return array{processed:int,inserted:int,held:int,skipped:int,done:bool,total_processed:int,job_id:int} */
    public function processJob(int $jobId, int $brandId, int $batchSize = 300): array
    {
        $this->purgeExpired();
        $this->cleanupStaging();
        $batchSize = max(1, min(500, $batchSize));
        $job = Database::selectOne('SELECT * FROM caravan_stay_import_jobs WHERE id=? AND brand_id=?', [$jobId,$brandId]);
        if ($job === null) {
            throw new RuntimeException('The stay import job was not found in this workspace.');
        }
        if (!in_array((string)$job['status'], ['queued','running'], true)) {
            return ['processed'=>0,'inserted'=>0,'held'=>0,'skipped'=>0,'done'=>true,'total_processed'=>(int)$job['processed_lines'],'job_id'=>$jobId];
        }
        $file = basename((string)($job['staged_file'] ?? ''));
        $path = BASE_PATH . '/' . self::STAGING_DIR . '/' . $file;
        if ($file === '' || !is_file($path)) {
            throw new RuntimeException('The staged stay discovery file is missing. Upload it again.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The staged stay discovery file could not be opened.');
        }
        $processedBefore = (int)$job['processed_lines'];
        try {
            for ($lineNo=0; $lineNo<$processedBefore; $lineNo++) {
                if (fgets($handle) === false) break;
            }
            $index = $this->parkIndex();
            $errors = json_decode((string)($job['errors_json'] ?? '[]'), true);
            $errors = is_array($errors) ? array_values(array_filter($errors, 'is_array')) : [];
            $processed=0; $inserted=0; $held=0; $skipped=0; $reachedEnd=false;
            while ($processed < $batchSize) {
                $line = fgets($handle);
                if ($line === false) { $reachedEnd=true; break; }
                $processed++;
                $line = trim($line);
                if ($line === '') continue;
                if (strlen($line) > 100_000) {
                    $skipped++;
                    if (count($errors) < 20) $errors[]=['line'=>$processedBefore+$processed,'error'=>'Row exceeds the 100 KB safety limit.'];
                    continue;
                }
                try {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($row)) { $skipped++; continue; }
                    $result = $this->storeCandidate($jobId, $brandId, $row, $index);
                    if ($result === 'inserted') $inserted++;
                    elseif ($result === 'held') { $inserted++; $held++; }
                    else $skipped++;
                } catch (Throwable $exception) {
                    $skipped++;
                    if (count($errors) < 20) $errors[]=['line'=>$processedBefore+$processed,'error'=>mb_substr($exception->getMessage(),0,300)];
                }
            }
        } finally {
            fclose($handle);
        }

        $total = $processedBefore + $processed;
        $done = $reachedEnd || $processed < $batchSize;
        Database::query(
            'UPDATE caravan_stay_import_jobs SET status=?,processed_lines=?,candidates_new=candidates_new+?,skipped_lines=skipped_lines+?,errors_json=?,started_at=COALESCE(started_at,NOW()),completed_at=? WHERE id=? AND brand_id=?',
            [$done?'review':'running',$total,$inserted,$skipped,json_encode($errors,JSON_THROW_ON_ERROR),$done?gmdate('Y-m-d H:i:s'):null,$jobId,$brandId]
        );
        if ($done) {
            @unlink($path);
            Database::query('UPDATE caravan_stay_import_jobs SET staged_file=NULL WHERE id=? AND brand_id=?',[$jobId,$brandId]);
        }
        return ['processed'=>$processed,'inserted'=>$inserted,'held'=>$held,'skipped'=>$skipped,'done'=>$done,'total_processed'=>$total,'job_id'=>$jobId];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int,page:int,perPage:int,summary:array<string,int>} */
    public function queue(int $brandId, array $filters): array
    {
        $status = (string)($filters['status'] ?? 'pending');
        if (!in_array($status,['pending','held','approved','merged','rejected'],true)) $status='pending';
        $where=['c.brand_id=?','c.review_status=?']; $params=[$brandId,$status];
        $type=(string)($filters['stay_type']??'');
        if (in_array($type,self::STAY_TYPES,true)) { $where[]='c.stay_type=?'; $params[]=$type; }
        $state=strtoupper(trim((string)($filters['state']??'')));
        if (preg_match('/^[A-Z]{2,3}$/',$state)===1) { $where[]='c.candidate_state=?'; $params[]=$state; }
        $duplicate=(string)($filters['duplicate']??'');
        if ($duplicate==='yes') $where[]='c.duplicate_park_id IS NOT NULL';
        if ($duplicate==='no') $where[]='c.duplicate_park_id IS NULL';
        $search=trim((string)($filters['q']??''));
        if ($search!=='') { $where[]='(c.name LIKE ? OR c.address LIKE ? OR c.phone LIKE ?)'; $like='%'.$search.'%'; array_push($params,$like,$like,$like); }
        $clause=implode(' AND ',$where);
        $perPage=50;
        $total=(int)Database::scalar('SELECT COUNT(*) FROM caravan_stay_import_candidates c WHERE '.$clause,$params);
        $pages=max(1,(int)ceil($total/$perPage));
        $page=max(1,min($pages,(int)($filters['page']??1)));
        $rows=Database::select(
            'SELECT c.*,p.name AS duplicate_name FROM caravan_stay_import_candidates c LEFT JOIN caravan_parks p ON p.id=c.duplicate_park_id WHERE '.$clause.' ORDER BY (c.duplicate_park_id IS NOT NULL) DESC,c.name LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage),
            $params
        );
        $summary=['pending'=>0,'held'=>0,'approved'=>0,'merged'=>0,'rejected'=>0];
        foreach(Database::select('SELECT review_status,COUNT(*) total FROM caravan_stay_import_candidates WHERE brand_id=? GROUP BY review_status',[$brandId]) as $row) $summary[(string)$row['review_status']]=(int)$row['total'];
        return compact('rows','total','page','perPage','summary');
    }

    /** @return array<int,array<string,mixed>> */
    public function recentJobs(int $brandId): array
    {
        return Database::select('SELECT * FROM caravan_stay_import_jobs WHERE brand_id=? ORDER BY id DESC LIMIT 10',[$brandId]);
    }

    public function review(int $candidateId, int $brandId, string $decision, ?int $parkId, int $userId, bool $retentionConfirmed, string $evidenceUrl, string $reviewNotes): int
    {
        $candidate=Database::selectOne("SELECT * FROM caravan_stay_import_candidates WHERE id=? AND brand_id=? AND review_status IN ('pending','held')",[$candidateId,$brandId]);
        if ($candidate===null) throw new RuntimeException('This stay candidate is no longer awaiting review in the current workspace.');
        $reviewNotes=mb_substr(trim($reviewNotes),0,1000);
        if ($decision==='hold' || $decision==='reject' || $decision==='restore') {
            $status=['hold'=>'held','reject'=>'rejected','restore'=>'pending'][$decision];
            Database::query('UPDATE caravan_stay_import_candidates SET review_status=?,review_notes=?,reviewed_by=?,reviewed_at=?,updated_at=NOW() WHERE id=? AND brand_id=?',[$status,$reviewNotes?:null,$decision==='restore'?null:$userId,$decision==='restore'?null:gmdate('Y-m-d H:i:s'),$candidateId,$brandId]);
            AuditLog::record('stay_import.'.$decision,'caravan_stay_import_candidate',(string)$candidateId);
            return 0;
        }
        if (!in_array($decision,['approve','merge'],true)) throw new RuntimeException('Unknown stay review decision.');
        $evidenceUrl=mb_substr(trim($evidenceUrl),0,500);
        if (!$retentionConfirmed || !$this->validEvidenceUrl($evidenceUrl)) {
            throw new RuntimeException('Record and confirm a current independent operator or authority URL before creating or merging a stay listing.');
        }
        if (in_array((string)$candidate['stay_type'],self::AUTHORITY_TYPES,true) && !$this->isAuthorityUrl($evidenceUrl)) {
            throw new RuntimeException('Free camps, national parks, showgrounds, rest areas and council camps require a current Australian government or council source URL.');
        }
        $target=$parkId ?: (int)($candidate['duplicate_park_id']??0);
        if ($decision==='merge') {
            if ($target<1 || (int)Database::scalar('SELECT COUNT(*) FROM caravan_parks WHERE id=? AND deleted_at IS NULL',[$target])<1) throw new RuntimeException('Choose an existing stay listing to merge into.');
            Database::query("UPDATE caravan_stay_import_candidates SET review_status='merged',evidence_url=?,review_notes=?,park_id=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=? AND brand_id=?",[$evidenceUrl,$reviewNotes?:null,$target,$userId,$candidateId,$brandId]);
            AuditLog::record('stay_import.merged','caravan_park',(string)$target);
            return $target;
        }

        $location=$this->candidateLocation($candidate);
        $website=mb_strlen((string)($candidate['website']??''))<=255?($candidate['website']?:null):null;
        Database::beginTransaction();
        try {
            $newId=Database::insert(
                "INSERT INTO caravan_parks (name,slug,address,town_id,region_id,state_id,phone,website,stay_type,price_type,latitude,longitude,source_type,source_url,external_id,source_checked_at,verification_type,listing_plan,public_page_enabled,status,is_demo,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'independent_review',?,?,NOW(),'unverified','free',0,'draft',0,NOW(),NOW())",
                [$candidate['name'],CaravanPark::uniqueSlug((string)$candidate['name']),$candidate['address']?:null,$location['town_id'],$location['region_id'],$location['state_id'],$candidate['phone']?:null,$website,$candidate['stay_type'],$candidate['price_type'],$candidate['latitude'],$candidate['longitude'],$evidenceUrl,mb_substr((string)$candidate['external_id'],0,100)]
            );
            Database::query("UPDATE caravan_stay_import_candidates SET review_status='approved',evidence_url=?,review_notes=?,park_id=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=? AND brand_id=?",[$evidenceUrl,$reviewNotes?:null,$newId,$userId,$candidateId,$brandId]);
            Database::commit();
            AuditLog::record('stay_import.approved_draft','caravan_park',(string)$newId);
            return $newId;
        } catch(Throwable $exception) { Database::rollBack(); throw $exception; }
    }

    public function purgeExpired(): int
    {
        return Database::affecting('DELETE FROM caravan_stay_import_candidates WHERE expires_at<NOW()');
    }

    private function stageFile(string $source,string $name,int $size,int $brandId,int $userId,bool $uploaded): int
    {
        $this->purgeExpired(); $this->cleanupStaging();
        if ($size<1 || $size>self::MAX_UPLOAD_BYTES || !str_ends_with(strtolower($name),'.jsonl')) throw new RuntimeException('Upload a non-empty JSONL stay discovery file no larger than 10 MB.');
        $dir=BASE_PATH.'/'.self::STAGING_DIR;
        if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) throw new RuntimeException('The private stay staging directory could not be created.');
        $staged=bin2hex(random_bytes(16)).'.jsonl'; $destination=$dir.'/'.$staged;
        $ok=$uploaded?move_uploaded_file($source,$destination):copy($source,$destination);
        if (!$ok) throw new RuntimeException('The stay discovery file could not be staged.');
        try {
            return Database::insert("INSERT INTO caravan_stay_import_jobs (brand_id,original_name,staged_file,status,requested_by,expires_at,created_at) VALUES (?,?,?,'queued',?,DATE_ADD(NOW(),INTERVAL 2 DAY),NOW())",[$brandId,mb_substr($name,0,190),$staged,$userId>0?$userId:null]);
        } catch(Throwable $exception) { @unlink($destination); throw $exception; }
    }

    /** @param array<string,array<string,array<int,array<string,mixed>>>> $index */
    private function storeCandidate(int $jobId,int $brandId,array $row,array $index): string
    {
        $externalId=mb_substr(trim((string)($row['external_id']??'')),0,255); $name=trim((string)($row['name']??''));
        if ($externalId==='' || $name==='' || !str_starts_with($externalId,'places:')) return 'skipped';
        $type=in_array(($row['stay_type']??''),self::STAY_TYPES,true)?(string)$row['stay_type']:'other';
        $price=in_array(($row['price_type']??''),self::PRICE_TYPES,true)?(string)$row['price_type']:'unknown';
        $hold=$this->holdReason($row,$name);
        $duplicate=$this->duplicate($row,$index);
        $inserted=Database::affecting(
            "INSERT IGNORE INTO caravan_stay_import_candidates (job_id,brand_id,external_id,name,address,phone,website,latitude,longitude,candidate_state,stay_type,price_type,business_status,route_hubs_json,raw_json,review_status,hold_reason,duplicate_park_id,duplicate_score,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())",
            [$jobId,$brandId,$externalId,mb_substr($name,0,190),$this->nullableLimit($row['address']??null,255),$this->nullableLimit($row['phone']??null,40),$this->nullableLimit($row['website']??null,500),$row['latitude']??null,$row['longitude']??null,$this->nullableLimit(strtoupper((string)($row['state']??'')),3),$type,$price,$this->nullableLimit($row['business_status']??null,40),json_encode($row['route_hubs']??[],JSON_THROW_ON_ERROR),json_encode($row,JSON_THROW_ON_ERROR),$hold?'held':'pending',$hold,$duplicate['id'],$duplicate['score']]
        );
        if ($inserted<1) return 'skipped';
        return $hold?'held':'inserted';
    }

    private function holdReason(array $row,string $name): ?string
    {
        if (($row['business_status']??'')!=='OPERATIONAL') return 'Google did not report this place as operational.';
        $haystack=strtolower($name.' '.implode(' ',(array)($row['place_types']??[])));
        if (preg_match('/\b(hotel|motel|hostel|apartment|bed and breakfast|serviced apartment)\b/',$haystack)===1 && preg_match('/caravan|camp|rv park/',$haystack)!==1) return 'The result appears to be general accommodation rather than a caravan-suitable stay.';
        return null;
    }

    /** @return array<string,array<string,array<int,array<string,mixed>>>> */
    private function parkIndex(): array
    {
        $index=['name'=>[],'phone'=>[],'host'=>[]];
        foreach(Database::select('SELECT id,name,phone,website FROM caravan_parks WHERE deleted_at IS NULL') as $park) {
            $name=$this->normal((string)$park['name']); $phone=$this->normal((string)($park['phone']??'')); $host=$this->host((string)($park['website']??''));
            if($name!=='')$index['name'][$name][]=$park; if($phone!=='')$index['phone'][$phone][]=$park; if($host!=='')$index['host'][$host][]=$park;
        }
        return $index;
    }

    /** @param array<string,array<string,array<int,array<string,mixed>>>> $index @return array{id:?int,score:int} */
    private function duplicate(array $row,array $index): array
    {
        $scores=[];
        foreach([['name',$this->normal((string)($row['name']??'')),75],['phone',$this->normal((string)($row['phone']??'')),95],['host',$this->host((string)($row['website']??'')),85]] as [$kind,$value,$score]) {
            if($value==='' || !isset($index[$kind][$value]) || count($index[$kind][$value])!==1) continue;
            $id=(int)$index[$kind][$value][0]['id']; $scores[$id]=min(100,($scores[$id]??0)+(int)$score);
        }
        if($scores===[])return ['id'=>null,'score'=>0]; arsort($scores); $id=(int)array_key_first($scores); return ['id'=>$id,'score'=>(int)$scores[$id]];
    }

    /** @return array{town_id:?int,region_id:?int,state_id:?int} */
    private function candidateLocation(array $candidate): array
    {
        if(!is_numeric($candidate['latitude']??null)||!is_numeric($candidate['longitude']??null))return ['town_id'=>null,'region_id'=>null,'state_id'=>null];
        $town=Town::nearestActive((float)$candidate['latitude'],(float)$candidate['longitude']);
        if($town===null || strtoupper((string)($candidate['candidate_state']??''))!==(string)$town['state_abbr'] || (float)$town['distance_km']>100)return ['town_id'=>null,'region_id'=>null,'state_id'=>null];
        return ['town_id'=>(int)$town['id'],'region_id'=>(int)($town['region_id']??0)?:null,'state_id'=>(int)$town['state_id']];
    }

    private function validEvidenceUrl(string $url): bool
    {
        if(filter_var($url,FILTER_VALIDATE_URL)===false || !in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true))return false;
        $host=$this->host($url);
        if ($host === '') return false;
        foreach (['google.com','google.com.au','googleusercontent.com','goo.gl','g.co'] as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) return false;
        }
        return true;
    }

    private function isAuthorityUrl(string $url): bool
    {
        $host=$this->host($url); return $host!=='' && ($host==='gov.au' || str_ends_with($host,'.gov.au'));
    }

    private function cleanupStaging(): void
    {
        $dir=BASE_PATH.'/'.self::STAGING_DIR;
        if(is_dir($dir))foreach(glob($dir.'/*.jsonl')?:[] as $file)if(is_file($file)&&(int)filemtime($file)<time()-172800)@unlink($file);
        Database::query("UPDATE caravan_stay_import_jobs SET status='failed',staged_file=NULL,completed_at=NOW() WHERE status IN ('queued','running') AND expires_at<NOW()");
    }

    private function normal(string $value): string { return preg_replace('/[^a-z0-9]+/','',strtolower($value))??''; }
    private function host(string $url): string { return strtolower(preg_replace('/^www\./','',(string)parse_url($url,PHP_URL_HOST))??''); }
    private function nullableLimit(mixed $value,int $limit): ?string { $value=trim((string)$value); return $value===''?null:mb_substr($value,0,$limit); }
}
