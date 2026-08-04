<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\GovernmentDatasetService;
use RuntimeException;
use Throwable;

/**
 * Admin API queues for facility/provider import candidates (Option B Increment H / H.1 / H.2 / H.3).
 *
 * Separate from GET /imports (api_import_jobs / RIC packages). Query patterns follow
 * GovernmentDatasetService::pendingCandidates and DataSourceService::reviewQueue.
 * Facility/provider approve/reject are human-only; merge stays website admin.
 * Facility bulk approve/reject use the same human scope and per-id brand gates.
 */
class AdminApiImportCandidateService
{
    /** @var list<string> */
    private const FACILITY_STATUSES = ['pending', 'approved', 'rejected', 'ignored'];

    /** @var list<string> */
    private const PROVIDER_STATUSES = ['pending', 'held', 'approved', 'merged', 'rejected', 'ignored'];

    /** @var list<string> */
    private const SECRET_RAW_KEYS = [
        'password',
        'passwd',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'authorization',
        'auth',
        'credential',
        'credentials',
        'private_key',
        'client_secret',
    ];

    private FacilityImportCandidateReviewGateway $datasets;

    private ProviderImportCandidateReviewGateway $providers;

    public function __construct(
        ?FacilityImportCandidateReviewGateway $datasets = null,
        ?ProviderImportCandidateReviewGateway $providers = null
    ) {
        $this->datasets = $datasets ?? new GovernmentDatasetService();
        $this->providers = $providers ?? new \App\Services\DataSourceService();
    }
    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function listFacilityCandidates(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('traveller_facility_import_candidates');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'traveller_facility_import_candidates_missing');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $brandId = AdminApiBrandScope::brandId();
        $status = $this->statusFilter($request, self::FACILITY_STATUSES, 'pending');

        // Brand rule matches AdminApiFacilityService: current brand or national (NULL).
        $where = ['(c.brand_id = ? OR c.brand_id IS NULL)', 'c.review_status = ?'];
        $params = [$brandId, $status];

        // Match GovernmentDatasetService::pendingCandidates expiry gate for the default pending queue.
        if ($status === 'pending') {
            $where[] = 'c.expires_at > NOW()';
        }

        if ($afterId !== null) {
            $where[] = 'c.id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT c.id, c.job_id, c.dataset_id, c.brand_id, c.external_id, c.facility_type, c.name, '
            . 'c.formatted_address, c.locality, c.latitude, c.longitude, c.source_url, c.source_licence, '
            . 'c.source_attribution, c.confidence, c.review_status, c.duplicate_facility_id, c.facility_id, '
            . 'c.reviewed_by, c.reviewed_at, c.review_notes, c.created_at, c.updated_at, c.expires_at, '
            . 'd.title AS dataset_title, d.publisher '
            . 'FROM traveller_facility_import_candidates c '
            . 'LEFT JOIN government_datasets d ON d.id = c.dataset_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY c.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => $this->facilitySummary($row), $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'brand_id' => $brandId,
                'status' => $status,
                'queue' => 'facility_import_candidates',
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @return array<string,mixed> */
    public function showFacilityCandidate(int $id): array
    {
        try {
            $tableReady = Database::tableExists('traveller_facility_import_candidates');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            throw new AdminApiException(404, 'not_found', 'Facility import candidate not found.');
        }

        $brandId = AdminApiBrandScope::brandId();
        $row = Database::selectOne(
            'SELECT c.*, d.title AS dataset_title, d.publisher '
            . 'FROM traveller_facility_import_candidates c '
            . 'LEFT JOIN government_datasets d ON d.id = c.dataset_id '
            . 'WHERE c.id = ? AND (c.brand_id = ? OR c.brand_id IS NULL) LIMIT 1',
            [$id, $brandId]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Facility import candidate not found.');
        }

        return $this->facilityDetail($row);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function approveFacilityCandidate(int $id, array $input, Request $request): array
    {
        return $this->reviewFacilityCandidate($id, 'approve', $request, $this->optionalReason($input));
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function rejectFacilityCandidate(int $id, array $input, Request $request): array
    {
        return $this->reviewFacilityCandidate($id, 'reject', $request, $this->optionalReason($input));
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function bulkApproveFacilityCandidates(array $input, Request $request): array
    {
        return $this->bulkReviewFacilityCandidates('approve', $input, $request);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function bulkRejectFacilityCandidates(array $input, Request $request): array
    {
        return $this->bulkReviewFacilityCandidates('reject', $input, $request);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function approveProviderCandidate(int $id, array $input, Request $request): array
    {
        $retentionConfirmed = $this->truthy($input['retention_confirmed'] ?? false);
        $evidenceUrl = trim((string) ($input['evidence_url'] ?? ''));
        $categoryId = isset($input['category_id']) && $input['category_id'] !== '' && $input['category_id'] !== null
            ? (int) $input['category_id']
            : null;
        if ($categoryId !== null && $categoryId < 1) {
            $categoryId = null;
        }
        $notes = $this->optionalReason($input) ?? '';

        $errors = [];
        if (!$retentionConfirmed) {
            $errors['retention_confirmed'] = [
                'Confirm an independent right to retain and publish this business data before approval.',
            ];
        }
        if ($evidenceUrl === '') {
            $errors['evidence_url'] = [
                'Provide an independent http/https evidence URL (not a Google search or Maps URL).',
            ];
        }
        if ($errors !== []) {
            throw new AdminApiException(422, 'validation_failed', 'Validation failed.', $errors);
        }

        return $this->reviewProviderCandidate(
            $id,
            'approve',
            $request,
            null,
            $retentionConfirmed,
            $categoryId,
            $evidenceUrl,
            $notes
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function rejectProviderCandidate(int $id, array $input, Request $request): array
    {
        return $this->reviewProviderCandidate(
            $id,
            'reject',
            $request,
            null,
            false,
            null,
            '',
            $this->optionalReason($input) ?? ''
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function mergeProviderCandidate(int $id, array $input, Request $request): array
    {
        $retentionConfirmed = $this->truthy($input['retention_confirmed'] ?? false);
        $evidenceUrl = trim((string) ($input['evidence_url'] ?? ''));
        $categoryId = isset($input['category_id']) && $input['category_id'] !== '' && $input['category_id'] !== null
            ? (int) $input['category_id']
            : null;
        if ($categoryId !== null && $categoryId < 1) {
            $categoryId = null;
        }
        $providerId = isset($input['provider_id']) && $input['provider_id'] !== '' && $input['provider_id'] !== null
            ? (int) $input['provider_id']
            : null;
        if ($providerId !== null && $providerId < 1) {
            $providerId = null;
        }
        $notes = $this->optionalReason($input) ?? '';

        $errors = [];
        if (!$retentionConfirmed) {
            $errors['retention_confirmed'] = [
                'Confirm an independent right to retain and publish this business data before merging.',
            ];
        }
        if ($evidenceUrl === '') {
            $errors['evidence_url'] = [
                'Provide an independent http/https evidence URL (not a Google search or Maps URL).',
            ];
        }
        if ($errors !== []) {
            throw new AdminApiException(422, 'validation_failed', 'Validation failed.', $errors);
        }

        return $this->reviewProviderCandidate(
            $id,
            'merge',
            $request,
            $providerId,
            $retentionConfirmed,
            $categoryId,
            $evidenceUrl,
            $notes
        );
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function listProviderCandidates(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('data_source_import_candidates');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'data_source_import_candidates_missing');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $brandId = AdminApiBrandScope::brandId();
        $status = $this->statusFilter($request, self::PROVIDER_STATUSES, 'pending');

        // Brand-scoped like DataSourceService::reviewQueue / queue().
        $where = ['c.brand_id = ?', 'c.review_status = ?'];
        $params = [$brandId, $status];

        if ($status === 'pending') {
            $where[] = 'c.expires_at > NOW()';
        }

        $state = strtoupper(trim((string) $request->query('state', '')));
        if ($state !== '') {
            if (preg_match('/^[A-Z]{2,3}$/', $state) !== 1) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['state' => ['State must be a 2–3 letter Australian jurisdiction code.']]
                );
            }
            $where[] = 'c.candidate_state = ?';
            $params[] = $state;
        }

        $search = trim((string) $request->query('q', $request->query('search', '')));
        if ($search !== '') {
            $where[] = '(c.business_name LIKE ? OR c.formatted_address LIKE ? OR c.phone LIKE ? OR c.website LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if ($afterId !== null) {
            $where[] = 'c.id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT c.id, c.job_id, c.connector_id, c.brand_id, c.category_id, c.external_id, c.business_name, '
            . 'c.formatted_address, c.phone, c.website, c.latitude, c.longitude, c.candidate_state, c.route_hub, '
            . 'c.confidence, c.review_status, c.duplicate_provider_id, c.duplicate_score, c.provider_id, '
            . 'c.evidence_status, c.reviewed_by, c.reviewed_at, c.created_at, c.updated_at, c.expires_at, '
            . 'ds.name AS connector_name, ds.connector_key, bpc.name AS category_name, p.business_name AS duplicate_name '
            . 'FROM data_source_import_candidates c '
            . 'JOIN data_source_connectors ds ON ds.id = c.connector_id '
            . 'LEFT JOIN brand_provider_categories bpc ON bpc.id = c.category_id '
            . 'LEFT JOIN providers p ON p.id = c.duplicate_provider_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY c.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => $this->providerSummary($row), $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'brand_id' => $brandId,
                'status' => $status,
                'queue' => 'provider_import_candidates',
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @return array<string,mixed> */
    public function showProviderCandidate(int $id): array
    {
        try {
            $tableReady = Database::tableExists('data_source_import_candidates');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            throw new AdminApiException(404, 'not_found', 'Provider import candidate not found.');
        }

        $brandId = AdminApiBrandScope::brandId();
        $row = Database::selectOne(
            'SELECT c.*, ds.name AS connector_name, ds.connector_key, bpc.name AS category_name, '
            . 'p.business_name AS duplicate_name '
            . 'FROM data_source_import_candidates c '
            . 'JOIN data_source_connectors ds ON ds.id = c.connector_id '
            . 'LEFT JOIN brand_provider_categories bpc ON bpc.id = c.category_id '
            . 'LEFT JOIN providers p ON p.id = c.duplicate_provider_id '
            . 'WHERE c.id = ? AND c.brand_id = ? LIMIT 1',
            [$id, $brandId]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Provider import candidate not found.');
        }

        return $this->providerDetail($row);
    }

    /**
     * @return array<string,mixed>
     */
    private function reviewFacilityCandidate(int $id, string $action, Request $request, ?string $notes): array
    {
        $before = $this->showFacilityCandidate($id);
        if ((string) ($before['review_status'] ?? '') !== 'pending') {
            throw new AdminApiException(
                409,
                'conflict',
                'Only pending facility import candidates can be reviewed.'
            );
        }

        try {
            $this->datasets->reviewCandidate($id, $action, AdminApiContext::userId(), $notes);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'not found') || str_contains($message, 'already reviewed')) {
                throw new AdminApiException(
                    409,
                    'conflict',
                    'Only pending facility import candidates can be reviewed.'
                );
            }
            if (str_contains($message, 'Invalid review action')) {
                throw new AdminApiException(422, 'validation_failed', 'Unknown review action.');
            }
            throw new AdminApiException(422, 'validation_failed', $message);
        } catch (Throwable $e) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Facility import candidate could not be reviewed: ' . $e->getMessage()
            );
        }

        $after = $this->showFacilityCandidate($id);
        AdminApiAudit::record(
            'facility_import_candidate.' . ($action === 'approve' ? 'approved' : 'rejected'),
            'traveller_facility_import_candidate',
            $id,
            [
                'review_status' => 'pending',
                'facility_id' => $before['facility_id'] ?? null,
            ],
            [
                'review_status' => (string) ($after['review_status'] ?? ''),
                'facility_id' => $after['facility_id'] ?? null,
                'reason' => $notes,
            ],
            $request
        );

        return $after;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{
     *   action:string,
     *   count:int,
     *   processed:int,
     *   failed:int,
     *   results:list<array<string,mixed>>
     * }
     */
    private function bulkReviewFacilityCandidates(string $action, array $input, Request $request): array
    {
        $ids = $this->normaliseBulkIds($input['ids'] ?? null);
        $reasonPayload = [];
        $reason = $this->optionalReason($input);
        if ($reason !== null) {
            $reasonPayload['reason'] = $reason;
        }

        $results = [];
        $processed = 0;
        $failed = 0;
        foreach ($ids as $id) {
            try {
                $candidate = $action === 'approve'
                    ? $this->approveFacilityCandidate($id, $reasonPayload, $request)
                    : $this->rejectFacilityCandidate($id, $reasonPayload, $request);
                $results[] = [
                    'id' => (string) $id,
                    'status' => $action === 'approve' ? 'approved' : 'rejected',
                    'candidate' => $candidate,
                ];
                $processed++;
            } catch (AdminApiException $e) {
                $results[] = [
                    'id' => (string) $id,
                    'status' => 'failed',
                    'error' => [
                        'code' => $e->errorCode(),
                        'message' => $e->getMessage(),
                    ],
                ];
                $failed++;
            }
        }

        return [
            'action' => $action === 'approve' ? 'bulk_approve' : 'bulk_reject',
            'count' => count($results),
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @return list<int>
     */
    private function normaliseBulkIds(mixed $ids): array
    {
        if (!is_array($ids) || $ids === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['ids' => ['Provide at least one facility import-candidate id.']]
            );
        }

        $max = (int) Config::get('admin_api.max_batch_size', 100);
        if (count($ids) > $max) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['ids' => ['Batch size exceeds max_batch_size (' . $max . ').']]
            );
        }

        $normalised = [];
        $seen = [];
        foreach ($ids as $index => $raw) {
            $id = (int) $raw;
            if ($id < 1) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['ids.' . $index => ['Each id must be a positive integer.']]
                );
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $normalised[] = $id;
        }

        return $normalised;
    }

    /**
     * @return array<string,mixed>
     */
    private function reviewProviderCandidate(
        int $id,
        string $decision,
        Request $request,
        ?int $providerId,
        bool $retentionConfirmed,
        ?int $categoryId,
        string $evidenceUrl,
        string $reviewNotes
    ): array {
        $before = $this->showProviderCandidate($id);
        $status = (string) ($before['review_status'] ?? '');
        if ($decision === 'approve' && $status !== 'pending') {
            throw new AdminApiException(
                409,
                'conflict',
                'Only pending provider import candidates can be approved.'
            );
        }
        if ($decision === 'merge' && $status !== 'pending') {
            throw new AdminApiException(
                409,
                'conflict',
                'Only pending provider import candidates can be merged.'
            );
        }
        if ($decision === 'reject' && !in_array($status, ['pending', 'held'], true)) {
            throw new AdminApiException(
                409,
                'conflict',
                'Only pending or held provider import candidates can be rejected.'
            );
        }
        if (
            $decision === 'merge'
            && $providerId === null
            && empty($before['duplicate_provider_id'])
        ) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['provider_id' => ['Provide provider_id or merge a candidate that already has a duplicate_provider_id.']]
            );
        }

        $userId = AdminApiContext::userId();
        if ($userId === null || $userId < 1) {
            throw new AdminApiException(401, 'unauthenticated', 'Human Admin API session required.');
        }

        try {
            // Website admin often confirms evidence as a separate step. When the API
            // supplies retention + evidence URL on approve, confirm first so
            // BulkReviewPolicy::approvalProblems can pass for non-national-route rows.
            // Merge sets evidence_status in-memory inside DataSourceService::review.
            if (
                $decision === 'approve'
                && !in_array((string) ($before['evidence_status'] ?? ''), ['confirmed', 'claimed'], true)
            ) {
                $this->providers->review(
                    $id,
                    AdminApiBrandScope::brandId(),
                    'confirm',
                    $providerId,
                    $userId,
                    $retentionConfirmed,
                    $categoryId,
                    $evidenceUrl,
                    $reviewNotes
                );
            }

            $this->providers->review(
                $id,
                AdminApiBrandScope::brandId(),
                $decision,
                $providerId,
                $userId,
                $retentionConfirmed,
                $categoryId,
                $evidenceUrl,
                $reviewNotes
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if (
                str_contains($message, 'no longer awaiting review')
                || str_contains($message, 'no longer eligible')
            ) {
                throw new AdminApiException(
                    409,
                    'conflict',
                    match ($decision) {
                        'approve' => 'Only pending provider import candidates can be approved.',
                        'merge' => 'Only pending provider import candidates can be merged.',
                        default => 'Only pending or held provider import candidates can be rejected.',
                    }
                );
            }
            throw new AdminApiException(422, 'validation_failed', $message);
        } catch (Throwable $e) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Provider import candidate could not be reviewed: ' . $e->getMessage()
            );
        }

        $after = $this->showProviderCandidate($id);
        $auditAction = match ($decision) {
            'approve' => 'provider_import_candidate.approved',
            'merge' => 'provider_import_candidate.merged',
            default => 'provider_import_candidate.rejected',
        };
        AdminApiAudit::record(
            $auditAction,
            'data_source_import_candidate',
            $id,
            [
                'review_status' => $status,
                'provider_id' => $before['provider_id'] ?? null,
            ],
            [
                'review_status' => (string) ($after['review_status'] ?? ''),
                'provider_id' => $after['provider_id'] ?? null,
                'reason' => $reviewNotes !== '' ? $reviewNotes : null,
                'evidence_url' => $evidenceUrl !== '' ? $evidenceUrl : null,
                'retention_confirmed' => $retentionConfirmed,
                'merge_target_id' => $decision === 'merge' ? ($providerId ?? $before['duplicate_provider_id'] ?? null) : null,
            ],
            $request
        );

        return $after;
    }

    /**
     * @param array<string,mixed> $input
     */
    private function optionalReason(array $input): ?string
    {
        $reason = trim((string) ($input['reason'] ?? $input['notes'] ?? $input['review_notes'] ?? ''));

        return $reason !== '' ? $reason : null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalised = strtolower(trim((string) $value));

        return in_array($normalised, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param list<string> $allowed
     */
    private function statusFilter(Request $request, array $allowed, string $default): string
    {
        $raw = $request->query('status');
        if ($raw === null || $raw === '') {
            return $default;
        }

        $status = strtolower(trim((string) $raw));
        if (!in_array($status, $allowed, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['status' => ['Status must be one of: ' . implode(', ', $allowed) . '.']]
            );
        }

        return $status;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function facilitySummary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'job_id' => (string) $row['job_id'],
            'dataset_id' => isset($row['dataset_id']) && $row['dataset_id'] !== null ? (string) $row['dataset_id'] : null,
            'dataset_title' => isset($row['dataset_title']) && $row['dataset_title'] !== null ? (string) $row['dataset_title'] : null,
            'publisher' => isset($row['publisher']) && $row['publisher'] !== null ? (string) $row['publisher'] : null,
            'external_id' => (string) ($row['external_id'] ?? ''),
            'facility_type' => (string) ($row['facility_type'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'formatted_address' => $row['formatted_address'] !== null ? (string) $row['formatted_address'] : null,
            'locality' => $row['locality'] !== null ? (string) $row['locality'] : null,
            'latitude' => isset($row['latitude']) && $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) && $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'confidence' => (int) ($row['confidence'] ?? 0),
            'review_status' => (string) ($row['review_status'] ?? ''),
            'duplicate_facility_id' => isset($row['duplicate_facility_id']) && $row['duplicate_facility_id'] !== null
                ? (string) $row['duplicate_facility_id']
                : null,
            'facility_id' => isset($row['facility_id']) && $row['facility_id'] !== null ? (string) $row['facility_id'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function facilityDetail(array $row): array
    {
        return array_merge($this->facilitySummary($row), [
            'brand_id' => isset($row['brand_id']) && $row['brand_id'] !== null ? (int) $row['brand_id'] : null,
            'source_url' => $row['source_url'] !== null ? (string) $row['source_url'] : null,
            'source_licence' => $row['source_licence'] !== null ? (string) $row['source_licence'] : null,
            'source_attribution' => $row['source_attribution'] !== null ? (string) $row['source_attribution'] : null,
            'review_notes' => $row['review_notes'] !== null ? (string) $row['review_notes'] : null,
            'reviewed_by' => isset($row['reviewed_by']) && $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'raw' => $this->summariseRaw($row['raw_json'] ?? null),
        ]);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function providerSummary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'job_id' => (string) $row['job_id'],
            'connector_id' => (string) $row['connector_id'],
            'connector_key' => isset($row['connector_key']) ? (string) $row['connector_key'] : null,
            'connector_name' => isset($row['connector_name']) ? (string) $row['connector_name'] : null,
            'category_id' => isset($row['category_id']) && $row['category_id'] !== null ? (string) $row['category_id'] : null,
            'category_name' => isset($row['category_name']) && $row['category_name'] !== null ? (string) $row['category_name'] : null,
            'external_id' => (string) ($row['external_id'] ?? ''),
            'business_name' => (string) ($row['business_name'] ?? ''),
            'formatted_address' => $row['formatted_address'] !== null ? (string) $row['formatted_address'] : null,
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'website' => $row['website'] !== null ? (string) $row['website'] : null,
            'latitude' => isset($row['latitude']) && $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) && $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'candidate_state' => isset($row['candidate_state']) && $row['candidate_state'] !== null
                ? (string) $row['candidate_state']
                : null,
            'route_hub' => isset($row['route_hub']) && $row['route_hub'] !== null ? (string) $row['route_hub'] : null,
            'confidence' => (int) ($row['confidence'] ?? 0),
            'review_status' => (string) ($row['review_status'] ?? ''),
            'duplicate_provider_id' => isset($row['duplicate_provider_id']) && $row['duplicate_provider_id'] !== null
                ? (string) $row['duplicate_provider_id']
                : null,
            'duplicate_name' => isset($row['duplicate_name']) && $row['duplicate_name'] !== null
                ? (string) $row['duplicate_name']
                : null,
            'duplicate_score' => isset($row['duplicate_score']) && $row['duplicate_score'] !== null
                ? (int) $row['duplicate_score']
                : null,
            'provider_id' => isset($row['provider_id']) && $row['provider_id'] !== null ? (string) $row['provider_id'] : null,
            'evidence_status' => isset($row['evidence_status']) && $row['evidence_status'] !== null
                ? (string) $row['evidence_status']
                : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function providerDetail(array $row): array
    {
        $duplicateReasons = $this->decodeJson($row['duplicate_reasons_json'] ?? null);

        return array_merge($this->providerSummary($row), [
            'brand_id' => (int) ($row['brand_id'] ?? 0),
            'evidence_url' => isset($row['evidence_url']) && $row['evidence_url'] !== null
                ? (string) $row['evidence_url']
                : null,
            'review_notes' => isset($row['review_notes']) && $row['review_notes'] !== null
                ? (string) $row['review_notes']
                : null,
            'hold_reason' => isset($row['hold_reason']) && $row['hold_reason'] !== null
                ? (string) $row['hold_reason']
                : null,
            'reviewed_by' => isset($row['reviewed_by']) && $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'duplicate_reasons' => $duplicateReasons,
            'raw' => $this->summariseRaw($row['raw_json'] ?? null),
        ]);
    }

    /**
     * Parse raw_json into a bounded, secret-stripped summary for detail views.
     *
     * @return array<string,mixed>|null
     */
    private function summariseRaw(mixed $raw): ?array
    {
        $decoded = $this->decodeJson($raw);
        if ($decoded === null) {
            return null;
        }

        return $this->sanitiseRawNode($decoded, 0);
    }

    /**
     * @param array<string,mixed>|list<mixed> $node
     * @return array<string,mixed>|list<mixed>
     */
    private function sanitiseRawNode(array $node, int $depth): array
    {
        if ($depth > 2) {
            return ['truncated' => true];
        }

        $out = [];
        $count = 0;
        foreach ($node as $key => $value) {
            if ($count >= 40) {
                $out['_truncated'] = true;
                break;
            }

            $keyString = is_string($key) ? $key : (string) $key;
            if ($this->isSecretRawKey($keyString)) {
                $out[$keyString] = '[redacted]';
                $count++;
                continue;
            }

            if (is_array($value)) {
                $out[$keyString] = $this->sanitiseRawNode($value, $depth + 1);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $out[$keyString] = $value;
            } elseif (is_string($value)) {
                $out[$keyString] = mb_strlen($value) > 500 ? (mb_substr($value, 0, 500) . '…') : $value;
            } else {
                $out[$keyString] = '[omitted]';
            }
            $count++;
        }

        return $out;
    }

    private function isSecretRawKey(string $key): bool
    {
        $normalised = strtolower($key);

        return in_array($normalised, self::SECRET_RAW_KEYS, true)
            || str_contains($normalised, 'password')
            || str_contains($normalised, 'secret')
            || str_contains($normalised, 'token')
            || str_contains($normalised, 'api_key');
    }

    /** @return array<string,mixed>|list<mixed>|null */
    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    private function emptyPage(int $limit, string $source): array
    {
        return [
            'items' => [],
            'meta' => [
                'count' => 0,
                'limit' => $limit,
                'has_more' => false,
                'next_cursor' => null,
                'sparse' => true,
                'source' => $source,
            ],
            'links' => ['next' => null],
        ];
    }
}
