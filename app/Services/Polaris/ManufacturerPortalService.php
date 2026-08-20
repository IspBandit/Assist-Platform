<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use App\Services\AuditLog;
use App\Services\FileStorage;
use RuntimeException;

/**
 * Manufacturer portal write paths: profile, media, dealers, team.
 */
final class ManufacturerPortalService
{
    /** @param array<string,mixed> $input */
    public function updateProfile(int $brandId, int $manufacturerId, array $input, int $userId): void
    {
        $mfr = Database::selectOne(
            'SELECT * FROM polaris_manufacturers WHERE id = ? AND brand_id = ? AND deleted_at IS NULL LIMIT 1',
            [$manufacturerId, $brandId]
        );
        if ($mfr === null) {
            throw new RuntimeException('Manufacturer not found.');
        }
        $website = trim((string) ($input['website_url'] ?? ''));
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Website URL is invalid.');
        }
        Database::affecting(
            'UPDATE polaris_manufacturers SET description = ?, website_url = ?, manufacturing_location = ?,
                warranty_summary = ?, verification_status = \'pending\', updated_at = NOW()
             WHERE id = ?',
            [
                trim((string) ($input['description'] ?? '')) ?: null,
                $website !== '' ? mb_substr($website, 0, 500) : null,
                trim((string) ($input['manufacturing_location'] ?? '')) ?: null,
                trim((string) ($input['warranty_summary'] ?? '')) ?: null,
                $manufacturerId,
            ]
        );
        AuditLog::record(
            'polaris.manufacturer.profile_updated',
            'polaris_manufacturer',
            (string) $manufacturerId,
            null,
            json_encode(['by' => $userId], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param array<string,mixed> $file $_FILES entry
     */
    public function storeMedia(int $manufacturerId, array $file, string $mediaType, string $title, int $userId): int
    {
        if (!in_array($mediaType, ['brochure', 'floorplan', 'logo', 'hero', 'other'], true)) {
            $mediaType = 'other';
        }
        $stored = FileStorage::storeUpload(
            $file,
            'polaris_media',
            [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            10 * 1024 * 1024
        );
        return Database::insert(
            'INSERT INTO polaris_manufacturer_media
                (manufacturer_id, media_type, title, storage_path, original_filename, mime_type, byte_size, review_status, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, NOW())',
            [
                $manufacturerId,
                $mediaType,
                mb_substr($title !== '' ? $title : $stored['original_name'], 0, 190),
                $stored['stored_name'],
                $stored['original_name'],
                $stored['mime_type'],
                $stored['file_size'],
                $userId,
            ]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listMedia(int $manufacturerId): array
    {
        return Database::select(
            'SELECT * FROM polaris_manufacturer_media WHERE manufacturer_id = ? ORDER BY created_at DESC LIMIT 100',
            [$manufacturerId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listLinkedDealers(int $manufacturerId): array
    {
        return Database::select(
            'SELECT d.*, md.is_primary, md.brands_represented
             FROM polaris_manufacturer_dealers md
             INNER JOIN polaris_dealers d ON d.id = md.dealer_id
             WHERE md.manufacturer_id = ? AND d.deleted_at IS NULL
             ORDER BY md.is_primary DESC, d.trading_name ASC',
            [$manufacturerId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function searchableDealers(int $brandId, string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return Database::select(
                'SELECT * FROM polaris_dealers WHERE brand_id = ? AND deleted_at IS NULL AND publication_status = \'published\' ORDER BY trading_name ASC LIMIT 40',
                [$brandId]
            );
        }
        return Database::select(
            'SELECT * FROM polaris_dealers WHERE brand_id = ? AND deleted_at IS NULL
               AND (trading_name LIKE ? OR locality LIKE ?)
             ORDER BY trading_name ASC LIMIT 40',
            [$brandId, '%' . $q . '%', '%' . $q . '%']
        );
    }

    public function linkDealer(int $manufacturerId, int $dealerId, ?string $brandsRepresented = null): void
    {
        Database::affecting(
            'INSERT IGNORE INTO polaris_manufacturer_dealers (manufacturer_id, dealer_id, brands_represented, created_at)
             VALUES (?, ?, ?, NOW())',
            [$manufacturerId, $dealerId, $brandsRepresented]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listTeam(int $manufacturerId): array
    {
        return Database::select(
            'SELECT t.*, u.email, u.first_name, u.last_name
             FROM polaris_manufacturer_team t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.manufacturer_id = ?
             ORDER BY t.role_label ASC, u.email ASC',
            [$manufacturerId]
        );
    }

    public function addTeamMember(int $manufacturerId, int $userId, string $role = 'editor'): void
    {
        if (!in_array($role, ['owner', 'editor', 'viewer'], true)) {
            $role = 'editor';
        }
        Database::affecting(
            'INSERT INTO polaris_manufacturer_team (manufacturer_id, user_id, role_label, created_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE role_label = VALUES(role_label)',
            [$manufacturerId, $userId, $role]
        );
    }
}
