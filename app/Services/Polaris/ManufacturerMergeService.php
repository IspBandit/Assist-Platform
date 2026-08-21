<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use App\Services\AuditLog;
use RuntimeException;

/**
 * Merge duplicate manufacturers into a survivor (models reassigned; absorbed archived).
 */
final class ManufacturerMergeService
{
    public function merge(int $brandId, int $survivorId, int $absorbedId, int $actorId, ?string $notes = null): void
    {
        if ($survivorId === $absorbedId) {
            throw new RuntimeException('Cannot merge a manufacturer into itself.');
        }
        $survivor = Database::selectOne(
            'SELECT * FROM polaris_manufacturers WHERE id = ? AND brand_id = ? AND deleted_at IS NULL LIMIT 1',
            [$survivorId, $brandId]
        );
        $absorbed = Database::selectOne(
            'SELECT * FROM polaris_manufacturers WHERE id = ? AND brand_id = ? AND deleted_at IS NULL LIMIT 1',
            [$absorbedId, $brandId]
        );
        if ($survivor === null || $absorbed === null) {
            throw new RuntimeException('Manufacturer not found for merge.');
        }

        Database::affecting(
            'UPDATE polaris_rv_models SET manufacturer_id = ?, updated_at = NOW() WHERE manufacturer_id = ? AND brand_id = ?',
            [$survivorId, $absorbedId, $brandId]
        );

        try {
            $links = Database::select(
                'SELECT dealer_id, brands_represented, is_primary FROM polaris_manufacturer_dealers WHERE manufacturer_id = ?',
                [$absorbedId]
            );
            foreach ($links as $link) {
                Database::affecting(
                    'INSERT IGNORE INTO polaris_manufacturer_dealers (manufacturer_id, dealer_id, brands_represented, is_primary, created_at)
                     VALUES (?, ?, ?, ?, NOW())',
                    [$survivorId, (int) $link['dealer_id'], $link['brands_represented'], (int) $link['is_primary']]
                );
            }
            Database::affecting('DELETE FROM polaris_manufacturer_dealers WHERE manufacturer_id = ?', [$absorbedId]);
        } catch (\Throwable) {
            // Dealer tables may be absent before migration 096.
        }

        $absorbedSlug = (string) $absorbed['slug'];
        Database::affecting(
            'UPDATE polaris_manufacturers SET lifecycle_status = \'recycle_bin\', deleted_at = NOW(),
                archival_reason = ?, publication_status = \'unpublished\', updated_at = NOW()
             WHERE id = ?',
            ['Merged into manufacturer #' . $survivorId . ($notes ? ': ' . $notes : ''), $absorbedId]
        );

        try {
            Database::insert(
                'INSERT INTO polaris_manufacturer_merges
                    (brand_id, survivor_id, absorbed_id, absorbed_slug, notes, merged_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$brandId, $survivorId, $absorbedId, $absorbedSlug, $notes, $actorId]
            );
        } catch (\Throwable) {
            // Merge audit table may be absent before migration 096.
        }

        AuditLog::record(
            'polaris.manufacturer.merged',
            'polaris_manufacturer',
            (string) $survivorId,
            json_encode(['absorbed_id' => $absorbedId, 'absorbed_slug' => $absorbedSlug], JSON_THROW_ON_ERROR),
            json_encode(['notes' => $notes, 'by' => $actorId], JSON_THROW_ON_ERROR)
        );
    }
}
