<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Town;
use App\Platform\AiSearch\Staging\DatasetTrustPolicy;
use App\Platform\DataSources\ConnectorRegistry;
use App\Platform\DataSources\Connectors\ArcGisFeatureConnector;
use App\Platform\DataSources\Connectors\CkanDatasetConnector;
use App\Platform\DataSources\Connectors\CsvDatasetConnector;
use App\Platform\DataSources\Connectors\GeoJsonDatasetConnector;
use App\Platform\DataSources\FacilityTypeMapper;
use App\Services\AuditLog;
use App\Services\Api\FacilityImportCandidateReviewGateway;
use RuntimeException;
use Throwable;

/**
 * DATA-012 government dataset catalogue and review-first facility ingest.
 */
final class GovernmentDatasetService implements FacilityImportCandidateReviewGateway
{
    private ConnectorRegistry $registry;

    public function __construct(?ConnectorRegistry $registry = null)
    {
        $this->registry = $registry ?? new ConnectorRegistry();
        $this->registry->register(new CkanDatasetConnector());
        $this->registry->register(new ArcGisFeatureConnector());
        $this->registry->register(new CsvDatasetConnector());
        $this->registry->register(new GeoJsonDatasetConnector());
    }

    /** @return list<array<string,mixed>> */
    public function listDatasets(): array
    {
        return Database::select('SELECT * FROM government_datasets ORDER BY publisher ASC, title ASC');
    }

    /** @return array<string,mixed>|null */
    public function findDataset(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM government_datasets WHERE id = ? LIMIT 1', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findDatasetByKey(string $datasetKey): ?array
    {
        $datasetKey = strtolower(trim($datasetKey));
        if ($datasetKey === '') {
            return null;
        }

        return Database::selectOne(
            'SELECT * FROM government_datasets WHERE dataset_key = ? LIMIT 1',
            [$datasetKey]
        );
    }

    /**
     * Stage Assist RIC facility package rows into the review-first candidate queue.
     *
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $meta
     * @return array{job_id:int,found:int,new:int}
     */
    public function ingestAssistRicRows(
        int $datasetId,
        array $rows,
        ?int $brandId,
        ?int $userId,
        array $meta = []
    ): array {
        $dataset = $this->findDataset($datasetId);
        if ($dataset === null) {
            throw new RuntimeException('Dataset not found.');
        }

        $jobId = Database::insert(
            'INSERT INTO traveller_facility_import_jobs
                (dataset_id, connector_key, brand_id, status, scope_json, requested_by, started_at, created_at)
             VALUES (?, ?, ?, \'running\', ?, ?, NOW(), NOW())',
            [
                $datasetId,
                'assist_ric_package',
                $brandId,
                json_encode(
                    [
                        'dataset_key' => $dataset['dataset_key'],
                        'source_system' => 'assist-ric',
                        'meta' => $meta,
                    ],
                    JSON_THROW_ON_ERROR
                ),
                $userId,
            ]
        );

        try {
            $created = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $mapped = $this->mapAssistRicRow($dataset, $row);
                if ($this->stageCandidate($jobId, $dataset, $brandId, $mapped)) {
                    $created++;
                }
            }
            Database::affecting(
                'UPDATE traveller_facility_import_jobs SET status = \'review\', candidates_found = ?, candidates_new = ?, completed_at = NOW() WHERE id = ?',
                [count($rows), $created, $jobId]
            );
            Database::affecting(
                'UPDATE government_datasets SET last_checked_at = NOW(), last_imported_at = NOW(), last_error = NULL, updated_at = NOW() WHERE id = ?',
                [$datasetId]
            );
            AuditLog::record(
                'gov_dataset.ric_package',
                'traveller_facility_import_job',
                (string) $jobId,
                null,
                json_encode(['found' => count($rows), 'new' => $created], JSON_THROW_ON_ERROR)
            );

            return ['job_id' => $jobId, 'found' => count($rows), 'new' => $created];
        } catch (Throwable $e) {
            Database::affecting(
                'UPDATE traveller_facility_import_jobs SET status = \'failed\', error_message = ?, completed_at = NOW() WHERE id = ?',
                [mb_substr($e->getMessage(), 0, 1000), $jobId]
            );
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $dataset
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function mapAssistRicRow(array $dataset, array $row): array
    {
        $externalId = trim((string) (
            $row['external_id']
            ?? $row['source_record_id']
            ?? $row['idempotency_key']
            ?? ''
        ));
        $name = trim((string) ($row['name'] ?? $row['business_name'] ?? $row['canonical_name'] ?? ''));
        $address = trim((string) (
            $row['formatted_address']
            ?? $row['address']
            ?? $row['street_address']
            ?? ''
        ));

        return [
            'external_id' => $externalId,
            'name' => $name,
            'facility_type' => (string) (
                $row['facility_type']
                ?? $row['entity_type']
                ?? $dataset['default_facility_type']
                ?? 'other_essential'
            ),
            'formatted_address' => $address !== '' ? $address : null,
            'locality' => isset($row['locality']) ? (string) $row['locality'] : null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'source_url' => $row['source_url'] ?? null,
            'licence' => $row['licence'] ?? null,
            'attribution' => $row['attribution'] ?? null,
            'confidence' => $row['confidence'] ?? 70,
            'raw' => $row['raw'] ?? $row['normalised'] ?? $row,
        ];
    }

    public function saveDataset(int $id, bool $enabled, ?int $userId = null): void
    {
        $dataset = $this->findDataset($id);
        if ($dataset === null) {
            throw new RuntimeException('Dataset not found.');
        }
        if ((string) $dataset['trust_policy'] === DatasetTrustPolicy::PROHIBITED && $enabled) {
            throw new RuntimeException('Prohibited datasets cannot be enabled.');
        }
        if ((string) $dataset['trust_policy'] === DatasetTrustPolicy::TRUSTED_AUTOMATIC && $enabled) {
            throw new RuntimeException('trusted_automatic requires a recorded owner decision and is not enabled via this UI.');
        }
        Database::affecting(
            'UPDATE government_datasets SET is_enabled = ?, updated_at = NOW() WHERE id = ?',
            [$enabled ? 1 : 0, $id]
        );
        AuditLog::record(
            'gov_dataset.updated',
            'government_dataset',
            (string) $id,
            json_encode(['is_enabled' => (int) $dataset['is_enabled']], JSON_THROW_ON_ERROR),
            json_encode(['is_enabled' => $enabled ? 1 : 0, 'by' => $userId], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Create or update a catalogue row (metadata + field mapping). Does not fetch.
     *
     * @param array<string,mixed> $input
     */
    public function upsertCatalogue(array $input, ?int $userId = null): int
    {
        $id = (int) ($input['id'] ?? 0);
        $key = strtolower(trim((string) ($input['dataset_key'] ?? '')));
        $key = preg_replace('/[^a-z0-9_\-]+/', '_', $key) ?? '';
        $publisher = trim((string) ($input['publisher'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $connectorKey = trim((string) ($input['connector_key'] ?? ''));
        $fetchMethod = trim((string) ($input['fetch_method'] ?? ''));
        $trust = trim((string) ($input['trust_policy'] ?? DatasetTrustPolicy::TRUSTED_REVIEW));
        $facilityType = FacilityTypeMapper::normalise((string) ($input['default_facility_type'] ?? 'other_essential'));

        if ($key === '' || $publisher === '' || $title === '') {
            throw new RuntimeException('Dataset key, publisher and title are required.');
        }
        if (!in_array($connectorKey, ['gov_ckan', 'gov_arcgis', 'gov_csv', 'gov_geojson', 'osm_offline_seed'], true)) {
            throw new RuntimeException('Unsupported connector key.');
        }
        if (!in_array($fetchMethod, ['ckan', 'arcgis', 'csv', 'geojson', 'url'], true)) {
            throw new RuntimeException('Unsupported fetch method.');
        }
        if (!DatasetTrustPolicy::isKnown($trust)) {
            throw new RuntimeException('Unsupported trust policy.');
        }
        if ($trust === DatasetTrustPolicy::TRUSTED_AUTOMATIC) {
            throw new RuntimeException('trusted_automatic cannot be set from this UI.');
        }
        if ($trust === DatasetTrustPolicy::PROHIBITED && !empty($input['is_enabled'])) {
            throw new RuntimeException('Prohibited datasets cannot be enabled.');
        }

        $settings = [];
        if (!empty($input['settings_json']) && is_string($input['settings_json'])) {
            $decoded = json_decode($input['settings_json'], true);
            if (!is_array($decoded)) {
                throw new RuntimeException('settings_json must be valid JSON object.');
            }
            $settings = $decoded;
        }
        foreach (['package_api_url', 'resource_id', 'resource_url', 'feature_url', 'name_field', 'id_field', 'lat_field', 'lng_field', 'address_field', 'type_field', 'filter_field', 'filter_value', 'format'] as $field) {
            if (isset($input[$field]) && trim((string) $input[$field]) !== '') {
                $settings[$field] = trim((string) $input[$field]);
            }
        }
        if (isset($input['limit']) && is_numeric($input['limit'])) {
            $settings['limit'] = max(1, min(25000, (int) $input['limit']));
        }
        $settings['default_facility_type'] = $facilityType;

        $recordTypes = array_values(array_filter(array_map(
            static fn (string $v): string => trim($v),
            preg_split('/\s*,\s*/', trim((string) ($input['record_types'] ?? $facilityType))) ?: []
        )));
        $recordTypes = array_values(array_filter(array_map(
            static function (string $v) use ($facilityType): string {
                if (in_array($v, ['portal', 'osm', 'council', 'stay', 'caravan_park', 'campground'], true)) {
                    return $v;
                }

                return FacilityTypeMapper::normalise($v !== '' ? $v : $facilityType);
            },
            $recordTypes
        )));

        $catalogueStatus = trim((string) ($input['catalogue_status'] ?? 'planned'));
        if (!in_array($catalogueStatus, ['planned', 'indexed', 'active', 'paused', 'retired'], true)) {
            throw new RuntimeException('Unsupported catalogue status.');
        }

        $duplicateRules = null;
        if (!empty($input['duplicate_rules_json']) && is_string($input['duplicate_rules_json'])) {
            $decodedRules = json_decode($input['duplicate_rules_json'], true);
            if (!is_array($decodedRules)) {
                throw new RuntimeException('duplicate_rules_json must be valid JSON object.');
            }
            $duplicateRules = json_encode($decodedRules, JSON_THROW_ON_ERROR);
        } elseif (isset($input['duplicate_rules']) && is_array($input['duplicate_rules'])) {
            $duplicateRules = json_encode($input['duplicate_rules'], JSON_THROW_ON_ERROR);
        }

        $params = [
            $key,
            $publisher,
            $title,
            trim((string) ($input['coverage'] ?? '')) ?: null,
            trim((string) ($input['jurisdiction'] ?? '')) ?: null,
            json_encode($recordTypes === [] ? [$facilityType] : $recordTypes, JSON_THROW_ON_ERROR),
            $duplicateRules,
            trim((string) ($input['licence'] ?? '')) ?: null,
            trim((string) ($input['attribution'] ?? '')) ?: null,
            $trust,
            $fetchMethod,
            trim((string) ($input['source_format'] ?? $input['format'] ?? '')) ?: null,
            trim((string) ($input['update_frequency'] ?? '')) ?: null,
            $connectorKey,
            trim((string) ($input['endpoint_url'] ?? '')) ?: null,
            trim((string) ($input['source_url'] ?? '')) ?: null,
            json_encode($settings, JSON_THROW_ON_ERROR),
            $facilityType,
            !empty($input['is_enabled']) ? 1 : 0,
            !empty($input['auto_update_enabled']) ? 1 : 0,
            $catalogueStatus,
            trim((string) ($input['notes'] ?? '')) ?: null,
        ];

        if ($id > 0) {
            $existing = $this->findDataset($id);
            if ($existing === null) {
                throw new RuntimeException('Dataset not found.');
            }
            Database::affecting(
                'UPDATE government_datasets SET dataset_key = ?, publisher = ?, title = ?, coverage = ?, jurisdiction = ?,
                    record_types_json = ?, duplicate_rules_json = ?, licence = ?, attribution = ?, trust_policy = ?,
                    fetch_method = ?, source_format = ?, update_frequency = ?, connector_key = ?, endpoint_url = ?,
                    source_url = ?, settings_json = ?, default_facility_type = ?, is_enabled = ?, auto_update_enabled = ?,
                    catalogue_status = ?, notes = ?, updated_at = NOW()
                 WHERE id = ?',
                [...$params, $id]
            );
            AuditLog::record('gov_dataset.catalogue_updated', 'government_dataset', (string) $id, null, $key);
            return $id;
        }

        $newId = Database::insert(
            'INSERT INTO government_datasets
                (dataset_key, publisher, title, coverage, jurisdiction, record_types_json, duplicate_rules_json,
                 licence, attribution, trust_policy, fetch_method, source_format, update_frequency, connector_key,
                 endpoint_url, source_url, settings_json, default_facility_type, is_enabled, auto_update_enabled,
                 catalogue_status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            $params
        );
        AuditLog::record('gov_dataset.catalogue_created', 'government_dataset', (string) $newId, null, $key);
        return $newId;
    }

    /**
     * @return array{job_id:int,found:int,new:int}
     */
    public function fetchDataset(int $datasetId, ?int $brandId, ?int $userId, ?string $uploadPath = null): array
    {
        $dataset = $this->findDataset($datasetId);
        if ($dataset === null) {
            throw new RuntimeException('Dataset not found.');
        }
        if (!(int) $dataset['is_enabled']) {
            throw new RuntimeException('Dataset is disabled.');
        }
        if (!DatasetTrustPolicy::mayStage((string) $dataset['trust_policy'])) {
            throw new RuntimeException('Dataset trust policy does not allow staging.');
        }

        /** @var array<string,mixed> $settings */
        $settings = [];
        if (!empty($dataset['settings_json'])) {
            $decoded = json_decode((string) $dataset['settings_json'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        $settings['default_facility_type'] = FacilityTypeMapper::normalise(
            (string) ($dataset['default_facility_type'] ?? $settings['default_facility_type'] ?? 'other_essential')
        );
        $settings['licence'] = (string) ($dataset['licence'] ?? '');
        $settings['attribution'] = (string) ($dataset['attribution'] ?? '');
        $landing = trim((string) ($dataset['endpoint_url'] ?? ''));
        if ($landing !== '' && empty($settings['source_url'])) {
            $settings['source_url'] = $landing;
        }
        $method = (string) $dataset['fetch_method'];
        if ($method === 'ckan') {
            // Prefer package_api_url + resource_id from settings; only use endpoint as direct resource URL.
            if (
                empty($settings['resource_url'])
                && empty($settings['package_api_url'])
                && $landing !== ''
            ) {
                $settings['resource_url'] = $landing;
            }
        } elseif ($method === 'arcgis' && empty($settings['feature_url']) && $landing !== '') {
            $settings['feature_url'] = $landing;
        }

        $request = ['limit' => (int) ($settings['limit'] ?? 200)];
        if ($uploadPath !== null) {
            $request['path'] = $uploadPath;
        }

        $jobId = Database::insert(
            'INSERT INTO traveller_facility_import_jobs
                (dataset_id, connector_key, brand_id, status, scope_json, requested_by, started_at, created_at)
             VALUES (?, ?, ?, \'running\', ?, ?, NOW(), NOW())',
            [
                $datasetId,
                (string) $dataset['connector_key'],
                $brandId,
                json_encode(['dataset_key' => $dataset['dataset_key']], JSON_THROW_ON_ERROR),
                $userId,
            ]
        );

        try {
            $connector = $this->registry->get((string) $dataset['connector_key']);
            $rows = $connector->search($request, [], $settings);
            $created = 0;
            foreach ($rows as $row) {
                if ($this->stageCandidate($jobId, $dataset, $brandId, $row)) {
                    $created++;
                }
            }
            Database::affecting(
                'UPDATE traveller_facility_import_jobs SET status = \'review\', candidates_found = ?, candidates_new = ?, completed_at = NOW() WHERE id = ?',
                [count($rows), $created, $jobId]
            );
            Database::affecting(
                'UPDATE government_datasets SET last_checked_at = NOW(), last_imported_at = NOW(), last_error = NULL, updated_at = NOW() WHERE id = ?',
                [$datasetId]
            );
            AuditLog::record(
                'gov_dataset.fetched',
                'traveller_facility_import_job',
                (string) $jobId,
                null,
                json_encode(['found' => count($rows), 'new' => $created], JSON_THROW_ON_ERROR)
            );
            return ['job_id' => $jobId, 'found' => count($rows), 'new' => $created];
        } catch (Throwable $e) {
            Database::affecting(
                'UPDATE traveller_facility_import_jobs SET status = \'failed\', error_message = ?, completed_at = NOW() WHERE id = ?',
                [mb_substr($e->getMessage(), 0, 1000), $jobId]
            );
            Database::affecting(
                'UPDATE government_datasets SET last_checked_at = NOW(), last_error = ?, updated_at = NOW() WHERE id = ?',
                [mb_substr($e->getMessage(), 0, 500), $datasetId]
            );
            throw $e;
        }
    }

    /**
     * Import local demo fixtures for disabled-by-default catalogue rows.
     *
     * @return array{job_id:int,found:int,new:int}
     */
    public function importFixture(int $datasetId, ?int $brandId, ?int $userId): array
    {
        $dataset = $this->findDataset($datasetId);
        if ($dataset === null) {
            throw new RuntimeException('Dataset not found.');
        }
        $key = (string) $dataset['dataset_key'];
        $path = match ($key) {
            'demo_geojson_dump_points' => base_path('resources/datasets/demo-dump-points.geojson'),
            'demo_csv_public_toilets' => base_path('resources/datasets/demo-public-toilets.csv'),
            'demo_csv_drinking_water' => base_path('resources/datasets/demo-drinking-water.csv'),
            'demo_csv_rest_areas' => base_path('resources/datasets/demo-rest-areas.csv'),
            'demo_csv_visitor_information' => base_path('resources/datasets/demo-visitor-information.csv'),
            default => null,
        };
        if ($path === null || !is_file($path)) {
            throw new RuntimeException('No local fixture is registered for this dataset.');
        }
        // Temporarily allow fetch for fixtures even when disabled.
        Database::affecting('UPDATE government_datasets SET is_enabled = 1 WHERE id = ?', [$datasetId]);
        try {
            return $this->fetchDataset($datasetId, $brandId, $userId, $path);
        } finally {
            if (!(int) $dataset['is_enabled']) {
                Database::affecting('UPDATE government_datasets SET is_enabled = 0 WHERE id = ?', [$datasetId]);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function pendingCandidates(int $limit = 100): array
    {
        return Database::select(
            'SELECT c.*, d.title AS dataset_title, d.publisher
             FROM traveller_facility_import_candidates c
             LEFT JOIN government_datasets d ON d.id = c.dataset_id
             WHERE c.review_status = \'pending\' AND c.expires_at > NOW()
             ORDER BY c.created_at ASC
             LIMIT ' . max(1, min(300, $limit))
        );
    }

    public function reviewCandidate(int $candidateId, string $action, ?int $reviewerId = null, ?string $notes = null): void
    {
        $candidate = Database::selectOne(
            'SELECT * FROM traveller_facility_import_candidates WHERE id = ? AND review_status = \'pending\' LIMIT 1',
            [$candidateId]
        );
        if ($candidate === null) {
            throw new RuntimeException('Candidate not found or already reviewed.');
        }
        if ($action === 'reject') {
            Database::affecting(
                'UPDATE traveller_facility_import_candidates SET review_status = \'rejected\', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, updated_at = NOW() WHERE id = ?',
                [$reviewerId, $notes, $candidateId]
            );
            AuditLog::record('gov_dataset.candidate_rejected', 'traveller_facility_import_candidate', (string) $candidateId, null, $notes);
            $this->maybeCompleteJob((int) $candidate['job_id']);
            return;
        }
        if ($action !== 'approve') {
            throw new RuntimeException('Invalid review action.');
        }

        $facilityId = $this->publishCandidate($candidate);
        Database::affecting(
            'UPDATE traveller_facility_import_candidates SET review_status = \'approved\', facility_id = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, updated_at = NOW() WHERE id = ?',
            [$facilityId, $reviewerId, $notes, $candidateId]
        );
        AuditLog::record('gov_dataset.candidate_approved', 'traveller_facility_import_candidate', (string) $candidateId, null, (string) $facilityId);
        $this->maybeCompleteJob((int) $candidate['job_id']);
    }

    /**
     * @param list<int> $candidateIds
     * @return array{processed:int,errors:list<string>}
     */
    public function reviewCandidates(array $candidateIds, string $action, ?int $reviewerId = null, ?string $notes = null): array
    {
        $processed = 0;
        $errors = [];
        foreach ($candidateIds as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            try {
                $this->reviewCandidate($id, $action, $reviewerId, $notes);
                $processed++;
            } catch (Throwable $e) {
                $errors[] = '#' . $id . ': ' . $e->getMessage();
            }
        }
        return ['processed' => $processed, 'errors' => $errors];
    }

    /**
     * Stable provenance key per catalogue row (not connector class).
     * Prevents Toilet Map toilet vs dump-point rows colliding on FacilityID.
     */
    public static function catalogueSourceKey(string $datasetKey, string $connectorKey = 'gov'): string
    {
        $datasetKey = strtolower(trim($datasetKey));
        if ($datasetKey !== '') {
            return 'gov:' . $datasetKey;
        }
        $connectorKey = strtolower(trim($connectorKey));
        return $connectorKey !== '' ? 'gov:' . $connectorKey : 'gov:dataset';
    }

    /**
     * @param array<string,mixed> $dataset
     * @param array<string,mixed> $row
     */
    private function stageCandidate(int $jobId, array $dataset, ?int $brandId, array $row): bool
    {
        $externalId = trim((string) ($row['external_id'] ?? ''));
        $name = trim((string) ($row['name'] ?? $row['business_name'] ?? ''));
        if ($externalId === '' || $name === '') {
            return false;
        }
        $type = FacilityTypeMapper::normalise(
            (string) ($row['facility_type'] ?? ''),
            (string) ($dataset['default_facility_type'] ?? 'other_essential')
        );
        $sourceKey = self::catalogueSourceKey((string) $dataset['dataset_key'], (string) $dataset['connector_key']);
        // A dataset sync creates a new job each time, so the database's
        // job-scoped unique key cannot prevent the same source record being
        // queued repeatedly. Keep one live review candidate per catalogue
        // record and let a later sync stage it again only after review.
        $pending = Database::selectOne(
            'SELECT id FROM traveller_facility_import_candidates
             WHERE dataset_id = ? AND brand_id <=> ? AND external_id = ?
               AND review_status = \'pending\' AND expires_at > NOW()
             LIMIT 1',
            [(int) $dataset['id'], $brandId, $externalId]
        );
        if ($pending !== null) {
            return false;
        }
        $dup = Database::selectOne(
            'SELECT id FROM traveller_facilities WHERE source_key = ? AND source_record_id = ? AND deleted_at IS NULL LIMIT 1',
            [$sourceKey, $externalId]
        );
        $affected = Database::affecting(
            'INSERT IGNORE INTO traveller_facility_import_candidates
                (job_id, dataset_id, brand_id, external_id, facility_type, name, formatted_address, locality,
                 latitude, longitude, source_url, source_licence, source_attribution, raw_json, confidence,
                 duplicate_facility_id, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))',
            [
                $jobId,
                (int) $dataset['id'],
                $brandId,
                mb_substr($externalId, 0, 255),
                $type,
                mb_substr($name, 0, 190),
                isset($row['formatted_address']) ? mb_substr((string) $row['formatted_address'], 0, 500) : null,
                isset($row['locality']) ? mb_substr((string) $row['locality'], 0, 120) : null,
                is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
                is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
                isset($row['source_url']) ? mb_substr((string) $row['source_url'], 0, 1000) : (string) ($dataset['endpoint_url'] ?? null),
                mb_substr((string) ($row['licence'] ?? $dataset['licence'] ?? ''), 0, 120) ?: null,
                mb_substr((string) ($row['attribution'] ?? $dataset['attribution'] ?? ''), 0, 255) ?: null,
                json_encode($row['raw'] ?? $row, JSON_THROW_ON_ERROR),
                max(0, min(100, (int) ($row['confidence'] ?? 70))),
                $dup['id'] ?? null,
            ]
        );
        return $affected > 0;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function publishCandidate(array $candidate): int
    {
        $sourceKey = 'gov:dataset';
        if (!empty($candidate['dataset_id'])) {
            $ds = $this->findDataset((int) $candidate['dataset_id']);
            if ($ds !== null) {
                $sourceKey = self::catalogueSourceKey((string) $ds['dataset_key'], (string) $ds['connector_key']);
            }
        }
        $slugBase = strtolower(trim((string) $candidate['name']));
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? 'facility';
        $slugBase = trim($slugBase, '-') ?: 'facility';
        $slug = $slugBase . '-' . substr(sha1($sourceKey . '|' . (string) $candidate['external_id']), 0, 8);
        $location = $this->resolveFacilityLocation(
            isset($candidate['locality']) ? (string) $candidate['locality'] : null,
            is_numeric($candidate['latitude'] ?? null) ? (float) $candidate['latitude'] : null,
            is_numeric($candidate['longitude'] ?? null) ? (float) $candidate['longitude'] : null
        );

        $existing = Database::selectOne(
            'SELECT id FROM traveller_facilities WHERE source_key = ? AND source_record_id = ? LIMIT 1',
            [$sourceKey, (string) $candidate['external_id']]
        );
        if ($existing !== null) {
            Database::affecting(
                'UPDATE traveller_facilities SET name = ?, facility_type = ?, latitude = ?, longitude = ?, formatted_address = ?,
                    locality = ?, town_id = ?, state_id = ?, source_licence = ?, source_attribution = ?, source_url = ?, confidence = ?,
                    verification_status = \'reviewed\', status = \'active\', last_checked_at = NOW(), updated_at = NOW(), deleted_at = NULL
                 WHERE id = ?',
                [
                    (string) $candidate['name'],
                    (string) $candidate['facility_type'],
                    $candidate['latitude'],
                    $candidate['longitude'],
                    $candidate['formatted_address'],
                    $candidate['locality'],
                    $location['town_id'],
                    $location['state_id'],
                    $candidate['source_licence'],
                    $candidate['source_attribution'],
                    $candidate['source_url'],
                    (int) $candidate['confidence'],
                    (int) $existing['id'],
                ]
            );
            return (int) $existing['id'];
        }

        return Database::insert(
            'INSERT INTO traveller_facilities
                (facility_type, name, slug, latitude, longitude, formatted_address, locality, town_id, state_id, operating_status,
                 source_key, source_record_id, source_licence, source_attribution, source_url, confidence,
                 verification_status, status, brand_id, last_checked_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'unknown\', ?, ?, ?, ?, ?, ?, \'reviewed\', \'active\', ?, NOW(), NOW(), NOW())',
            [
                (string) $candidate['facility_type'],
                (string) $candidate['name'],
                $slug,
                $candidate['latitude'],
                $candidate['longitude'],
                $candidate['formatted_address'],
                $candidate['locality'],
                $location['town_id'],
                $location['state_id'],
                $sourceKey,
                (string) $candidate['external_id'],
                $candidate['source_licence'],
                $candidate['source_attribution'],
                $candidate['source_url'],
                (int) $candidate['confidence'],
                $candidate['brand_id'] !== null ? (int) $candidate['brand_id'] : null,
            ]
        );
    }

    /** @return array{town_id:?int,state_id:?int} */
    private function resolveFacilityLocation(?string $locality, ?float $lat, ?float $lng): array
    {
        $locality = trim((string) $locality);
        if ($locality !== '') {
            $byName = Database::selectOne(
                'SELECT id, state_id FROM towns WHERE is_active = 1 AND LOWER(name) = LOWER(?) LIMIT 1',
                [$locality]
            );
            if ($byName !== null) {
                return [
                    'town_id' => (int) $byName['id'],
                    'state_id' => isset($byName['state_id']) ? (int) $byName['state_id'] : null,
                ];
            }
        }
        if ($lat !== null && $lng !== null) {
            $nearest = Town::nearestActive($lat, $lng);
            if ($nearest !== null && (float) ($nearest['distance_km'] ?? 999) <= 50) {
                return [
                    'town_id' => (int) $nearest['id'],
                    'state_id' => isset($nearest['state_id']) ? (int) $nearest['state_id'] : null,
                ];
            }
        }
        return ['town_id' => null, 'state_id' => null];
    }

    private function maybeCompleteJob(int $jobId): void
    {
        if ($jobId < 1) {
            return;
        }
        $pending = (int) Database::scalar(
            'SELECT COUNT(*) FROM traveller_facility_import_candidates WHERE job_id = ? AND review_status = \'pending\'',
            [$jobId]
        );
        if ($pending > 0) {
            return;
        }
        Database::affecting(
            'UPDATE traveller_facility_import_jobs SET status = \'completed\', completed_at = COALESCE(completed_at, NOW()) WHERE id = ? AND status = \'review\'',
            [$jobId]
        );
    }
}
