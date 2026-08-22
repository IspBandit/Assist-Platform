<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use App\Services\AuditLog;
use RuntimeException;

/**
 * Claim-first manufacturer onboarding. Encourages matching existing profiles.
 */
final class ManufacturerClaimService
{
    /** @return array<int,array<string,mixed>> */
    public function searchForClaim(int $brandId, string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->unclaimed($brandId, $limit);
        }
        $like = '%' . $query . '%';
        return Database::select(
            'SELECT id, trading_name, legal_name, slug, claim_status, verification_status, website_url, is_demo
             FROM polaris_manufacturers
             WHERE brand_id = ? AND deleted_at IS NULL AND lifecycle_status = \'active\'
               AND (trading_name LIKE ? OR legal_name LIKE ? OR slug LIKE ?)
             ORDER BY claim_status = \'unclaimed\' DESC, trading_name ASC
             LIMIT ' . max(1, min(50, $limit)),
            [$brandId, $like, $like, $like]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function unclaimed(int $brandId, int $limit = 20): array
    {
        return Database::select(
            'SELECT id, trading_name, legal_name, slug, claim_status, verification_status, website_url, is_demo
             FROM polaris_manufacturers
             WHERE brand_id = ? AND deleted_at IS NULL AND lifecycle_status = \'active\' AND claim_status = \'unclaimed\'
             ORDER BY trading_name ASC LIMIT ' . max(1, min(50, $limit)),
            [$brandId]
        );
    }

    public function submitClaim(int $brandId, int $manufacturerId, int $userId, string $email, string $evidence): int
    {
        $mfr = Database::selectOne(
            'SELECT * FROM polaris_manufacturers WHERE id = ? AND brand_id = ? AND deleted_at IS NULL LIMIT 1',
            [$manufacturerId, $brandId]
        );
        if ($mfr === null) {
            throw new RuntimeException('Manufacturer profile not found. Search existing profiles before creating a new one.');
        }
        if (in_array((string) $mfr['claim_status'], ['claimed', 'pending'], true)) {
            throw new RuntimeException('This manufacturer is already claimed or has a pending claim.');
        }

        $existing = Database::selectOne(
            'SELECT id FROM polaris_manufacturer_claims WHERE manufacturer_id = ? AND user_id = ? AND status = \'pending\' LIMIT 1',
            [$manufacturerId, $userId]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $id = Database::insert(
            'INSERT INTO polaris_manufacturer_claims
                (brand_id, manufacturer_id, user_id, status, authority_evidence, contact_email, created_at)
             VALUES (?, ?, ?, \'pending\', ?, ?, NOW())',
            [$brandId, $manufacturerId, $userId, $evidence, $email]
        );
        Database::affecting(
            'UPDATE polaris_manufacturers SET claim_status = \'pending\', updated_at = NOW() WHERE id = ?',
            [$manufacturerId]
        );
        AuditLog::record('polaris.manufacturer.claim_submitted', 'polaris_manufacturer', (string) $manufacturerId, null, (string) $id);
        return $id;
    }

    public function approveClaim(int $brandId, int $claimId, int $reviewerId, ?string $notes = null): void
    {
        $claim = Database::selectOne(
            'SELECT * FROM polaris_manufacturer_claims WHERE id = ? AND brand_id = ? AND status = \'pending\' LIMIT 1',
            [$claimId, $brandId]
        );
        if ($claim === null) {
            throw new RuntimeException('Claim not found.');
        }
        Database::affecting(
            'UPDATE polaris_manufacturer_claims SET status = \'approved\', reviewer_user_id = ?, reviewed_at = NOW(), review_notes = ?, updated_at = NOW() WHERE id = ?',
            [$reviewerId, $notes, $claimId]
        );
        Database::affecting(
            'UPDATE polaris_manufacturers SET claim_status = \'claimed\', verification_status = \'verified\',
                claimed_by_user_id = ?, claimed_at = NOW(), updated_at = NOW() WHERE id = ?',
            [(int) $claim['user_id'], (int) $claim['manufacturer_id']]
        );
        AuditLog::record('polaris.manufacturer.claim_approved', 'polaris_manufacturer_claim', (string) $claimId, null, $notes);
    }

    public function rejectClaim(int $brandId, int $claimId, int $reviewerId, ?string $notes = null): void
    {
        $claim = Database::selectOne(
            'SELECT * FROM polaris_manufacturer_claims WHERE id = ? AND brand_id = ? AND status = \'pending\' LIMIT 1',
            [$claimId, $brandId]
        );
        if ($claim === null) {
            throw new RuntimeException('Claim not found.');
        }
        Database::affecting(
            'UPDATE polaris_manufacturer_claims SET status = \'rejected\', reviewer_user_id = ?, reviewed_at = NOW(), review_notes = ?, updated_at = NOW() WHERE id = ?',
            [$reviewerId, $notes, $claimId]
        );
        Database::affecting(
            'UPDATE polaris_manufacturers SET claim_status = \'unclaimed\', updated_at = NOW() WHERE id = ? AND claim_status = \'pending\'',
            [(int) $claim['manufacturer_id']]
        );
        AuditLog::record('polaris.manufacturer.claim_rejected', 'polaris_manufacturer_claim', (string) $claimId, null, $notes);
    }

    /** @return array<string,mixed>|null */
    public function claimedManufacturerForUser(int $brandId, int $userId): ?array
    {
        return Database::selectOne(
            'SELECT * FROM polaris_manufacturers
             WHERE brand_id = ? AND claimed_by_user_id = ? AND claim_status = \'claimed\' AND deleted_at IS NULL
             LIMIT 1',
            [$brandId, $userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingClaims(int $brandId): array
    {
        return Database::select(
            'SELECT c.*, m.trading_name, m.slug, u.name AS user_name, u.email AS user_email
             FROM polaris_manufacturer_claims c
             INNER JOIN polaris_manufacturers m ON m.id = c.manufacturer_id
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.brand_id = ? AND c.status = \'pending\'
             ORDER BY c.created_at ASC',
            [$brandId]
        );
    }
}
