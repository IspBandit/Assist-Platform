<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use Throwable;

/**
 * Model-page dealer contact handoff (mailto / website only). No outbound mailer.
 */
final class DealerEnquiryService
{
    /**
     * Published linked dealers that have at least one contact channel.
     *
     * @return list<array{
     *   id: int,
     *   trading_name: string,
     *   locality: string,
     *   state_abbr: string,
     *   email: ?string,
     *   website_url: ?string,
     *   mailto_url: ?string,
     *   website_handoff: ?string
     * }>
     */
    public function listForManufacturer(int $brandId, int $manufacturerId): array
    {
        if ($brandId < 1 || $manufacturerId < 1) {
            return [];
        }
        try {
            $rows = Database::select(
                'SELECT d.id, d.trading_name, d.locality, d.state_abbr, d.email, d.website_url, d.publication_status
                 FROM polaris_manufacturer_dealers md
                 INNER JOIN polaris_dealers d ON d.id = md.dealer_id
                 WHERE md.manufacturer_id = ?
                   AND d.brand_id = ?
                   AND d.publication_status = \'published\'
                   AND d.lifecycle_status = \'active\'
                   AND d.deleted_at IS NULL
                 ORDER BY md.is_primary DESC, d.trading_name ASC
                 LIMIT 20',
                [$manufacturerId, $brandId]
            );
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $shaped = self::shapeHandoff($row);
            if ($shaped !== null) {
                $out[] = $shaped;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{
     *   id: int,
     *   trading_name: string,
     *   locality: string,
     *   state_abbr: string,
     *   email: ?string,
     *   website_url: ?string,
     *   mailto_url: ?string,
     *   website_handoff: ?string
     * }|null
     */
    public static function shapeHandoff(array $row): ?array
    {
        if ((string) ($row['publication_status'] ?? 'published') !== 'published') {
            return null;
        }
        $email = trim((string) ($row['email'] ?? ''));
        $website = trim((string) ($row['website_url'] ?? ''));
        if ($email === '' && $website === '') {
            return null;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = '';
        }
        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $website = '';
        }
        if ($email === '' && $website === '') {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'trading_name' => (string) ($row['trading_name'] ?? ''),
            'locality' => (string) ($row['locality'] ?? ''),
            'state_abbr' => (string) ($row['state_abbr'] ?? ''),
            'email' => $email !== '' ? $email : null,
            'website_url' => $website !== '' ? $website : null,
            'mailto_url' => $email !== '' ? 'mailto:' . $email : null,
            'website_handoff' => $website !== '' ? $website : null,
        ];
    }

    /**
     * Resolve a published dealer for handoff redirect (brand-scoped).
     *
     * @return array{id:int,email:?string,website_url:?string}|null
     */
    public function findPublishedDealer(int $brandId, int $dealerId): ?array
    {
        if ($brandId < 1 || $dealerId < 1) {
            return null;
        }
        try {
            $row = Database::selectOne(
                'SELECT id, email, website_url, publication_status
                 FROM polaris_dealers
                 WHERE id = ? AND brand_id = ?
                   AND publication_status = \'published\'
                   AND lifecycle_status = \'active\'
                   AND deleted_at IS NULL
                 LIMIT 1',
                [$dealerId, $brandId]
            );
        } catch (Throwable) {
            return null;
        }
        if ($row === null) {
            return null;
        }
        $shaped = self::shapeHandoff($row + ['trading_name' => '', 'locality' => '', 'state_abbr' => '']);
        if ($shaped === null) {
            return null;
        }
        return [
            'id' => $shaped['id'],
            'email' => $shaped['email'],
            'website_url' => $shaped['website_url'],
        ];
    }
}
