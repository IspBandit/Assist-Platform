<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Platform\DataSources\DuplicateMatcher;
use App\Platform\DataSources\BulkReviewPolicy;
use RuntimeException;
use Throwable;

final class NationalRouteImportService
{
    private const CONNECTOR_KEY = 'national_route_places';
    private const MAX_UPLOAD_BYTES = 25_000_000;
    private const STAGING_DIR = 'storage/imports/national-route-coverage/staged';

    public function stageUpload(array $file, int $brandId, int $userId): int
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadError($error));
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('The uploaded discovery file could not be verified.');
        }
        return $this->stageFile($tmp, $name, $size, $brandId, $userId, true);
    }

    public function stageLocalFile(string $path, int $brandId, int $userId = 0): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('National route discovery file was not found.');
        }
        return $this->stageFile($path, basename($path), (int) filesize($path), $brandId, $userId, false);
    }

    /** @return array{processed:int,inserted:int,held:int,skipped:int,auto_merged:int,done:bool,total_processed:int,job_id:int} */
    public function processJob(int $jobId, int $brandId, int $batchSize = 300): array
    {
        $this->purgeExpiredCandidates();
        $this->cleanupStaging();
        $batchSize = max(1, min(500, $batchSize));
        $job = Database::selectOne(
            'SELECT j.*,c.connector_key FROM data_source_import_jobs j '
            . 'JOIN data_source_connectors c ON c.id=j.connector_id WHERE j.id=? AND j.brand_id=?',
            [$jobId, $brandId]
        );
        if ($job === null || (string) $job['connector_key'] !== self::CONNECTOR_KEY) {
            throw new RuntimeException('National route import job was not found for this workspace.');
        }
        if (!in_array((string) $job['status'], ['queued', 'running'], true)) {
            return ['processed'=>0,'inserted'=>0,'held'=>0,'skipped'=>0,'auto_merged'=>0,'done'=>true,'total_processed'=>(int)$job['candidates_found'],'job_id'=>$jobId];
        }

        $scope = json_decode((string) $job['scope_json'], true);
        if (!is_array($scope) || empty($scope['staged_file'])) {
            throw new RuntimeException('The staged national discovery file is no longer available.');
        }
        $stagedName = basename((string) $scope['staged_file']);
        $path = BASE_PATH . '/' . self::STAGING_DIR . '/' . $stagedName;
        if (!is_file($path)) {
            throw new RuntimeException('The staged national discovery file is missing. Upload it again.');
        }

        $processedBefore = max(0, (int) ($scope['processed_lines'] ?? 0));
        $handle = $this->open($path, !empty($scope['compressed']));
        try {
            for ($i = 0; $i < $processedBefore; $i++) {
                if ($this->readLine($handle, !empty($scope['compressed'])) === false) {
                    break;
                }
            }

            $categories = $this->categoryMap($brandId);
            $providerIndex = $this->providerIndex($brandId);
            $classifier = new NationalRouteCandidateClassifier();
            $processed = 0;
            $inserted = 0;
            $held = 0;
            $skipped = 0;
            $autoMerged = 0;
            $reachedEnd = false;
            $errors = array_values(array_filter((array)($scope['errors'] ?? []), 'is_array'));

            while ($processed < $batchSize) {
                $line = $this->readLine($handle, !empty($scope['compressed']));
                if ($line === false) {
                    $reachedEnd = true;
                    break;
                }
                $processed++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                try {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($row)) {
                        $skipped++;
                        continue;
                    }
                    $result = $this->storeCandidate(
                        $jobId,
                        (int) $job['connector_id'],
                        $brandId,
                        $row,
                        $classifier,
                        $categories,
                        $providerIndex
                    );
                    if ($result === 'inserted') {
                        $inserted++;
                    } elseif ($result === 'held') {
                        $inserted++;
                        $held++;
                    } elseif ($result === 'merged') {
                        $inserted++;
                        $autoMerged++;
                    } else {
                        $skipped++;
                    }
                } catch (Throwable $exception) {
                    $skipped++;
                    if (count($errors) < 20) {
                        $errors[] = ['line'=>$processedBefore+$processed,'error'=>mb_substr($exception->getMessage(),0,300)];
                    }
                }
            }
        } finally {
            $this->close($handle, !empty($scope['compressed']));
        }

        $totalProcessed = $processedBefore + $processed;
        $scope['processed_lines'] = $totalProcessed;
        $scope['skipped_lines'] = (int)($scope['skipped_lines'] ?? 0) + $skipped;
        $scope['auto_merged'] = (int)($scope['auto_merged'] ?? 0) + $autoMerged;
        $scope['errors'] = $errors;
        $done = $reachedEnd || $processed < $batchSize;
        Database::query(
            'UPDATE data_source_import_jobs SET status=?,scope_json=?,candidates_found=?,candidates_new=candidates_new+?,error_message=?,completed_at=? WHERE id=?',
            [
                $done ? 'review' : 'running',
                json_encode($scope, JSON_THROW_ON_ERROR),
                $totalProcessed,
                $inserted,
                $scope['skipped_lines'] > 0 ? number_format((int)$scope['skipped_lines']) . ' rows require attention; inspect the recorded import errors.' : null,
                $done ? gmdate('Y-m-d H:i:s') : null,
                $jobId,
            ]
        );
        if ($done) {
            @unlink($path);
            AuditLog::record('data_source.national_route_staged', 'data_source_import_job', (string) $jobId, null, json_encode([
                'processed' => $totalProcessed,
                'queued' => (int) Database::scalar('SELECT candidates_new FROM data_source_import_jobs WHERE id=?', [$jobId]),
            ]));
        }

        return [
            'processed'=>$processed, 'inserted'=>$inserted, 'held'=>$held,
            'skipped'=>$skipped, 'auto_merged'=>$autoMerged, 'done'=>$done, 'total_processed'=>$totalProcessed,
            'job_id'=>$jobId,
        ];
    }

    /** @return array<string,mixed>|null */
    public function jobStatus(int $jobId, int $brandId): ?array
    {
        return Database::selectOne(
            'SELECT id,status,candidates_found,candidates_new,scope_json,error_message,created_at,completed_at '
            . 'FROM data_source_import_jobs WHERE id=? AND brand_id=?',
            [$jobId, $brandId]
        );
    }

    private function stageFile(string $source, string $originalName, int $size, int $brandId, int $userId, bool $uploaded): int
    {
        $this->purgeExpiredCandidates();
        $this->cleanupStaging();
        if ($size < 1 || $size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Upload a non-empty JSONL or JSONL.GZ file no larger than 25 MB.');
        }
        $lower = strtolower($originalName);
        $compressed = str_ends_with($lower, '.gz');
        if (!$compressed && !str_ends_with($lower, '.jsonl')) {
            throw new RuntimeException('The discovery file must use .jsonl or .jsonl.gz.');
        }
        $connectorId = (int) Database::scalar('SELECT id FROM data_source_connectors WHERE connector_key=?', [self::CONNECTOR_KEY]);
        if ($connectorId < 1) {
            throw new RuntimeException('Run the latest database migrations before staging national route candidates.');
        }
        $dir = BASE_PATH . '/' . self::STAGING_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('The private import staging directory could not be created.');
        }
        $stagedName = bin2hex(random_bytes(16)) . ($compressed ? '.jsonl.gz' : '.jsonl');
        $destination = $dir . '/' . $stagedName;
        $moved = $uploaded ? move_uploaded_file($source, $destination) : copy($source, $destination);
        if (!$moved) {
            throw new RuntimeException('The discovery file could not be moved into private staging.');
        }

        $scope = [
            'source' => 'national-caravan-route-places',
            'original_name' => mb_substr($originalName, 0, 190),
            'staged_file' => $stagedName,
            'compressed' => $compressed,
            'processed_lines' => 0,
            'publication_status' => 'review_only',
            'evidence_required' => true,
        ];
        try {
            return Database::insert(
                "INSERT INTO data_source_import_jobs (connector_id,brand_id,mapping_id,status,scope_json,requested_by,started_at,created_at) VALUES (?,?,NULL,'queued',?,?,NOW(),NOW())",
                [$connectorId, $brandId, json_encode($scope, JSON_THROW_ON_ERROR), $userId > 0 ? $userId : null]
            );
        } catch (Throwable $exception) {
            @unlink($destination);
            throw $exception;
        }
    }

    /**
     * @param array<string,int> $categories
     * @param array{name:array<string,list<array<string,mixed>>>,phone:array<string,list<array<string,mixed>>>,host:array<string,list<array<string,mixed>>>,id:array<int,array<string,mixed>>} $providerIndex
     */
    private function storeCandidate(int $jobId, int $connectorId, int $brandId, array $row, NationalRouteCandidateClassifier $classifier, array $categories, array $providerIndex): string
    {
        $externalId = trim((string) ($row['external_id'] ?? ''));
        $name = trim((string) ($row['business_name'] ?? ''));
        if ($externalId === '' || $name === '' || !str_starts_with($externalId, 'places:')) {
            return 'skipped';
        }
        $classification = $classifier->classify($row);
        $categoryId = (int) ($categories[$classification['category_key']] ?? 0);
        if ($categoryId < 1) {
            $classification['review_status'] = 'held';
            $classification['hold_reason'] = 'The suggested category is not configured for this workspace.';
        }
        $duplicate = $this->duplicate($row, $providerIndex);
        $raw = $row;
        $raw['classification'] = $classification;
        $inserted = Database::affecting(
            'INSERT IGNORE INTO data_source_import_candidates '
            . '(job_id,connector_id,brand_id,category_id,external_id,business_name,formatted_address,phone,website,latitude,longitude,candidate_state,route_hub,raw_json,evidence_status,review_notes,hold_reason,confidence,review_status,duplicate_provider_id,duplicate_score,duplicate_reasons_json,created_at,expires_at) '
            . "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'required',NULL,?,?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY))",
            [
                $jobId, $connectorId, $brandId, $categoryId ?: null, $externalId, mb_substr($name, 0, 190),
                $row['formatted_address'] ?? null, $row['phone'] ?? null, $row['website'] ?? null,
                $row['latitude'] ?? null, $row['longitude'] ?? null, $classification['state'] ?: null,
                $classification['route_hub'] ?: null, json_encode($raw, JSON_THROW_ON_ERROR),
                $classification['hold_reason'], $classification['confidence'], $classification['review_status'],
                $duplicate['score'] >= 60 ? $duplicate['id'] : null, $duplicate['score'],
                json_encode($duplicate['reasons'], JSON_THROW_ON_ERROR),
            ]
        );
        if ($inserted < 1) {
            return 'skipped';
        }
        $candidateId = (int)Database::scalar(
            'SELECT id FROM data_source_import_candidates WHERE connector_id=? AND external_id=?',
            [$connectorId,$externalId]
        );
        $target = $duplicate['id'] !== null ? ($providerIndex['id'][(int)$duplicate['id']] ?? null) : null;
        if ($candidateId > 0 && is_array($target)) {
            $identity = [
                'duplicate_provider_id'=>$duplicate['id'],
                'duplicate_score'=>$duplicate['score'],
                'duplicate_reasons_json'=>json_encode($duplicate['reasons'], JSON_THROW_ON_ERROR),
                'target_is_unclaimed'=>$target['is_unclaimed'] ?? 0,
                'target_has_brand_listing'=>$target['has_brand_listing'] ?? 0,
            ];
            if ((new BulkReviewPolicy())->automaticLinkProblems($identity) === []) {
                Database::query(
                    "UPDATE data_source_import_candidates SET review_status='merged',provider_id=?,reviewed_at=NOW(),"
                    . "review_notes='Automatically linked as an exact duplicate; no candidate fields were copied to the provider.' WHERE id=?",
                    [(int)$duplicate['id'],$candidateId]
                );
                return 'merged';
            }
        }
        return $classification['review_status'] === 'held' ? 'held' : 'inserted';
    }

    /** @return array<string,int> */
    private function categoryMap(int $brandId): array
    {
        $rows = Database::select('SELECT id,category_key FROM brand_provider_categories WHERE brand_id=? AND is_active=1', [$brandId]);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['category_key']] = (int) $row['id'];
        }
        return $map;
    }

    /** @return array{name:array<string,list<array<string,mixed>>>,phone:array<string,list<array<string,mixed>>>,host:array<string,list<array<string,mixed>>>,id:array<int,array<string,mixed>>} */
    private function providerIndex(int $brandId): array
    {
        $index = ['name'=>[], 'phone'=>[], 'host'=>[], 'id'=>[]];
        foreach (Database::select(
            'SELECT p.id,p.business_name,p.phone,p.website,p.is_unclaimed,EXISTS(SELECT 1 FROM provider_brand_listings pbl WHERE pbl.provider_id=p.id AND pbl.brand_id=? AND pbl.deleted_at IS NULL) AS has_brand_listing FROM providers p WHERE p.deleted_at IS NULL',
            [$brandId]
        ) as $provider) {
            $index['id'][(int)$provider['id']] = $provider;
            $name = $this->normal((string) $provider['business_name']);
            $phone = $this->normal((string) ($provider['phone'] ?? ''));
            $host = $this->host((string) ($provider['website'] ?? ''));
            if ($name !== '') $index['name'][$name][] = $provider;
            if ($phone !== '') $index['phone'][$phone][] = $provider;
            if ($host !== '') $index['host'][$host][] = $provider;
        }
        return $index;
    }

    /**
     * @param array{name:array<string,list<array<string,mixed>>>,phone:array<string,list<array<string,mixed>>>,host:array<string,list<array<string,mixed>>>,id:array<int,array<string,mixed>>} $index
     * @return array{score:int,reasons:array<int,string>,id:?int}
     */
    private function duplicate(array $row, array $index): array
    {
        $matches = [];
        $name = $this->normal((string) ($row['business_name'] ?? ''));
        $phone = $this->normal((string) ($row['phone'] ?? ''));
        $host = $this->host((string) ($row['website'] ?? ''));
        foreach ([['name',$name],['phone',$phone],['host',$host]] as [$kind,$value]) {
            if ($value !== '' && isset($index[$kind][$value])) {
                $providers = $index[$kind][$value];
                if ($kind === 'host' && count($providers) > 1) continue;
                foreach ($providers as $provider) $matches[(int)$provider['id']] = $provider;
            }
        }
        $best = ['score'=>0,'reasons'=>[],'id'=>null];
        $matcher = new DuplicateMatcher();
        foreach ($matches as $provider) {
            $match = $matcher->score($row, $provider);
            if ($match['score'] > $best['score']) {
                $best = $match + ['id'=>(int)$provider['id']];
            }
        }
        return $best;
    }

    public function purgeExpiredCandidates(): int
    {
        return Database::affecting(
            'DELETE c FROM data_source_import_candidates c JOIN data_source_connectors d ON d.id=c.connector_id WHERE d.connector_key=? AND c.expires_at<NOW()',
            [self::CONNECTOR_KEY]
        );
    }

    private function cleanupStaging(): void
    {
        $dir = BASE_PATH . '/' . self::STAGING_DIR;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.jsonl*') ?: [] as $file) {
                if (is_file($file) && (int)filemtime($file) < time() - 172800) @unlink($file);
            }
        }
        $connectorId = (int)Database::scalar('SELECT id FROM data_source_connectors WHERE connector_key=?',[self::CONNECTOR_KEY]);
        if ($connectorId > 0) {
            Database::query("UPDATE data_source_import_jobs SET status='failed',error_message='Staged discovery file expired before screening completed.',completed_at=NOW() WHERE connector_id=? AND status IN ('queued','running') AND created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)",[$connectorId]);
        }
    }

    private function normal(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }

    private function host(string $url): string
    {
        return strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)) ?? '');
    }

    /** @return resource */
    private function open(string $path, bool $compressed)
    {
        $handle = $compressed ? gzopen($path, 'rb') : fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('The staged discovery file could not be opened.');
        return $handle;
    }

    /** @param resource $handle */
    private function readLine($handle, bool $compressed): string|false
    {
        return $compressed ? gzgets($handle) : fgets($handle);
    }

    /** @param resource $handle */
    private function close($handle, bool $compressed): void
    {
        if ($compressed) gzclose($handle); else fclose($handle);
    }

    private function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The upload is larger than the server allows. Use the compressed .jsonl.gz file.',
            UPLOAD_ERR_NO_FILE => 'Choose the national route JSONL or JSONL.GZ file first.',
            default => 'The discovery file upload failed. Please try again.',
        };
    }
}
