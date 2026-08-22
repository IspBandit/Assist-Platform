<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Town;
use App\Platform\DataSources\ConnectorRegistry;
use App\Platform\DataSources\BulkReviewPolicy;
use App\Platform\DataSources\DuplicateMatcher;
use App\Services\Api\ProviderImportCandidateReviewGateway;
use RuntimeException;
use Throwable;

final class DataSourceService implements ProviderImportCandidateReviewGateway
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

    /** @return array{rows:array<int,array<string,mixed>>,total:int,page:int,perPage:int,summary:array<string,int>} */
    public function reviewQueue(int $brandId, array $filters = []): array
    {
        $status = (string) ($filters['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'held', 'approved', 'merged', 'rejected'], true)) {
            $status = 'pending';
        }
        $where = ['c.brand_id=?', 'c.review_status=?'];
        $params = [$brandId, $status];
        $state = strtoupper(trim((string) ($filters['state'] ?? '')));
        if (preg_match('/^[A-Z]{2,3}$/', $state) === 1) {
            $where[] = 'c.candidate_state=?';
            $params[] = $state;
        }
        $categoryId = (int) ($filters['category'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'c.category_id=?';
            $params[] = $categoryId;
        }
        $evidence = (string) ($filters['evidence'] ?? '');
        if (in_array($evidence, ['required', 'confirmed', 'claimed'], true)) {
            $where[] = 'c.evidence_status=?';
            $params[] = $evidence;
        }
        $duplicate = (string) ($filters['duplicate'] ?? '');
        if ($duplicate === 'yes') $where[] = 'c.duplicate_provider_id IS NOT NULL';
        if ($duplicate === 'no') $where[] = 'c.duplicate_provider_id IS NULL';
        $contact = (string) ($filters['contact'] ?? '');
        if ($contact === 'both') $where[] = "COALESCE(c.phone,'')<>'' AND COALESCE(c.website,'')<>''";
        if ($contact === 'phone') $where[] = "COALESCE(c.phone,'')<>''";
        if ($contact === 'website') $where[] = "COALESCE(c.website,'')<>''";
        if ($contact === 'none') $where[] = "COALESCE(c.phone,'')='' AND COALESCE(c.website,'')=''";
        $route = trim((string) ($filters['route'] ?? ''));
        if ($route !== '') {
            $where[] = 'c.route_hub LIKE ?';
            $params[] = '%' . $route . '%';
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.business_name LIKE ? OR c.formatted_address LIKE ? OR c.phone LIKE ? OR c.website LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $clause = implode(' AND ', $where);
        $perPage = max(20, min(100, (int) ($filters['per_page'] ?? 50)));
        $total = (int) Database::scalar('SELECT COUNT(*) FROM data_source_import_candidates c WHERE ' . $clause, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($filters['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            'SELECT c.*,ds.name AS connector_name,ds.connector_key,bpc.name AS category_name,p.business_name AS duplicate_name '
            . 'FROM data_source_import_candidates c JOIN data_source_connectors ds ON ds.id=c.connector_id '
            . 'LEFT JOIN brand_provider_categories bpc ON bpc.id=c.category_id '
            . 'LEFT JOIN providers p ON p.id=c.duplicate_provider_id WHERE ' . $clause
            . ' ORDER BY (c.duplicate_provider_id IS NOT NULL) DESC,c.confidence DESC,c.business_name LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        $summaryRows = Database::select(
            'SELECT review_status,COUNT(*) total FROM data_source_import_candidates WHERE brand_id=? GROUP BY review_status',
            [$brandId]
        );
        $summary = ['pending'=>0,'held'=>0,'approved'=>0,'merged'=>0,'rejected'=>0];
        foreach ($summaryRows as $row) $summary[(string)$row['review_status']] = (int)$row['total'];
        return compact('rows', 'total', 'page', 'perPage', 'summary');
    }

    /** @return array<int,array<string,mixed>> */
    public function reviewCategories(int $brandId): array
    {
        return Database::select('SELECT id,category_key,name FROM brand_provider_categories WHERE brand_id=? AND is_active=1 ORDER BY sort_order,name', [$brandId]);
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
        $purged += (new NationalRouteImportService())->purgeExpiredCandidates();
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

    public function review(int $candidateId,int $brandId,string $decision,?int $providerId,int $userId,bool $retentionConfirmed=false,?int $categoryId=null,string $evidenceUrl='',string $reviewNotes=''): int
    {
        $candidate = Database::selectOne('SELECT c.*,ds.connector_key FROM data_source_import_candidates c JOIN data_source_connectors ds ON ds.id=c.connector_id WHERE c.id=? AND c.brand_id=? AND c.review_status IN (\'pending\',\'held\')', [$candidateId,$brandId]);
        if ($candidate === null) { throw new RuntimeException('Candidate is no longer awaiting review.'); }
        if ($categoryId !== null && $categoryId > 0) {
            if ((int) Database::scalar('SELECT COUNT(*) FROM brand_provider_categories WHERE id=? AND brand_id=? AND is_active=1', [$categoryId,$candidate['brand_id']]) < 1) {
                throw new RuntimeException('Choose a category from the current workspace.');
            }
            $candidate['category_id'] = $categoryId;
        }
        $evidenceUrl = trim($evidenceUrl);
        $reviewNotes = mb_substr(trim($reviewNotes), 0, 1000);
        $publishing = in_array($decision, ['approve','merge'], true);
        $confirmingEvidence = $decision === 'confirm';
        if (!$publishing && !$confirmingEvidence) {
            $evidenceUrl = '';
        }
        if (($publishing || $confirmingEvidence) && $evidenceUrl !== '' && !$this->validEvidenceUrl($evidenceUrl)) {
            throw new RuntimeException('Use the business website or another independent http/https evidence URL, not a Google search or Maps URL.');
        }
        Database::query(
            'UPDATE data_source_import_candidates SET category_id=?,evidence_url=?,review_notes=?,updated_at=NOW() WHERE id=? AND brand_id=?',
            [$candidate['category_id'] ?: null, $evidenceUrl !== '' ? $evidenceUrl : null, $reviewNotes !== '' ? $reviewNotes : null, $candidateId, $brandId]
        );
        $candidate['evidence_url'] = $evidenceUrl;
        $candidate['review_notes'] = $reviewNotes;
        if ($decision === 'reject') { Database::query('UPDATE data_source_import_candidates SET review_status=\'rejected\',reviewed_by=?,reviewed_at=NOW() WHERE id=? AND brand_id=?',[$userId,$candidateId,$brandId]); AuditLog::record('data_source.candidate_rejected','data_source_import_candidate',(string)$candidateId); return 0; }
        if ($decision === 'hold') { Database::query('UPDATE data_source_import_candidates SET review_status=\'held\',reviewed_by=?,reviewed_at=NOW() WHERE id=? AND brand_id=?',[$userId,$candidateId,$brandId]); AuditLog::record('data_source.candidate_held','data_source_import_candidate',(string)$candidateId); return 0; }
        if ($decision === 'restore') { Database::query('UPDATE data_source_import_candidates SET review_status=\'pending\',reviewed_by=NULL,reviewed_at=NULL WHERE id=? AND brand_id=?',[$candidateId,$brandId]); AuditLog::record('data_source.candidate_restored','data_source_import_candidate',(string)$candidateId); return 0; }
        if ($confirmingEvidence) {
            if (empty($candidate['category_id']) || !$retentionConfirmed || $evidenceUrl === '') {
                throw new RuntimeException('Choose the confirmed service, record an independent evidence URL and tick the retention confirmation.');
            }
            $this->publicServiceCategoryId($candidate);
            Database::query(
                "UPDATE data_source_import_candidates SET review_status='pending',evidence_status='confirmed',reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=? AND brand_id=?",
                [$userId,$candidateId,$brandId]
            );
            AuditLog::record('data_source.candidate_evidence_confirmed','data_source_import_candidate',(string)$candidateId,null,json_encode(['evidence_url'=>$evidenceUrl,'category_id'=>(int)$candidate['category_id']]));
            return 0;
        }
        if ($publishing && empty($candidate['category_id'])) {
            throw new RuntimeException('Choose and confirm an active service category before approving or merging this candidate.');
        }
        if ($publishing) { $this->publicServiceCategoryId($candidate); }
        if ($publishing && (string)$candidate['connector_key'] === 'national_route_places') {
            if (!$retentionConfirmed || $evidenceUrl === '') {
                throw new RuntimeException('Confirm the independent source and record its evidence URL before approving or merging a Google-discovered listing.');
            }
            $candidate['evidence_status'] = 'confirmed';
        }
        if ($decision === 'merge') {
            $target = $providerId ?: (int) ($candidate['duplicate_provider_id'] ?? 0);
            if ($target < 1) { throw new RuntimeException('Choose an existing provider to merge into.'); }
            $targetProvider = Database::selectOne('SELECT id,business_name,phone,website,is_unclaimed FROM providers WHERE id=? AND deleted_at IS NULL',[$target]);
            if ($targetProvider === null) { throw new RuntimeException('The selected merge target provider no longer exists.'); }
            if (!$retentionConfirmed || $evidenceUrl === '') {
                throw new RuntimeException('Confirm the independent source and record its evidence URL before merging this candidate.');
            }
            $candidate['evidence_status'] = 'confirmed';
            $identity = (new DuplicateMatcher())->score($candidate, $targetProvider);
            $mergeCandidate = array_replace($candidate, [
                'duplicate_provider_id' => $target,
                'duplicate_score' => $identity['score'],
                'duplicate_reasons_json' => json_encode($identity['reasons'], JSON_THROW_ON_ERROR),
                'target_is_unclaimed' => $targetProvider['is_unclaimed'] ?? 0,
            ]);
            $problems = (new BulkReviewPolicy())->exactMergeProblems($mergeCandidate);
            if ($problems !== []) {
                throw new RuntimeException('This merge is not safe: ' . implode('; ', $problems) . '.');
            }
            Database::beginTransaction();
            try {
                $lockedCandidate = Database::selectOne("SELECT id FROM data_source_import_candidates WHERE id=? AND brand_id=? AND review_status IN ('pending','held') FOR UPDATE",[$candidateId,$brandId]);
                $lockedTarget = Database::selectOne('SELECT id FROM providers WHERE id=? AND deleted_at IS NULL AND is_unclaimed=1 FOR UPDATE',[$target]);
                if ($lockedCandidate === null) { throw new RuntimeException('Candidate is no longer awaiting review.'); }
                if ($lockedTarget === null) { throw new RuntimeException('The merge target is no longer an unclaimed provider.'); }
                $this->attachProvider($target,$candidate,$userId);
                Database::query("UPDATE data_source_import_candidates SET review_status='merged',evidence_status='confirmed',provider_id=?,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND brand_id=? AND review_status IN ('pending','held')",[$target,$userId,$candidateId,$brandId]);
                Database::commit();
            } catch (Throwable $e) {
                Database::rollBack();
                throw $e;
            }
            AuditLog::record('data_source.candidate_merged','provider',(string)$target);
            return $target;
        }
        if ($decision !== 'approve') { throw new RuntimeException('Unknown review decision.'); }
        if (!$retentionConfirmed) { throw new RuntimeException('Confirm an independent right to retain and publish this business data before approval.'); }
        $approvalProblems = (new BulkReviewPolicy())->approvalProblems($candidate);
        if ($approvalProblems !== []) {
            throw new RuntimeException('This publication is not safe: ' . implode('; ', $approvalProblems) . '.');
        }
        $slug = $this->uniqueSlug((string)$candidate['business_name']);
        $location = $this->candidateLocation($candidate);
        $website = mb_strlen((string)($candidate['website'] ?? '')) <= 255 ? ($candidate['website'] ?: null) : null;
        Database::beginTransaction();
        try {
            $lockedCandidate = Database::selectOne("SELECT id FROM data_source_import_candidates WHERE id=? AND brand_id=? AND review_status='pending' FOR UPDATE",[$candidateId,$brandId]);
            if ($lockedCandidate === null) { throw new RuntimeException('Candidate is no longer eligible for publication.'); }
            $newId = Database::insert("INSERT INTO providers (business_name,slug,phone,public_phone,show_public_phone,website,base_town_id,region_id,street_address,latitude,longitude,description,service_model,status,is_unclaimed,source_note,source_url,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'workshop','active',1,?,?,NOW(),NOW())", [$candidate['business_name'],$slug,$candidate['phone'],$candidate['phone'],trim((string)$candidate['phone'])!==''?1:0,$website,$location['town_id'],$location['region_id'],$candidate['formatted_address'],$candidate['latitude'],$candidate['longitude'],'Imported after independent evidence review.','Independent evidence confirmed during import review.',$candidate['evidence_url'] ?: $website]);
            $this->attachProvider($newId,$candidate,$userId);
            Database::query("UPDATE data_source_import_candidates SET review_status='approved',evidence_status='confirmed',provider_id=?,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND brand_id=?",[$newId,$userId,$candidateId,$brandId]);
            Database::commit();
            AuditLog::record('data_source.candidate_approved','provider',(string)$newId);
            return $newId;
        } catch (Throwable $e) { Database::rollBack(); throw $e; }
    }

    /**
     * @param array<int,int> $candidateIds
     * @return array{processed:int,skipped:int}
     */
    public function bulkReview(array $candidateIds,string $decision,int $brandId,int $userId,bool $confirmed=false): array
    {
        if (!in_array($decision, ['hold','reject','restore','approve_eligible','merge_exact_duplicates'], true)) {
            throw new RuntimeException('Choose a valid bulk review action.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $candidateIds), static fn(int $id): bool => $id > 0)));
        if ($ids === [] || count($ids) > 100) {
            throw new RuntimeException('Select between 1 and 100 candidates.');
        }
        if (in_array($decision, ['approve_eligible','merge_exact_duplicates'], true)) {
            if (!$confirmed) {
                throw new RuntimeException('Confirm the controlled publication or duplicate-merge action before continuing.');
            }
            return $this->bulkPublish($ids, $decision, $brandId, $userId);
        }
        $status = ['hold'=>'held','reject'=>'rejected','restore'=>'pending'][$decision];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = [$status, $userId, ...$ids, $brandId];
        $allowedCurrent = $decision === 'restore' ? "('held','rejected')" : "('pending','held')";
        $count = Database::affecting(
            'UPDATE data_source_import_candidates SET review_status=?,reviewed_by=?,reviewed_at=NOW() '
            . 'WHERE id IN (' . $placeholders . ') AND brand_id=? AND review_status IN ' . $allowedCurrent,
            $params
        );
        AuditLog::record('data_source.candidates_bulk_' . $decision,'data_source_import_candidate','bulk',null,json_encode(['count'=>$count]));
        return ['processed'=>$count,'skipped'=>count($ids)-$count];
    }

    /**
     * Process one bounded slice of the current filtered queue. Strong duplicate
     * links always run before publication and never copy fields to a provider.
     *
     * @return array{merged:int,approved:int,processed:int,skipped:int,failed:int,blocked:int,remaining:int,reasons:array<string,int>}
     */
    public function processEligibleQueue(array $filters,int $brandId,int $userId,int $limit=75,float $seconds=8.0): array
    {
        $limit = max(1, min(150, $limit));
        $deadline = microtime(true) + max(1.0, min(12.0, $seconds));
        $snapshot = $this->eligibleQueueSnapshot($brandId, $filters);
        $policy = new BulkReviewPolicy();
        $mergeIds = [];
        $approvalIds = [];
        foreach ($snapshot['rows'] as $candidate) {
            $action = $policy->eligibleQueueAction($candidate)['action'];
            if ($action === 'merge') $mergeIds[] = (int)$candidate['id'];
            if ($action === 'approve') $approvalIds[] = (int)$candidate['id'];
        }

        $merged = 0;
        $approved = 0;
        $failed = 0;
        $attempted = 0;
        $processingReasons = [];
        foreach ($mergeIds as $candidateId) {
            if ($attempted >= $limit || microtime(true) >= $deadline) break;
            ++$attempted;
            $candidate = $snapshot['by_id'][$candidateId] ?? null;
            if (!is_array($candidate)) continue;
            $count = Database::affecting(
                "UPDATE data_source_import_candidates SET review_status='merged',provider_id=?,reviewed_by=?,reviewed_at=NOW(),"
                . "review_notes=CONCAT_WS(' ',NULLIF(review_notes,''),'Automatically linked by the filtered eligible-queue run as a strong 70%+ duplicate; no candidate fields were copied to the provider.'),updated_at=NOW() "
                . "WHERE id=? AND brand_id=? AND review_status='pending' AND duplicate_provider_id=? AND duplicate_score>=? "
                . "AND EXISTS(SELECT 1 FROM providers p JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=data_source_import_candidates.brand_id AND pbl.deleted_at IS NULL WHERE p.id=data_source_import_candidates.duplicate_provider_id AND p.deleted_at IS NULL AND p.is_unclaimed=1) "
                . "AND (JSON_CONTAINS(duplicate_reasons_json,'\"same normalised name\"') OR JSON_CONTAINS(duplicate_reasons_json,'\"similar business name\"')) "
                . "AND (JSON_CONTAINS(duplicate_reasons_json,'\"same phone\"') OR JSON_CONTAINS(duplicate_reasons_json,'\"same website\"'))",
                [(int)$candidate['duplicate_provider_id'],$userId,$candidateId,$brandId,(int)$candidate['duplicate_provider_id'],BulkReviewPolicy::STRONG_DUPLICATE_SCORE]
            );
            if ($count > 0) {
                ++$merged;
                AuditLog::record('data_source.candidate_strong_duplicate_linked','provider',(string)$candidate['duplicate_provider_id'],null,json_encode(['candidate_id'=>$candidateId,'score'=>(int)$candidate['duplicate_score'],'run'=>'filtered_eligible_queue']));
            } else {
                ++$failed;
                $processingReasons['candidate changed before duplicate link'] = ($processingReasons['candidate changed before duplicate link'] ?? 0) + 1;
            }
        }

        // Publication begins only after every safe duplicate in this filter has
        // been linked, including duplicates left for the next bounded request.
        if ($this->eligibleQueueSnapshot($brandId, $filters)['mergeable'] === 0) {
            foreach ($approvalIds as $candidateId) {
                if ($attempted >= $limit || microtime(true) >= $deadline) break;
                ++$attempted;
                $candidate = $snapshot['by_id'][$candidateId] ?? null;
                if (!is_array($candidate)) continue;
                try {
                    $this->review(
                        $candidateId,$brandId,'approve',null,$userId,true,
                        (int)$candidate['category_id'],(string)$candidate['evidence_url'],
                        trim((string)($candidate['review_notes'] ?? '')) !== ''
                            ? (string)$candidate['review_notes']
                            : 'Published by the filtered eligible-queue run after evidence and category safeguards passed.'
                    );
                    ++$approved;
                } catch (Throwable $exception) {
                    ++$failed;
                    $reason = mb_substr($exception->getMessage(), 0, 180);
                    $processingReasons[$reason] = ($processingReasons[$reason] ?? 0) + 1;
                }
            }
        }

        $after = $this->eligibleQueueSnapshot($brandId, $filters);
        $reasons = $after['reasons'];
        foreach ($processingReasons as $reason=>$count) $reasons[$reason] = ($reasons[$reason] ?? 0) + $count;
        arsort($reasons);
        $result = [
            'merged'=>$merged,'approved'=>$approved,'processed'=>$merged+$approved,
            'skipped'=>$after['blocked']+$failed,'failed'=>$failed,'blocked'=>$after['blocked'],'remaining'=>$after['eligible'],
            'reasons'=>$reasons,
        ];
        AuditLog::record('data_source.filtered_eligible_queue_batch','data_source_import_candidate','bulk',null,json_encode($result + [
            'brand_id'=>$brandId,
            'filters'=>array_intersect_key($filters,array_flip(['state','category','evidence','duplicate','contact','route'])),
            'limit'=>$limit,
        ]));
        return $result;
    }

    /** @return array{pending:int,mergeable:int,approvable:int,eligible:int,blocked:int,reasons:array<string,int>} */
    public function eligibleQueueSummary(int $brandId,array $filters=[]): array
    {
        $snapshot = $this->eligibleQueueSnapshot($brandId,$filters);
        return [
            'pending'=>count($snapshot['rows']),
            'mergeable'=>$snapshot['mergeable'],
            'approvable'=>$snapshot['approvable'],
            'eligible'=>$snapshot['eligible'],
            'blocked'=>$snapshot['blocked'],
            'reasons'=>$snapshot['reasons'],
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>,by_id:array<int,array<string,mixed>>,mergeable:int,approvable:int,eligible:int,blocked:int,reasons:array<string,int>} */
    private function eligibleQueueSnapshot(int $brandId,array $filters): array
    {
        [$clause,$params] = $this->eligibleQueueWhere($brandId,$filters);
        $rows = Database::select(
            'SELECT c.id,c.brand_id,c.category_id,c.review_status,c.evidence_status,c.evidence_url,c.review_notes,c.duplicate_provider_id,c.duplicate_score,c.duplicate_reasons_json,'
            . 'p.is_unclaimed AS target_is_unclaimed,EXISTS(SELECT 1 FROM provider_brand_listings pbl WHERE pbl.provider_id=p.id AND pbl.brand_id=c.brand_id AND pbl.deleted_at IS NULL) AS target_has_brand_listing '
            . 'FROM data_source_import_candidates c LEFT JOIN providers p ON p.id=c.duplicate_provider_id AND p.deleted_at IS NULL WHERE ' . $clause . ' ORDER BY c.id',
            $params
        );
        $policy = new BulkReviewPolicy();
        $byId = [];
        $mergeable = 0;
        $approvable = 0;
        $blocked = 0;
        $reasons = [];
        foreach ($rows as $candidate) {
            $byId[(int)$candidate['id']] = $candidate;
            $decision = $policy->eligibleQueueAction($candidate);
            $problems = $decision['problems'];
            if ($problems === []) {
                if ($decision['action'] === 'merge') ++$mergeable; else ++$approvable;
                continue;
            }
            ++$blocked;
            foreach ($problems as $problem) $reasons[$problem] = ($reasons[$problem] ?? 0) + 1;
        }
        arsort($reasons);
        return ['rows'=>$rows,'by_id'=>$byId,'mergeable'=>$mergeable,'approvable'=>$approvable,'eligible'=>$mergeable+$approvable,'blocked'=>$blocked,'reasons'=>$reasons];
    }

    /** @return array{string,array<int,mixed>} */
    private function eligibleQueueWhere(int $brandId,array $filters): array
    {
        $where = ["c.brand_id=?", "c.review_status='pending'"];
        $params = [$brandId];
        $state = strtoupper(trim((string)($filters['state'] ?? '')));
        if (preg_match('/^[A-Z]{2,3}$/',$state) === 1) { $where[]='c.candidate_state=?'; $params[]=$state; }
        $category = (int)($filters['category'] ?? 0);
        if ($category > 0) { $where[]='c.category_id=?'; $params[]=$category; }
        $evidence = (string)($filters['evidence'] ?? '');
        if (in_array($evidence,['required','confirmed','claimed'],true)) { $where[]='c.evidence_status=?'; $params[]=$evidence; }
        $duplicate = (string)($filters['duplicate'] ?? '');
        if ($duplicate === 'yes') $where[]='c.duplicate_provider_id IS NOT NULL';
        if ($duplicate === 'no') $where[]='c.duplicate_provider_id IS NULL';
        $contact = (string)($filters['contact'] ?? '');
        if ($contact === 'both') $where[]="COALESCE(c.phone,'')<>'' AND COALESCE(c.website,'')<>''";
        if ($contact === 'phone') $where[]="COALESCE(c.phone,'')<>''";
        if ($contact === 'website') $where[]="COALESCE(c.website,'')<>''";
        if ($contact === 'none') $where[]="COALESCE(c.phone,'')='' AND COALESCE(c.website,'')=''";
        $route = trim((string)($filters['route'] ?? ''));
        if ($route !== '') { $where[]='c.route_hub LIKE ?'; $params[]='%'.$route.'%'; }
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[]='(c.business_name LIKE ? OR c.formatted_address LIKE ? OR c.phone LIKE ? OR c.website LIKE ?)';
            $like='%'.$search.'%'; array_push($params,$like,$like,$like,$like);
        }
        return [implode(' AND ',$where),$params];
    }

    /**
     * @param array<int,int> $candidateIds
     * @return array{processed:int,skipped:int}
     */
    private function bulkPublish(array $candidateIds,string $decision,int $brandId,int $userId): array
    {
        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $rows = Database::select(
            'SELECT c.*,ds.connector_key,p.is_unclaimed AS target_is_unclaimed '
            . 'FROM data_source_import_candidates c '
            . 'JOIN data_source_connectors ds ON ds.id=c.connector_id '
            . 'LEFT JOIN providers p ON p.id=c.duplicate_provider_id AND p.deleted_at IS NULL '
            . 'WHERE c.id IN (' . $placeholders . ') AND c.brand_id=?',
            [...$candidateIds, $brandId]
        );
        $policy = new BulkReviewPolicy();
        $processed = 0;
        foreach ($rows as $candidate) {
            $problems = $decision === 'approve_eligible'
                ? $policy->approvalProblems($candidate)
                : $policy->exactMergeProblems($candidate);
            if ($problems !== []) {
                continue;
            }
            $reviewDecision = $decision === 'approve_eligible' ? 'approve' : 'merge';
            $this->review(
                (int) $candidate['id'], $brandId, $reviewDecision,
                $reviewDecision === 'merge' ? (int) $candidate['duplicate_provider_id'] : null,
                $userId, true, (int) $candidate['category_id'],
                (string) $candidate['evidence_url'],
                trim((string) ($candidate['review_notes'] ?? '')) !== ''
                    ? (string) $candidate['review_notes']
                    : 'Processed by controlled bulk review after evidence and eligibility checks.'
            );
            ++$processed;
        }
        $result = ['processed'=>$processed,'skipped'=>count($candidateIds)-$processed];
        AuditLog::record(
            'data_source.candidates_bulk_' . $decision,
            'data_source_import_candidate',
            'bulk',
            null,
            json_encode($result + ['selected'=>count($candidateIds),'strong_duplicate_threshold'=>BulkReviewPolicy::STRONG_DUPLICATE_SCORE])
        );
        return $result;
    }

    /** @return array{processed:int,remaining:int} */
    public function resolveExactDuplicates(int $brandId,int $userId,int $limit=1000): array
    {
        $limit = max(1, min(2000, $limit));
        $rows = Database::select(
            'SELECT c.*,p.is_unclaimed AS target_is_unclaimed,EXISTS(SELECT 1 FROM provider_brand_listings pbl WHERE pbl.provider_id=p.id AND pbl.brand_id=c.brand_id AND pbl.deleted_at IS NULL) AS target_has_brand_listing FROM data_source_import_candidates c '
            . 'JOIN providers p ON p.id=c.duplicate_provider_id AND p.deleted_at IS NULL '
            . "WHERE c.brand_id=? AND c.review_status IN ('pending','held') AND c.duplicate_score>=? "
            . 'ORDER BY c.id LIMIT ' . $limit,
            [$brandId, BulkReviewPolicy::STRONG_DUPLICATE_SCORE]
        );
        $policy = new BulkReviewPolicy();
        $processed = 0;
        foreach ($rows as $candidate) {
            if ($policy->automaticLinkProblems($candidate) !== []) {
                continue;
            }
            $count = Database::affecting(
                "UPDATE data_source_import_candidates SET review_status='merged',provider_id=?,reviewed_by=?,reviewed_at=NOW(),"
                . "review_notes=CONCAT_WS(' ',NULLIF(review_notes,''),'Automatically linked as a strong 70%+ duplicate; no candidate fields were copied to the provider.'),updated_at=NOW() "
                . "WHERE id=? AND brand_id=? AND review_status IN ('pending','held') AND duplicate_provider_id=? AND duplicate_score>=? "
                . "AND EXISTS(SELECT 1 FROM providers p JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=data_source_import_candidates.brand_id AND pbl.deleted_at IS NULL WHERE p.id=data_source_import_candidates.duplicate_provider_id AND p.deleted_at IS NULL AND p.is_unclaimed=1) "
                . "AND (JSON_CONTAINS(duplicate_reasons_json,'\"same normalised name\"') OR JSON_CONTAINS(duplicate_reasons_json,'\"similar business name\"')) "
                . "AND (JSON_CONTAINS(duplicate_reasons_json,'\"same phone\"') OR JSON_CONTAINS(duplicate_reasons_json,'\"same website\"'))",
                [(int)$candidate['duplicate_provider_id'],$userId,(int)$candidate['id'],$brandId,(int)$candidate['duplicate_provider_id'],BulkReviewPolicy::STRONG_DUPLICATE_SCORE]
            );
            if ($count > 0) {
                ++$processed;
                AuditLog::record('data_source.candidate_exact_duplicate_linked','provider',(string)$candidate['duplicate_provider_id'],null,json_encode(['candidate_id'=>(int)$candidate['id'],'score'=>(int)$candidate['duplicate_score']]));
            }
        }
        $remaining = (int)Database::scalar(
            "SELECT COUNT(*) FROM data_source_import_candidates c JOIN providers p ON p.id=c.duplicate_provider_id AND p.deleted_at IS NULL AND p.is_unclaimed=1 "
            . "JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=c.brand_id AND pbl.deleted_at IS NULL "
            . "WHERE c.brand_id=? AND c.review_status IN ('pending','held') AND c.duplicate_score>=? "
            . "AND (JSON_CONTAINS(c.duplicate_reasons_json,'\"same normalised name\"') OR JSON_CONTAINS(c.duplicate_reasons_json,'\"similar business name\"')) "
            . "AND (JSON_CONTAINS(c.duplicate_reasons_json,'\"same phone\"') OR JSON_CONTAINS(c.duplicate_reasons_json,'\"same website\"'))",
            [$brandId,BulkReviewPolicy::STRONG_DUPLICATE_SCORE]
        );
        AuditLog::record('data_source.exact_duplicates_auto_resolved','data_source_import_candidate','bulk',null,json_encode(['processed'=>$processed,'remaining'=>$remaining]));
        return ['processed'=>$processed,'remaining'=>$remaining];
    }

    private function attachProvider(int $providerId,array $candidate,int $userId): void
    {
        $location = $this->candidateLocation($candidate);
        Database::query(
            'UPDATE providers SET base_town_id=COALESCE(base_town_id,?),region_id=COALESCE(region_id,?),street_address=COALESCE(street_address,?),latitude=COALESCE(latitude,?),longitude=COALESCE(longitude,?),updated_at=NOW() WHERE id=? AND deleted_at IS NULL',
            [$location['town_id'],$location['region_id'],$candidate['formatted_address'] ?: null,$candidate['latitude'] ?: null,$candidate['longitude'] ?: null,$providerId]
        );
        $slug = $this->uniqueBrandSlug((int)$candidate['brand_id'],(string)$candidate['business_name'],$providerId);
        Database::query("INSERT IGNORE INTO provider_brand_listings (brand_id,provider_id,slug,display_name,status,is_featured,is_verified,search_visible,created_at,updated_at) VALUES (?,?,?,?,'active',0,0,1,NOW(),NOW())",[$candidate['brand_id'],$providerId,$slug,$candidate['business_name']]);
        $listingId=(int)Database::scalar('SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',[$candidate['brand_id'],$providerId]);
        if (!empty($candidate['category_id'])) { Database::query("INSERT IGNORE INTO provider_brand_category_assignments (listing_id,category_id,assignment_source,confidence,is_verified,created_at) VALUES (?,?,'import',?,0,NOW())",[$listingId,$candidate['category_id'],$candidate['confidence']]); }
        $serviceCategoryId = $this->publicServiceCategoryId($candidate);
        Database::query("INSERT IGNORE INTO provider_services (provider_id,category_id,is_inferred,notes,created_at) VALUES (?,?,0,'Confirmed during independent import review',NOW())",[$providerId,$serviceCategoryId]);
        $connectorKey=(string)Database::scalar('SELECT connector_key FROM data_source_connectors WHERE id=?',[$candidate['connector_id']]);
        Database::query("INSERT IGNORE INTO provider_discovery_evidence (provider_id,brand_id,source_type,connector_key,source_reference,verification_status,discovered_at,last_checked_at,checked_by,notes) VALUES (?,?,'other',?,?,?,NOW(),NOW(),?,?)",[$providerId,$candidate['brand_id'],$connectorKey,mb_substr((string)($candidate['evidence_url'] ?: $candidate['external_id']),0,255),!empty($candidate['evidence_url'])?'admin_verified':'discovered',$userId,mb_substr((string)($candidate['review_notes'] ?: 'Discovered through connector review queue'),0,500)]);
    }

    /** @return array{town_id:?int,region_id:?int} */
    private function candidateLocation(array $candidate): array
    {
        if (!is_numeric($candidate['latitude'] ?? null) || !is_numeric($candidate['longitude'] ?? null)) {
            return ['town_id'=>null,'region_id'=>null];
        }
        $town = Town::nearestActive((float)$candidate['latitude'], (float)$candidate['longitude']);
        if ($town === null || (string)($candidate['candidate_state'] ?? '') !== (string)($town['state_abbr'] ?? '')) {
            return ['town_id'=>null,'region_id'=>null];
        }
        return ['town_id'=>(int)$town['id'],'region_id'=>(int)($town['region_id'] ?? 0) ?: null];
    }

    private function publicServiceCategoryId(array $candidate): int
    {
        $key = (string)Database::scalar('SELECT category_key FROM brand_provider_categories WHERE id=? AND brand_id=? AND is_active=1',[$candidate['category_id'] ?? 0,$candidate['brand_id']]);
        $slug = [
            'caravan-rv-repairs'=>'general-caravan-repairs',
            'auto-electrical'=>'auto-electrical-and-batteries',
            'tyres-wheels-bearings'=>'tyres-and-wheels',
            'roadside-recovery'=>'roadside-assistance',
            'trailer-brakes-suspension'=>'brakes-and-bearings',
            'caravan-gas-appliances'=>'gas-appliance-servicing',
            'mobile-diesel-mechanics'=>'diesel-mechanics',
            'fuel-travel-stops'=>'fuel-and-travel-stops',
            'ev-charging'=>'ev-charging',
        ][$key] ?? '';
        $id = $slug !== '' ? (int)Database::scalar('SELECT id FROM service_categories WHERE slug=? AND is_active=1',[$slug]) : 0;
        if ($id < 1) { throw new RuntimeException('The confirmed category is not connected to the public VanAssist search. Correct the category mapping before publishing.'); }
        return $id;
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
        return Database::affecting('INSERT IGNORE INTO data_source_import_candidates (job_id,connector_id,brand_id,category_id,external_id,business_name,formatted_address,phone,website,latitude,longitude,raw_json,confidence,duplicate_provider_id,duplicate_score,duplicate_reasons_json,created_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY))',[$jobId,$connectorId,$brandId,$categoryId,$row['external_id'],$row['business_name'],$row['formatted_address']??null,$row['phone']??null,$row['website']??null,$row['latitude']??null,$row['longitude']??null,json_encode($row['raw']??$row,JSON_THROW_ON_ERROR),85,$best['score']>=BulkReviewPolicy::STRONG_DUPLICATE_SCORE?$best['id']:null,$best['score'],json_encode($best['reasons'],JSON_THROW_ON_ERROR)])>0;
    }

    private function connectorRow(int $id): array { $row=Database::selectOne('SELECT * FROM data_source_connectors WHERE id=?',[$id]);if($row===null){throw new RuntimeException('Data source connector not found.');}return $row; }
    private function uniqueSlug(string $name): string { $base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name))??'','-') ?: 'provider';$slug=$base;$i=2;while((int)Database::scalar('SELECT COUNT(*) FROM providers WHERE slug=?',[$slug])>0){$slug=$base.'-'.$i++;}return $slug; }
    private function uniqueBrandSlug(int $brandId,string $name,int $providerId): string { $existing=Database::selectOne('SELECT slug FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',[$brandId,$providerId]);if($existing){return(string)$existing['slug'];}$base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($name))??'','-')?:'provider';$slug=$base;$i=2;while((int)Database::scalar('SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id=? AND slug=?',[$brandId,$slug])>0){$slug=$base.'-'.$i++;}return$slug;}

    private function validEvidenceUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)) return false;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $host !== '' && !str_contains($host, 'google.') && !str_contains($host, 'goo.gl');
    }
}
