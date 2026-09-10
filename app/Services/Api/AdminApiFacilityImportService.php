<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\GovernmentDatasetService;

/**
 * Assist RIC facility package ingest with trusted-automatic publish (ADR 0034).
 *
 * Service accounts with imports:write may stage and publish Assist RIC government
 * facility packs into traveller_facilities. Other import paths stay review-first.
 */
final class AdminApiFacilityImportService
{
    private GovernmentDatasetService $government;

    public function __construct(?GovernmentDatasetService $government = null)
    {
        $this->government = $government ?? new GovernmentDatasetService();
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        $idempotencyKey = AdminApiIdempotency::requireKey($request);

        $checksum = strtolower(trim((string) ($input['checksum'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['checksum' => ['Checksum must be a 64-character SHA-256 hex string.']]
            );
        }

        $datasetKey = strtolower(trim((string) ($input['dataset_key'] ?? $input['dataset_id'] ?? '')));
        if ($datasetKey === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['dataset_key' => ['dataset_key is required.']]
            );
        }

        $items = $input['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['items' => ['At least one facility import item is required.']]
            );
        }

        $max = (int) Config::get('admin_api.max_batch_size', 100);
        if (count($items) > $max) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['items' => ['Batch size exceeds max_batch_size (' . $max . ').']]
            );
        }

        $replay = AdminApiIdempotency::execute(
            'facility_imports.create',
            $idempotencyKey,
            function () use ($datasetKey, $items, $checksum, $input, $request): array {
                if (!Database::tableExists('traveller_facility_import_jobs')
                    || !Database::tableExists('traveller_facility_import_candidates')
                ) {
                    throw new AdminApiException(
                        503,
                        'unavailable',
                        'Facility import tables are not available. Apply DATA-012 migrations.'
                    );
                }

                $dataset = $this->government->findDatasetByKey($datasetKey);
                if ($dataset === null) {
                    throw new AdminApiException(
                        404,
                        'not_found',
                        'Unknown government dataset_key for facility import.',
                        ['dataset_key' => ['No government_datasets row matches ' . $datasetKey . '.']]
                    );
                }

                $rows = [];
                foreach ($items as $index => $item) {
                    if (!is_array($item)) {
                        throw new AdminApiException(
                            422,
                            'validation_failed',
                            'Validation failed.',
                            ['items.' . $index => ['Each item must be an object.']]
                        );
                    }
                    $payload = $item['payload'] ?? $item;
                    if (!is_array($payload)) {
                        throw new AdminApiException(
                            422,
                            'validation_failed',
                            'Validation failed.',
                            ['items.' . $index . '.payload' => ['Payload must be an object.']]
                        );
                    }
                    $entityType = strtolower(trim((string) ($item['entity_type'] ?? $payload['facility_type'] ?? '')));
                    if ($entityType !== '' && !in_array($entityType, ['traveller_facility', 'facility'], true)) {
                        // RIC sends concrete facility types (public_toilet, boat_ramp, …) as entity_type.
                        $payload['facility_type'] = $payload['facility_type'] ?? $entityType;
                    }
                    $rows[] = $payload;
                }

                $brandId = AdminApiBrandScope::brandId();
                $userId = AdminApiContext::userId();
                $result = $this->government->ingestAssistRicRows(
                    (int) $dataset['id'],
                    $rows,
                    $brandId,
                    $userId,
                    [
                        'checksum' => $checksum,
                        'source_system' => 'assist-ric',
                        'meta' => is_array($input['meta'] ?? null) ? $input['meta'] : [],
                    ]
                );

                $publishLimit = max(count($rows), 200);
                $published = $this->government->publishPendingAssistRicCandidates(
                    (int) $dataset['id'],
                    null,
                    $userId,
                    'Auto-published Assist RIC trusted government pack (ADR 0034).',
                    $publishLimit
                );

                AdminApiAudit::record(
                    'facility_import.received',
                    'traveller_facility_import_job',
                    (string) ($result['job_id'] ?? ''),
                    null,
                    [
                        'dataset_key' => $datasetKey,
                        'checksum' => $checksum,
                        'item_count' => count($rows),
                        'new' => (int) ($result['new'] ?? 0),
                        'found' => (int) ($result['found'] ?? 0),
                        'published' => (int) ($published['processed'] ?? 0),
                        'publish_remaining' => (int) ($published['remaining'] ?? 0),
                    ],
                    $request
                );

                return [
                    'id' => (string) ($result['job_id'] ?? ''),
                    'status' => 'published',
                    'dataset_key' => $datasetKey,
                    'dataset_id' => (string) $dataset['id'],
                    'package_checksum' => $checksum,
                    'item_count' => count($rows),
                    'candidates_found' => (int) ($result['found'] ?? 0),
                    'candidates_new' => (int) ($result['new'] ?? 0),
                    'published' => (int) ($published['processed'] ?? 0),
                    'publish_remaining' => (int) ($published['remaining'] ?? 0),
                    'publish_errors' => $published['errors'] ?? [],
                    'notes' => 'Staged and auto-published Assist RIC government facilities (ADR 0034).',
                ];
            }
        );

        return $replay['result'];
    }

    /**
     * Drain pending Assist RIC facility candidates without a new package upload.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function publishPending(array $input, Request $request): array
    {
        if (!Database::tableExists('traveller_facility_import_jobs')
            || !Database::tableExists('traveller_facility_import_candidates')
        ) {
            throw new AdminApiException(
                503,
                'unavailable',
                'Facility import tables are not available. Apply DATA-012 migrations.'
            );
        }

        $datasetId = null;
        $datasetKey = strtolower(trim((string) ($input['dataset_key'] ?? $input['dataset_id'] ?? '')));
        if ($datasetKey !== '') {
            $dataset = $this->government->findDatasetByKey($datasetKey);
            if ($dataset === null) {
                throw new AdminApiException(
                    404,
                    'not_found',
                    'Unknown government dataset_key for facility publish.',
                    ['dataset_key' => ['No government_datasets row matches ' . $datasetKey . '.']]
                );
            }
            $datasetId = (int) $dataset['id'];
        }

        $limit = (int) ($input['limit'] ?? 200);
        $userId = AdminApiContext::userId();
        $published = $this->government->publishPendingAssistRicCandidates(
            $datasetId,
            null,
            $userId,
            'Auto-published Assist RIC pending queue flush (ADR 0034).',
            $limit
        );

        AdminApiAudit::record(
            'facility_import.publish_pending',
            'traveller_facility_import_candidate',
            $datasetKey !== '' ? $datasetKey : 'all',
            null,
            [
                'dataset_key' => $datasetKey !== '' ? $datasetKey : null,
                'processed' => (int) ($published['processed'] ?? 0),
                'remaining' => (int) ($published['remaining'] ?? 0),
                'limit' => max(1, min(500, $limit)),
            ],
            $request
        );

        return [
            'status' => 'published',
            'dataset_key' => $datasetKey !== '' ? $datasetKey : null,
            'processed' => (int) ($published['processed'] ?? 0),
            'remaining' => (int) ($published['remaining'] ?? 0),
            'errors' => $published['errors'] ?? [],
            'notes' => 'Published pending Assist RIC government facilities (ADR 0034).',
        ];
    }
}
