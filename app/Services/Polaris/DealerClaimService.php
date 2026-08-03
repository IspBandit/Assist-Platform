<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use App\Services\AuditLog;
use RuntimeException;

/**
 * Claim-first dealer onboarding (no used-stock inventory).
 */
final class DealerClaimService
{
    /** @return list<array<string,mixed>> */
    public function searchForClaim(int $brandId, string $query, int $limit = 20): array
    {
        $query = trim($query);
        $limit = max(1, min(50, $limit));
        if ($query === '') {
            return Database::select(
                'SELECT id, trading_name, slug, locality, state_abbr, claim_status, verification_status, website_url, is_demo
                 FROM polaris_dealers
                 WHERE brand_id = ? AND deleted_at IS NULL AND lifecycle_status = \'active\' AND claim_status = \'unclaimed\'
                 ORDER BY trading_name ASC LIMIT ' . $limit,
                [$brandId]
            );
        }
        $like = '%' . $query . '%';
        return Database::select(
            'SELECT id, trading_name, slug, locality, state_abbr, claim_status, verification_status, website_url, is_demo
             FROM polaris_dealers
             WHERE brand_id = ? AND deleted_at IS NULL AND lifecycle_status = \'active\'
               AND (trading_name LIKE ? OR locality LIKE ? OR slug LIKE ?)
             ORDER BY claim_status = \'unclaimed\' DESC, trading_name ASC
             LIMIT ' . $limit,
            [$brandId, $like, $like, $like]
        );
    }

    public function submitClaim(int $brandId, int $dealerId, int $userId, string $evidence): void
    {
        $dealer = Database::selectOne(
            'SELECT * FROM polaris_dealers WHERE id = ? AND brand_id = ? AND deleted_at IS NULL LIMIT 1',
            [$dealerId, $brandId]
        );
        if ($dealer === null) {
            throw new RuntimeException('Dealer profile not found. Search existing dealers before creating a new one.');
        }
        if (in_array((string) $dealer['claim_status'], ['claimed', 'pending'], true)) {
            throw new RuntimeException('This dealer is already claimed or has a pending claim.');
        }
        Database::affecting(
            'UPDATE polaris_dealers SET claim_status = \'pending\', claimed_by_user_id = ?, verification_status = \'pending\', updated_at = NOW()
             WHERE id = ?',
            [$userId, $dealerId]
        );
        AuditLog::record(
            'polaris.dealer.claim_submitted',
            'polaris_dealer',
            (string) $dealerId,
            null,
            json_encode(['by' => $userId, 'evidence' => mb_substr($evidence, 0, 500)], JSON_THROW_ON_ERROR)
        );
    }

    public function approveClaim(int $brandId, int $dealerId, int $reviewerId): void
    {
        $dealer = Database::selectOne(
            'SELECT * FROM polaris_dealers WHERE id = ? AND brand_id = ? AND claim_status = \'pending\' AND deleted_at IS NULL LIMIT 1',
            [$dealerId, $brandId]
        );
        if ($dealer === null) {
            throw new RuntimeException('Pending dealer claim not found.');
        }
        Database::affecting(
            'UPDATE polaris_dealers SET claim_status = \'claimed\', verification_status = \'verified\', updated_at = NOW() WHERE id = ?',
            [$dealerId]
        );
        AuditLog::record(
            'polaris.dealer.claim_approved',
            'polaris_dealer',
            (string) $dealerId,
            null,
            json_encode(['by' => $reviewerId], JSON_THROW_ON_ERROR)
        );
    }

    /** @return list<array<string,mixed>> */
    public function pendingClaims(int $brandId): array
    {
        return Database::select(
            'SELECT d.*, u.email AS claimant_email
             FROM polaris_dealers d
             LEFT JOIN users u ON u.id = d.claimed_by_user_id
             WHERE d.brand_id = ? AND d.claim_status = \'pending\' AND d.deleted_at IS NULL
             ORDER BY d.updated_at DESC, d.created_at DESC
             LIMIT 100',
            [$brandId]
        );
    }
}
