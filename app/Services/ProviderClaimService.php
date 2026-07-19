<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Services\EmailQueue;
use App\Services\FoundingGraphicService;
use RuntimeException;

/**
 * Self-serve and admin-initiated claim invites for unclaimed directory listings.
 */
final class ProviderClaimService
{
    public static function sendClaimInvite(int $providerId, string $email, ?int $adminId = null): string
    {
        $provider = Database::selectOne(
            'SELECT p.id, p.business_name, p.email, p.contact_name, p.slug, p.is_unclaimed, '
            . 'tw.name AS town_name, tw.is_launch_town, s.abbreviation AS state_abbr '
            . 'FROM providers p '
            . 'LEFT JOIN towns tw ON tw.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = tw.state_id '
            . 'WHERE p.id = ? AND p.deleted_at IS NULL',
            [$providerId]
        );
        if ($provider === null || empty($provider['is_unclaimed'])) {
            throw new RuntimeException('Provider is not an unclaimed listing.');
        }

        $email = strtolower(trim($email));
        if ($email === '') {
            $email = strtolower(trim((string) ($provider['email'] ?? '')));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required to send a claim invite.');
        }

        $token = bin2hex(random_bytes(32));
        $days = (int) config('security.provider_invite_expiry_days', 14);

        Database::query(
            'INSERT INTO provider_claim_tokens (provider_id, email, token_hash, expires_at, created_by, created_at) '
            . 'VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), ?, NOW())',
            [$providerId, $email, hash('sha256', $token), $days, $adminId]
        );

        $url = url('provider/claim/' . $token);
        $listingUrl = url('providers/' . (string) $provider['slug']);
        $services = Database::select(
            'SELECT sc.name FROM provider_services ps '
            . 'INNER JOIN service_categories sc ON sc.id = ps.category_id '
            . 'WHERE ps.provider_id = ? ORDER BY ps.is_inferred ASC, sc.name LIMIT 5',
            [$providerId]
        );
        $serviceNames = array_map(static fn (array $row): string => (string) $row['name'], $services);

        $launchTownName = null;
        if (!empty($provider['is_launch_town']) && !empty($provider['town_name'])) {
            $launchTownName = (string) $provider['town_name'];
            if (!empty($provider['state_abbr'])) {
                $launchTownName .= ', ' . $provider['state_abbr'];
            }
        }

        EmailQueue::queueTemplate('provider_claim_invite', $email, (string) $provider['business_name'], [
            'business_name'        => (string) $provider['business_name'],
            'greeting'             => self::greeting($provider),
            'town_line'            => self::townLine($provider),
            'services_line'        => self::servicesLine($serviceNames),
            'listing_url'          => $listingUrl,
            'action_url'           => $url,
            'site_url'             => url('/'),
            'expiry_days'          => (string) $days,
            'founding_offer_line'  => FoundingGraphicService::claimInviteOfferLine($launchTownName),
            'founding_offer_text'  => FoundingGraphicService::claimInviteOfferText($launchTownName),
        ]);

        return $url;
    }

    /** @return array<string,mixed>|null */
    public static function resolveToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $row = Database::selectOne(
            'SELECT t.*, p.business_name, p.slug, p.contact_name, p.is_unclaimed, p.base_town_id, '
            . 'tw.name AS town_name, tw.is_launch_town '
            . 'FROM provider_claim_tokens t '
            . 'INNER JOIN providers p ON p.id = t.provider_id '
            . 'LEFT JOIN towns tw ON tw.id = p.base_town_id '
            . 'WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND p.is_unclaimed = 1 AND p.deleted_at IS NULL',
            [hash('sha256', $token)]
        );
        return $row ?: null;
    }

    /**
     * Attach a user account to an existing unclaimed listing and activate it.
     */
    public static function claim(int $tokenId, int $providerId, int $userId, string $contactName): void
    {
        Database::query(
            'UPDATE providers SET user_id = ?, contact_name = COALESCE(NULLIF(contact_name, \'\'), ?), '
            . "is_unclaimed = 0, claimed_at = NOW(), status = 'pending', updated_at = NOW() "
            . 'WHERE id = ? AND is_unclaimed = 1',
            [$userId, $contactName, $providerId]
        );
        Database::query(
            'UPDATE provider_claim_tokens SET used_at = NOW() WHERE id = ?',
            [$tokenId]
        );
        User::assignRoleBySlug($userId, 'provider');
    }

    /** @param array<string,mixed> $provider */
    private static function greeting(array $provider): string
    {
        $contact = trim((string) ($provider['contact_name'] ?? ''));
        if ($contact !== '') {
            return $contact;
        }

        return 'there';
    }

    /** @param array<string,mixed> $provider */
    private static function townLine(array $provider): string
    {
        $town = trim((string) ($provider['town_name'] ?? ''));
        if ($town === '') {
            return '';
        }
        $state = trim((string) ($provider['state_abbr'] ?? ''));
        $label = $state !== '' ? $town . ', ' . $state : $town;

        return '<p style="margin:4px 0 0;font-size:.95rem;color:#5c6369">Based in ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /** @param array<int,string> $serviceNames */
    private static function servicesLine(array $serviceNames): string
    {
        if ($serviceNames === []) {
            return '';
        }

        $list = htmlspecialchars(implode(', ', $serviceNames), ENT_QUOTES, 'UTF-8');

        return '<p style="margin:6px 0 0;font-size:.9rem;color:#5c6369">Services on file: ' . $list . '</p>';
    }

    /**
     * Count unclaimed providers eligible for a bulk claim invite under the given admin filters.
     *
     * @param array<string,mixed> $filters Same keys as Provider::adminListing()
     * @return array{total:int,eligible:int,no_email:int,pending_invite:int}
     */
    public static function bulkInviteStats(array $filters): array
    {
        [$clause, $params, $joins] = self::bulkInviteFilterSql($filters, false);

        $total = (int) Database::scalar('SELECT COUNT(*) FROM providers p' . $joins . $clause, $params);

        $noEmail = (int) Database::scalar(
            'SELECT COUNT(*) FROM providers p' . $joins . $clause . " AND (p.email IS NULL OR TRIM(p.email) = '')",
            $params
        );

        [$eligibleClause, $eligibleParams, $eligibleJoins] = self::bulkInviteFilterSql($filters, true);
        $eligible = (int) Database::scalar('SELECT COUNT(*) FROM providers p' . $eligibleJoins . $eligibleClause, $eligibleParams);

        $pendingInvite = max(0, $total - $noEmail - $eligible);

        return [
            'total'          => $total,
            'eligible'       => $eligible,
            'no_email'       => $noEmail,
            'pending_invite' => $pendingInvite,
        ];
    }

    /**
     * Queue claim invites for a batch of unclaimed providers matching admin filters.
     *
     * @param array<string,mixed> $filters
     * @return array{sent:int,failed:int,next_offset:int,done:bool,errors:array<int,string>}
     */
    public static function runBulkInvites(array $filters, int $offset, int $limit, ?int $adminId = null): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        [$clause, $params, $joins] = self::bulkInviteFilterSql($filters, true);

        $rows = Database::select(
            'SELECT p.id FROM providers p' . $joins . $clause
            . ' ORDER BY p.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        $sent = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $row) {
            try {
                self::sendClaimInvite((int) $row['id'], '', $adminId);
                ++$sent;
            } catch (\Throwable $e) {
                ++$failed;
                if (count($errors) < 5) {
                    $errors[] = 'Provider #' . (int) $row['id'] . ': ' . $e->getMessage();
                }
            }
        }

        $batchCount = count($rows);
        $done = $batchCount < $limit;

        return [
            'sent'         => $sent,
            'failed'       => $failed,
            'next_offset'  => $offset + $batchCount,
            'done'         => $done,
            'errors'       => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>,2:string}
     */
    private static function bulkInviteFilterSql(array $filters, bool $eligibleOnly): array
    {
        $where = ['p.deleted_at IS NULL', 'p.is_unclaimed = 1'];
        $params = [];
        $joins = ' LEFT JOIN towns t ON t.id = p.base_town_id LEFT JOIN regions r ON r.id = p.region_id';

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.business_name LIKE ? OR p.email LIKE ? OR p.contact_name LIKE ? OR p.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $town = trim((string) ($filters['town'] ?? ''));
        if ($town !== '') {
            $where[] = 't.name LIKE ?';
            $params[] = '%' . $town . '%';
        }

        $categoryId = (int) ($filters['category'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM provider_services ps WHERE ps.provider_id = p.id AND ps.category_id = ?)';
            $params[] = $categoryId;
        }

        $stateId = (int) ($filters['state'] ?? 0);
        if ($stateId > 0) {
            $where[] = '(t.state_id = ? OR r.state_id = ?)';
            $params[] = $stateId;
            $params[] = $stateId;
        }

        if ($eligibleOnly) {
            $where[] = "p.email IS NOT NULL AND TRIM(p.email) != ''";
            $where[] = 'NOT EXISTS (SELECT 1 FROM provider_claim_tokens pct WHERE pct.provider_id = p.id AND pct.used_at IS NULL AND pct.expires_at > NOW())';
        }

        return [' WHERE ' . implode(' AND ', $where), $params, $joins];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function duplicateSuspects(int $limit = 100): array
    {
        return Database::select(
            'SELECT p1.id AS id_a, p1.business_name AS name_a, p1.phone AS phone_a, p1.slug AS slug_a, '
            . 'p2.id AS id_b, p2.business_name AS name_b, p2.phone AS phone_b, p2.slug AS slug_b, '
            . 'p1.is_unclaimed AS unclaimed_a, p2.is_unclaimed AS unclaimed_b '
            . 'FROM providers p1 '
            . 'INNER JOIN providers p2 ON p2.id > p1.id AND p2.deleted_at IS NULL '
            . 'WHERE p1.deleted_at IS NULL AND ( '
            . "(p1.phone IS NOT NULL AND p1.phone != '' AND p1.phone = p2.phone) "
            . "OR (p1.website IS NOT NULL AND p1.website != '' AND p1.website = p2.website) "
            . "OR (p1.business_name = p2.business_name AND p1.base_town_id = p2.base_town_id AND p1.base_town_id IS NOT NULL) "
            . ') ORDER BY p1.business_name LIMIT ' . max(1, min(200, $limit))
        );
    }
}
