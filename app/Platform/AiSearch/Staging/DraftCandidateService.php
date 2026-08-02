<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Staging;

use App\Core\Database;
use App\Platform\DataSources\BulkReviewPolicy;
use App\Platform\DataSources\DuplicateMatcher;
use App\Services\AuditLog;
use RuntimeException;
use Throwable;

/**
 * Stages identifiable external hits into DATA-006 import candidates.
 * Runs DuplicateMatcher before insert; never auto-publishes (ADR 0026).
 */
final class DraftCandidateService
{
    /**
     * @param list<array<string,mixed>> $hits  Rows with external_id, business_name, optional address/geo/phone/website
     * @return array{staged:int,skipped:int,job_id:?int}
     */
    public function stageHits(
        int $brandId,
        string $connectorKey,
        array $hits,
        string $trustPolicy = DatasetTrustPolicy::TRUSTED_REVIEW,
        ?int $categoryId = null,
        ?int $userId = null,
    ): array {
        if (!DatasetTrustPolicy::mayStage($trustPolicy)) {
            throw new RuntimeException('Trust policy does not allow staging: ' . $trustPolicy);
        }
        if (DatasetTrustPolicy::isAskBlockedConnector($connectorKey)) {
            throw new RuntimeException('Connector is blocked from Ask VanAssist staging: ' . $connectorKey);
        }
        if ($hits === []) {
            return ['staged' => 0, 'skipped' => 0, 'job_id' => null];
        }

        $connector = Database::selectOne(
            'SELECT id, connector_key, status FROM data_source_connectors WHERE connector_key = ? LIMIT 1',
            [$connectorKey]
        );
        if ($connector === null) {
            throw new RuntimeException('Unknown connector: ' . $connectorKey);
        }

        $jobId = Database::insert(
            'INSERT INTO data_source_import_jobs
                (connector_id, brand_id, mapping_id, status, scope_json, requested_by, started_at, completed_at, created_at)
             VALUES (?, ?, NULL, \'review\', ?, ?, NOW(), NOW(), NOW())',
            [
                (int) $connector['id'],
                $brandId,
                json_encode(['channel' => 'assist_ai_datasets', 'trust_policy' => $trustPolicy], JSON_THROW_ON_ERROR),
                $userId,
            ]
        );

        $staged = 0;
        $skipped = 0;
        foreach ($hits as $hit) {
            $externalId = trim((string) ($hit['external_id'] ?? ''));
            $name = trim((string) ($hit['business_name'] ?? ''));
            if ($externalId === '' || $name === '') {
                $skipped++;
                continue;
            }
            try {
                $dup = $this->bestDuplicate([
                    'business_name' => $name,
                    'phone' => (string) ($hit['phone'] ?? ''),
                    'website' => (string) ($hit['website'] ?? ''),
                ]);
                $affected = Database::affecting(
                    'INSERT IGNORE INTO data_source_import_candidates
                        (job_id, connector_id, brand_id, category_id, external_id, business_name, formatted_address,
                         phone, website, latitude, longitude, raw_json, confidence,
                         duplicate_provider_id, duplicate_score, duplicate_reasons_json,
                         created_at, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))',
                    [
                        $jobId,
                        (int) $connector['id'],
                        $brandId,
                        $categoryId,
                        mb_substr($externalId, 0, 255),
                        mb_substr($name, 0, 190),
                        isset($hit['formatted_address']) ? mb_substr((string) $hit['formatted_address'], 0, 500) : null,
                        isset($hit['phone']) ? mb_substr((string) $hit['phone'], 0, 40) : null,
                        isset($hit['website']) ? mb_substr((string) $hit['website'], 0, 500) : null,
                        is_numeric($hit['latitude'] ?? null) ? (float) $hit['latitude'] : null,
                        is_numeric($hit['longitude'] ?? null) ? (float) $hit['longitude'] : null,
                        json_encode($hit['raw'] ?? $hit, JSON_THROW_ON_ERROR),
                        max(0, min(100, (int) ($hit['confidence'] ?? 60))),
                        $dup['provider_id'],
                        $dup['score'],
                        json_encode($dup['reasons'], JSON_THROW_ON_ERROR),
                    ]
                );
                if ($affected > 0) {
                    $staged++;
                } else {
                    $skipped++;
                }
            } catch (Throwable) {
                $skipped++;
            }
        }

        AuditLog::record(
            'ai.dataset.staged',
            'data_source_import_job',
            (string) $jobId,
            null,
            json_encode(['staged' => $staged, 'skipped' => $skipped, 'connector' => $connectorKey], JSON_THROW_ON_ERROR)
        );

        return ['staged' => $staged, 'skipped' => $skipped, 'job_id' => $jobId];
    }

    /**
     * @param array{business_name:string,phone:string,website:string} $candidate
     * @return array{provider_id:?int,score:int,reasons:list<string>}
     */
    private function bestDuplicate(array $candidate): array
    {
        $best = ['provider_id' => null, 'score' => 0, 'reasons' => []];
        try {
            $where = ['business_name LIKE ?'];
            $params = ['%' . $candidate['business_name'] . '%'];
            if (trim($candidate['phone']) !== '') {
                $where[] = 'phone = ?';
                $params[] = $candidate['phone'];
            }
            if (trim($candidate['website']) !== '') {
                $where[] = 'website = ?';
                $params[] = $candidate['website'];
            }
            $providers = Database::select(
                'SELECT id, business_name, phone, website FROM providers
                 WHERE deleted_at IS NULL AND (' . implode(' OR ', $where) . ') LIMIT 30',
                $params
            );
            $matcher = new DuplicateMatcher();
            foreach ($providers as $provider) {
                $match = $matcher->score($candidate, $provider);
                if ($match['score'] > $best['score']) {
                    $best = [
                        'provider_id' => $match['score'] >= BulkReviewPolicy::STRONG_DUPLICATE_SCORE
                            ? (int) $provider['id']
                            : null,
                        'score' => $match['score'],
                        'reasons' => $match['reasons'],
                    ];
                }
            }
        } catch (Throwable) {
            // Staging continues without duplicate link on lookup failure.
        }
        return $best;
    }
}
