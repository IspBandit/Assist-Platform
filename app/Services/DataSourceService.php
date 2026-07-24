<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Platform\DataSources\ConnectorRegistry;
use App\Platform\DataSources\DuplicateMatcher;
use RuntimeException;
use Throwable;

final class DataSourceService
{
    private ConnectorRegistry $registry;
    public function __construct(?ConnectorRegistry $registry = null)
    {
        $this->registry = $registry ?? new ConnectorRegistry();
    }

    /** @return array<int,array<string,mixed>> */
    public function connectors(): array
    {
        return Database::select('SELECT c.*, cr.value_hint, cr.updated_at AS credential_updated_at, COALESCE(u.requests_used,0) AS requests_today, COALESCE(u.estimated_cost_aud,0) AS cost_today FROM data_source_connectors c LEFT JOIN data_source_credentials cr ON cr.connector_id=c.id AND cr.credential_key=\'api_key\' LEFT JOIN data_source_usage_daily u ON u.connector_id=c.id AND u.usage_date=CURRENT_DATE ORDER BY c.name');
    }

    public function saveConnector(int $id, string $apiKey, int $dailyLimit, float $dailyBudget, bool $active, int $userId): void
    {
        $connector = $this->connectorRow($id);
        $dailyLimit = max(1, min(100000, $dailyLimit));
        $dailyBudget = max(0, min(100000, $dailyBudget));
        Database::query('UPDATE data_source_connectors SET status=?, daily_request_limit=?, daily_budget_aud=?, updated_at=NOW() WHERE id=?', [$active ? 'active' : 'configured', $dailyLimit, $dailyBudget, $id]);
        if (trim($apiKey) !== '') {
            $encrypted = SecretCipher::encrypt(trim($apiKey));
            $hint = '••••' . substr(trim($apiKey), -4);
            Database::query('INSERT INTO data_source_credentials (connector_id,credential_key,encrypted_value,value_hint,updated_by,created_at,updated_at) VALUES (?,\'api_key\',?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE encrypted_value=VALUES(encrypted_value),value_hint=VALUES(value_hint),updated_by=VALUES(updated_by),updated_at=NOW()', [$id, $encrypted, $hint, $userId]);
        }
        AuditLog::record('data_source.connector_updated', 'data_source_connector', (string) $id, null, json_encode(['key'=>$connector['connector_key'],'active'=>$active,'daily_limit'=>$dailyLimit,'daily_budget'=>$dailyBudget]));
    }

    /** @return array<int,array<string,mixed>> */
    public function mappings(int $brandId): array
    {
        return Database::select('SELECT c.id AS category_id,c.name AS category_name,c.category_key,m.id,m.connector_id,m.external_query,m.is_active,ds.name AS connector_name FROM brand_provider_categories c LEFT JOIN data_source_category_mappings m ON m.category_id=c.id AND m.brand_id=c.brand_id LEFT JOIN data_source_connectors ds ON ds.id=m.connector_id WHERE c.brand_id=? AND c.is_active=1 ORDER BY c.sort_order,c.name', [$brandId]);
    }

    public function saveMapping(int $connectorId, int $brandId, int $categoryId, string $query, bool $active): void
    {
        if (trim($query) === '') { throw new RuntimeException('A search phrase is required.'); }
        Database::query('INSERT INTO data_source_category_mappings (connector_id,brand_id,category_id,external_query,is_active,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE external_query=VALUES(external_query),is_active=VALUES(is_active),updated_at=NOW()', [$connectorId,$brandId,$categoryId,trim($query),$active ? 1 : 0]);
        AuditLog::record('data_source.mapping_updated', 'brand_provider_category', (string) $categoryId);
    }

    /** @return array<string,mixed> */
    public function run(int $connectorId, int $brandId, int $mappingId, string $location, int $userId): array
    {
        $connectorRow = $this->connectorRow($connectorId);
        if ($connectorRow['status'] !== 'active') { throw new RuntimeException('Enable this connector before running an import.'); }
        $usage = Database::selectOne('SELECT requests_used,estimated_cost_aud FROM data_source_usage_daily WHERE connector_id=? AND usage_date=CURRENT_DATE', [$connectorId]) ?? ['requests_used'=>0,'estimated_cost_aud'=>0];
        if ((int) $usage['requests_used'] >= (int) $connectorRow['daily_request_limit']) { throw new RuntimeException('The connector daily request quota has been reached.'); }
        if ((float) $connectorRow['daily_budget_aud'] > 0 && (float) $usage['estimated_cost_aud'] + (float) $connectorRow['estimated_request_cost_aud'] > (float) $connectorRow['daily_budget_aud']) { throw new RuntimeException('The connector daily budget guard has been reached.'); }
        $mapping = Database::selectOne('SELECT * FROM data_source_category_mappings WHERE id=? AND connector_id=? AND brand_id=? AND is_active=1', [$mappingId,$connectorId,$brandId]);
        if ($mapping === null) { throw new RuntimeException('Select an active category mapping.'); }
        $jobId = Database::insert('INSERT INTO data_source_import_jobs (connector_id,brand_id,mapping_id,status,scope_json,requested_by,started_at,created_at) VALUES (?,?,?,\'running\',?,?,NOW(),NOW())', [$connectorId,$brandId,$mappingId,json_encode(['location'=>$location], JSON_THROW_ON_ERROR),$userId]);
        try {
            $credential = Database::selectOne('SELECT encrypted_value FROM data_source_credentials WHERE connector_id=? AND credential_key=\'api_key\'', [$connectorId]);
            $settings = json_decode((string) ($connectorRow['settings_json'] ?? '{}'), true) ?: [];
            $rows = $this->registry->resolve((string) $connectorRow['connector_key'],(string)$connectorRow['connector_class'])->search(['query'=>$mapping['external_query'],'location'=>$location,'limit'=>20], ['api_key'=>SecretCipher::decrypt((string) ($credential['encrypted_value'] ?? ''))], $settings);
            $created = 0;
            foreach ($rows as $row) { $created += $this->storeCandidate($jobId,$connectorId,$brandId,(int)$mapping['category_id'],$row) ? 1 : 0; }
            $cost = (float) $connectorRow['estimated_request_cost_aud'];
            Database::query('INSERT INTO data_source_usage_daily (connector_id,usage_date,requests_used,estimated_cost_aud,updated_at) VALUES (?,CURRENT_DATE,1,?,NOW()) ON DUPLICATE KEY UPDATE requests_used=requests_used+1,estimated_cost_aud=estimated_cost_aud+VALUES(estimated_cost_aud),updated_at=NOW()', [$connectorId,$cost]);
            Database::query('UPDATE data_source_connectors SET last_used_at=NOW(),last_error=NULL WHERE id=?', [$connectorId]);
            Database::query('UPDATE data_source_import_jobs SET status=\'review\',requests_used=1,candidates_found=?,candidates_new=?,completed_at=NOW() WHERE id=?', [count($rows),$created,$jobId]);
            AuditLog::record('data_source.import_completed','data_source_import_job',(string)$jobId,null,json_encode(['found'=>count($rows),'new'=>$created]));
            return ['job_id'=>$jobId,'found'=>count($rows),'new'=>$created];
        } catch (Throwable $e) {
            Database::query('UPDATE data_source_import_jobs SET status=\'failed\',error_message=?,completed_at=NOW() WHERE id=?', [substr($e->getMessage(),0,1000),$jobId]);
            Database::query('UPDATE data_source_connectors SET status=\'error\',last_error=?,updated_at=NOW() WHERE id=?', [substr($e->getMessage(),0,500),$connectorId]);
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function queue(int $brandId): array
    {
        return Database::select('SELECT c.*,ds.name AS connector_name,bpc.name AS category_name,p.business_name AS duplicate_name FROM data_source_import_candidates c JOIN data_source_connectors ds ON ds.id=c.connector_id LEFT JOIN brand_provider_categories bpc ON bpc.id=c.category_id LEFT JOIN providers p ON p.id=c.duplicate_provider_id WHERE c.brand_id=? AND c.review_status=\'pending\' ORDER BY c.duplicate_score DESC,c.confidence DESC,c.created_at DESC LIMIT 200', [$brandId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function jobs(int $brandId): array { return Database::select('SELECT j.*,c.name AS connector_name,m.external_query FROM data_source_import_jobs j JOIN data_source_connectors c ON c.id=j.connector_id LEFT JOIN data_source_category_mappings m ON m.id=j.mapping_id WHERE j.brand_id=? ORDER BY j.id DESC LIMIT 20', [$brandId]); }
    /** @return array<int,array<string,mixed>> */
    public function schedules(int $brandId): array { return Database::select('SELECT s.*,c.name AS connector_name,m.external_query FROM data_source_schedules s JOIN data_source_connectors c ON c.id=s.connector_id LEFT JOIN data_source_category_mappings m ON m.id=s.mapping_id WHERE s.brand_id=? ORDER BY s.name', [$brandId]); }

    public function saveSchedule(int $connectorId,int $brandId,int $mappingId,string $name,string $location,string $frequency,bool $enabled,int $userId): void
    {
        if (!in_array($frequency,['daily','weekly','monthly'],true)) { $frequency='weekly'; }
        $interval = ['daily'=>1,'weekly'=>7,'monthly'=>30][$frequency];
        Database::query('INSERT INTO data_source_schedules (connector_id,brand_id,mapping_id,name,frequency,scope_json,is_enabled,next_run_at,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL '.$interval.' DAY),?,NOW(),NOW())', [$connectorId,$brandId,$mappingId,trim($name),$frequency,json_encode(['location'=>trim($location)],JSON_THROW_ON_ERROR),$enabled?1:0,$userId]);
        AuditLog::record('data_source.schedule_created','data_source_connector',(string)$connectorId);
    }

    /** Run due schedules from a trusted CLI/cron process. @return array{run:int,failed:int,purged:int} */
    public function runDueSchedules(int $limit = 10): array
    {
        $purged = Database::affecting("DELETE FROM data_source_import_candidates WHERE expires_at < NOW() AND review_status = 'pending'");
        $rows = Database::select('SELECT * FROM data_source_schedules WHERE is_enabled=1 AND next_run_at<=NOW() ORDER BY next_run_at LIMIT '.max(1,min(50,$limit)));
        $run=0;$failed=0;
        foreach($rows as $schedule){
            $scope=json_decode((string)$schedule['scope_json'],true)?:[];
            try{$this->run((int)$schedule['connector_id'],(int)$schedule['brand_id'],(int)$schedule['mapping_id'],(string)($scope['location']??''),(int)($schedule['created_by']??0));++$run;}
            catch(Throwable){++$failed;}
            $days=['daily'=>1,'weekly'=>7,'monthly'=>30][(string)$schedule['frequency']]??7;
            Database::query('UPDATE data_source_schedules SET last_run_at=NOW(),next_run_at=DATE_ADD(NOW(),INTERVAL '.$days.' DAY),updated_at=NOW() WHERE id=?',[$schedule['id']]);
        }
        return ['run'=>$run,'failed'=>$failed,'purged'=>$purged];
    }

    /** @return array<int,array<string,mixed>> */
    public function coverage(int $brandId): array
    {
        return Database::select('SELECT c.id,c.name,c.category_key,COUNT(DISTINCT a.listing_id) AS provider_count,COUNT(DISTINCT CASE WHEN l.is_verified=1 THEN a.listing_id END) AS verified_count FROM brand_provider_categories c LEFT JOIN provider_brand_category_assignments a ON a.category_id=c.id LEFT JOIN provider_brand_listings l ON l.id=a.listing_id AND l.status=\'active\' WHERE c.brand_id=? AND c.is_active=1 GROUP BY c.id,c.name,c.category_key ORDER BY provider_count ASC,c.name', [$brandId]);
    }

    public function review(int $candidateId,string $decision,?int $providerId,int $userId,bool $retentionConfirmed=false): int
    {
        $candidate = Database::selectOne('SELECT * FROM data_source_import_candidates WHERE id=? AND review_status=\'pending\'', [$candidateId]);
        if ($candidate === null) { throw new RuntimeException('Candidate is no longer awaiting review.'); }
        if ($decision === 'reject') { Database::query('UPDATE data_source_import_candidates SET review_status=\'rejected\',reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$userId,$candidateId]); AuditLog::record('data_source.candidate_rejected','data_source_import_candidate',(string)$candidateId); return 0; }
        if ($decision === 'merge') {
            $target = $providerId ?: (int) ($candidate['duplicate_provider_id'] ?? 0);
            if ($target < 1) { throw new RuntimeException('Choose an existing provider to merge into.'); }
            $this->attachProvider($target,$candidate,$userId);
            Database::query('UPDATE data_source_import_candidates SET review_status=\'merged\',provider_id=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$target,$userId,$candidateId]);
            AuditLog::record('data_source.candidate_merged','provider',(string)$target);
            return $target;
        }
        if ($decision !== 'approve') { throw new RuntimeException('Unknown review decision.'); }
        if (!$retentionConfirmed) { throw new RuntimeException('Confirm an independent right to retain and publish this business data before approval.'); }
        $slug = $this->uniqueSlug((string)$candidate['business_name']);
        Database::beginTransaction();
        try {
            $newId = Database::insert("INSERT INTO providers (business_name,slug,phone,public_phone,website,description,service_model,status,is_unclaimed,source_note,source_url,created_at,updated_at) VALUES (?,?,?,?,?,?, 'workshop','active',1,?,?,NOW(),NOW())", [$candidate['business_name'],$slug,$candidate['phone'],$candidate['phone'],$candidate['website'],'Imported for review from an external directory.',$candidate['formatted_address'],$candidate['website']]);
            $this->attachProvider($newId,$candidate,$userId);
            Database::query('UPDATE data_source_import_candidates SET review_status=\'approved\',provider_id=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$newId,$userId,$candidateId]);
            Database::commit();
            AuditLog::record('data_source.candidate_approved','provider',(string)$newId);
            return $newId;
        } catch (Throwable $e) { Database::rollBack(); throw $e; }
    }

    private function attachProvider(int $providerId,array $candidate,int $userId): void
    {
        $slug = $this->uniqueBrandSlug((int)$candidate['brand_id'],(string)$candidate['business_name'],$providerId);
        Database::query("INSERT IGNORE INTO provider_brand_listings (brand_id,provider_id,slug,display_name,status,is_featured,is_verified,search_visible,created_at,updated_at) VALUES (?,?,?,?,'active',0,0,1,NOW(),NOW())",[$candidate['brand_id'],$providerId,$slug,$candidate['business_name']]);
        $listingId=(int)Database::scalar('SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',[$candidate['brand_id'],$providerId]);
        if (!empty($candidate['category_id'])) { Database::query("INSERT IGNORE INTO provider_brand_category_assignments (listing_id,category_id,assignment_source,confidence,is_verified,created_at) VALUES (?,?,'import',?,0,NOW())",[$listingId,$candidate['category_id'],$candidate['confidence']]); }
        $connectorKey=(string)Database::scalar('SELECT connector_key FROM data_source_connectors WHERE id=?',[$candidate['connector_id']]);
        Database::query("INSERT IGNORE INTO provider_discovery_evidence (provider_id,brand_id,source_type,connector_key,source_reference,verification_status,discovered_at,last_checked_at,checked_by,notes) VALUES (?,?,'other',?,?,'discovered',NOW(),NOW(),?,?)",[$providerId,$candidate['brand_id'],$connectorKey,$candidate['external_id'],$userId,'Discovered through connector review queue']);
    }

    private function storeCandidate(int $jobId,int $connectorId,int $brandId,int $categoryId,array $row): bool
    {
        $candidate=['business_name'=>$row['business_name'],'phone'=>$row['phone']??'','website'=>$row['website']??''];
        $where=['business_name LIKE ?'];$params=['%'.$row['business_name'].'%'];
        if(trim((string)($row['phone']??''))!==''){$where[]='phone=?';$params[]=$row['phone'];}
        if(trim((string)($row['website']??''))!==''){$where[]='website=?';$params[]=$row['website'];}
        $providers=Database::select('SELECT id,business_name,phone,website FROM providers WHERE deleted_at IS NULL AND ('.implode(' OR ',$where).') LIMIT 30',$params);
        $best=['score'=>0,'reasons'=>[],'id'=>null]; $matcher=new DuplicateMatcher();
        foreach($providers as $provider){$match=$matcher->score($candidate,$provider);if($match['score']>$best['score']){$best=$match+['id'=>(int)$provider['id']];}}
        return Database::affecting('INSERT IGNORE INTO data_source_import_candidates (job_id,connector_id,brand_id,category_id,external_id,business_name,formatted_address,phone,website,latitude,longitude,raw_json,confidence,duplicate_provider_id,duplicate_score,duplicate_reasons_json,created_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY))',[$jobId,$connectorId,$brandId,$categoryId,$row['external_id'],$row['business_name'],$row['formatted_address']??null,$row['phone']??null,$row['website']??null,$row['latitude']??null,$row['longitude']??null,json_encode($row['raw']??$row,JSON_THROW_ON_ERROR),85,$best['score']>=60?$best['id']:null,$best['score'],json_encode($best['reasons'],JSON_THROW_ON_ERROR)])>0;
    }

    private function connectorRow(int $id): array { $row=Database::selectOne('SELECT * FROM data_source_connectors WHERE id=?',[$id]);if($row===null){throw new RuntimeException('Data source connector not found.');}return $row; }
    private function uniqueSlug(string $name): string { $base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name))??'','-') ?: 'provider';$slug=$base;$i=2;while((int)Database::scalar('SELECT COUNT(*) FROM providers WHERE slug=?',[$slug])>0){$slug=$base.'-'.$i++;}return $slug; }
    private function uniqueBrandSlug(int $brandId,string $name,int $providerId): string { $existing=Database::selectOne('SELECT slug FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',[$brandId,$providerId]);if($existing){return(string)$existing['slug'];}$base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name))??'','-')?:'provider';$slug=$base;$i=2;while((int)Database::scalar('SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id=? AND slug=?',[$brandId,$slug])>0){$slug=$base.'-'.$i++;}return$slug;}
}
