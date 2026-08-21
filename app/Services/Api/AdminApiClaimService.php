<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Park claims and provider invite tokens for Admin API review (Option B Increment B).
 */
final class AdminApiClaimService
{
    /** @var list<string> */
    private const PARK_STATUSES = ['pending', 'approved', 'rejected', 'withdrawn'];

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function list(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));
        $type = strtolower(trim((string) $request->query('type', '')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $afterId = AdminApiCursor::decode($request->query('cursor'));

        $items = [];

        if ($type === '' || $type === 'park_claim') {
            $items = array_merge($items, $this->listParkClaims($status, $afterId, $limit));
        }

        if ($type === '' || $type === 'provider_invite') {
            $items = array_merge($items, $this->listProviderInvites($status, $limit));
        }

        if ($type !== '' && !in_array($type, ['park_claim', 'provider_invite'], true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['type' => ['Type must be park_claim or provider_invite.']]
            );
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        $page = AdminApiCursor::page($items, $limit, static fn (array $row): int => (int) $row['_cursor_id']);

        $mapped = array_map(static function (array $row): array {
            unset($row['_cursor_id']);

            return $row;
        }, $page['items']);

        return [
            'items' => $mapped,
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'brand_id' => AdminApiBrandScope::brandId(),
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @return array<string,mixed> */
    public function show(int $id, Request $request): array
    {
        $type = strtolower(trim((string) $request->query('type', 'park_claim')));
        if ($type === 'provider_invite') {
            return $this->showProviderInvite($id);
        }

        return $this->showParkClaim($id);
    }

    /** @return array<string,mixed> */
    public function approve(int $id, Request $request): array
    {
        return $this->reviewParkClaim($id, 'approved', $request, null);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function reject(int $id, array $input, Request $request): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));

        return $this->reviewParkClaim($id, 'rejected', $request, $reason !== '' ? $reason : null);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function requestEvidence(int $id, array $input, Request $request): array
    {
        $claim = $this->findParkClaim($id);
        if ((string) $claim['status'] !== 'pending') {
            throw new AdminApiException(409, 'conflict', 'Only pending park claims can request additional evidence.');
        }

        $notes = trim((string) ($input['notes'] ?? $input['reason'] ?? ''));
        $evidenceNotes = (string) ($claim['evidence_notes'] ?? '');
        if ($notes !== '') {
            $evidenceNotes = $evidenceNotes !== '' ? $evidenceNotes . "\n\n" . $notes : $notes;
        }

        Database::query(
            'UPDATE caravan_park_claims SET status = ?, evidence_notes = ?, updated_at = NOW() WHERE id = ?',
            ['pending', $evidenceNotes !== '' ? $evidenceNotes : $claim['evidence_notes'], $id]
        );

        AdminApiAudit::record(
            'claim.evidence_requested',
            'caravan_park_claim',
            $id,
            ['status' => (string) $claim['status']],
            ['status' => 'pending', 'notes' => $notes !== '' ? $notes : null],
            $request
        );

        return $this->showParkClaim($id);
    }

    /** @return list<array<string,mixed>> */
    private function listParkClaims(string $status, ?int $afterId, int $limit): array
    {
        if (!Database::tableExists('caravan_park_claims')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($status !== '') {
            if (!in_array($status, self::PARK_STATUSES, true)) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['status' => ['Status is not recognised for park claims.']]
                );
            }
            $where[] = 'c.status = ?';
            $params[] = $status;
        }

        if ($afterId !== null) {
            $where[] = 'c.id < ?';
            $params[] = $afterId;
        }

        $rows = Database::select(
            'SELECT c.*, cp.name AS park_name, cp.slug AS park_slug '
            . 'FROM caravan_park_claims c '
            . 'INNER JOIN caravan_parks cp ON cp.id = c.park_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'ORDER BY c.id DESC LIMIT ' . ($limit + 1),
            $params
        );

        return array_map(fn (array $row): array => $this->mapParkClaim($row), $rows);
    }

    /** @return list<array<string,mixed>> */
    private function listProviderInvites(string $status, int $limit): array
    {
        if (!Database::tableExists('provider_claim_tokens')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($status === 'pending' || $status === '') {
            $where[] = 't.used_at IS NULL AND t.expires_at > NOW()';
        } elseif ($status === 'used') {
            $where[] = 't.used_at IS NOT NULL';
        } elseif ($status === 'expired') {
            $where[] = 't.used_at IS NULL AND t.expires_at <= NOW()';
        } elseif ($status !== '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['status' => ['Provider invite status must be pending, used or expired.']]
            );
        }

        $rows = Database::select(
            'SELECT t.*, p.business_name, p.slug AS provider_slug '
            . 'FROM provider_claim_tokens t '
            . 'INNER JOIN providers p ON p.id = t.provider_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'ORDER BY t.id DESC LIMIT ' . ($limit + 1),
            $params
        );

        return array_map(fn (array $row): array => $this->mapProviderInvite($row), $rows);
    }

    /** @return array<string,mixed> */
    private function showParkClaim(int $id): array
    {
        $row = $this->findParkClaim($id);

        return $this->mapParkClaim($row);
    }

    /** @return array<string,mixed> */
    private function showProviderInvite(int $id): array
    {
        $row = Database::selectOne(
            'SELECT t.*, p.business_name, p.slug AS provider_slug '
            . 'FROM provider_claim_tokens t '
            . 'INNER JOIN providers p ON p.id = t.provider_id '
            . 'WHERE t.id = ?',
            [$id]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Provider invite not found.');
        }

        return $this->mapProviderInvite($row);
    }

    /** @return array<string,mixed> */
    private function findParkClaim(int $id): array
    {
        if (!Database::tableExists('caravan_park_claims')) {
            throw new AdminApiException(404, 'not_found', 'Park claim not found.');
        }

        $row = Database::selectOne(
            'SELECT c.*, cp.name AS park_name, cp.slug AS park_slug '
            . 'FROM caravan_park_claims c '
            . 'INNER JOIN caravan_parks cp ON cp.id = c.park_id '
            . 'WHERE c.id = ?',
            [$id]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Park claim not found.');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function reviewParkClaim(int $id, string $decision, Request $request, ?string $reason): array
    {
        $claim = $this->findParkClaim($id);
        if ((string) $claim['status'] !== 'pending') {
            throw new AdminApiException(409, 'conflict', 'Only pending park claims can be reviewed.');
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new AdminApiException(422, 'validation_failed', 'Unknown review decision.');
        }

        Database::query(
            'UPDATE caravan_park_claims SET status = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?',
            [$decision, AdminApiContext::userId(), $id]
        );

        AdminApiAudit::record(
            'claim.' . $decision,
            'caravan_park_claim',
            $id,
            ['status' => 'pending', 'park_id' => (int) $claim['park_id']],
            ['status' => $decision, 'reason' => $reason],
            $request
        );

        return $this->showParkClaim($id);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapParkClaim(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'type' => 'park_claim',
            '_cursor_id' => (int) $row['id'],
            'park_id' => (string) $row['park_id'],
            'park_name' => (string) ($row['park_name'] ?? ''),
            'park_slug' => (string) ($row['park_slug'] ?? ''),
            'claimant_name' => (string) ($row['claimant_name'] ?? ''),
            'claimant_email' => (string) ($row['claimant_email'] ?? ''),
            'claimant_phone' => $row['claimant_phone'] !== null ? (string) $row['claimant_phone'] : null,
            'relationship_to_park' => $row['relationship_to_park'] !== null ? (string) $row['relationship_to_park'] : null,
            'evidence_notes' => $row['evidence_notes'] !== null ? (string) $row['evidence_notes'] : null,
            'status' => (string) ($row['status'] ?? ''),
            'reviewed_by' => $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapProviderInvite(array $row): array
    {
        $status = 'pending';
        if ($row['used_at'] !== null) {
            $status = 'used';
        } elseif (strtotime((string) $row['expires_at']) <= time()) {
            $status = 'expired';
        }

        return [
            'id' => (string) $row['id'],
            'type' => 'provider_invite',
            '_cursor_id' => (int) $row['id'],
            'provider_id' => (string) $row['provider_id'],
            'provider_name' => (string) ($row['business_name'] ?? ''),
            'provider_slug' => (string) ($row['provider_slug'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'used_at' => $row['used_at'] ?? null,
            'status' => $status,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
