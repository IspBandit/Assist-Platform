<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Platform\Brand\BrandContext;
use Throwable;

/**
 * Resolves the recipient list for a targeted broadcast. Customer audiences are
 * limited to people who opted in to updates (marketing_opt_in); provider
 * audiences are treated as operational business contacts. Results are
 * de-duplicated by lower-cased email address.
 */
final class BroadcastAudience
{
    public const TYPES = [
        'all'            => 'Everyone opted in',
        'providers'      => 'All active providers',
        'customers_open' => 'Customers with open requests',
        'town'           => 'By town (customers + local providers)',
        'region'         => 'By region (customers + providers)',
        'category'       => 'By service category (customers + providers)',
    ];

    /**
     * @return array<int,array{user_id:?int,email:string,name:string}>
     */
    public static function resolve(string $type, ?int $townId, ?int $regionId, ?int $categoryId): array
    {
        $rows = [];
        try {
            switch ($type) {
                case 'all':
                    $rows = Database::select(
                        "SELECT DISTINCT u.id AS user_id,u.email,u.name FROM users u "
                        . "INNER JOIN user_brand_profiles bp ON bp.user_id=u.id AND bp.brand_id=? AND bp.status='active' AND bp.deleted_at IS NULL "
                        . "WHERE u.status='active' AND u.deleted_at IS NULL AND u.marketing_opt_in=1 AND u.email<>''",
                        [self::brandId()]
                    );
                    break;

                case 'providers':
                    $rows = self::activeProviders();
                    break;

                case 'customers_open':
                    $rows = Database::select(
                        "SELECT DISTINCT NULL AS user_id, contact_email AS email, contact_name AS name "
                        . "FROM service_requests WHERE deleted_at IS NULL AND is_spam = 0 "
                        . "AND marketing_opt_in=1 "
                        . "AND status IN ('open','matching','provider_interested','information_requested','offered_appointment','added_to_run') "
                        . "AND contact_email <> ''"
                    );
                    break;

                case 'town':
                    if ($townId === null) {
                        return [];
                    }
                    $rows = array_merge(
                        self::optedInRequests('town_id', $townId),
                        self::activeProviders($townId)
                    );
                    break;

                case 'region':
                    if ($regionId === null) {
                        return [];
                    }
                    $rows = array_merge(
                        self::optedInRequests('region_id', $regionId),
                        self::activeProviders(null, $regionId)
                    );
                    break;

                case 'category':
                    if ($categoryId === null) {
                        return [];
                    }
                    $rows = array_merge(
                        self::optedInRequests('primary_category_id', $categoryId),
                        self::activeProviders(null, null, $categoryId)
                    );
                    break;
            }
        } catch (Throwable) {
            return [];
        }

        return self::dedupe($rows);
    }

    /** Count without materialising names (used for the compose preview). */
    public static function count(string $type, ?int $townId, ?int $regionId, ?int $categoryId): int
    {
        return count(self::resolve($type, $townId, $regionId, $categoryId));
    }

    /** @return array<int,array<string,mixed>> */
    private static function activeProviders(?int $townId = null, ?int $regionId = null, ?int $categoryId = null): array
    {
        $joins = " INNER JOIN provider_brand_listings bl ON bl.provider_id=p.id AND bl.brand_id=? AND bl.status='active' AND bl.deleted_at IS NULL";
        $params = [self::brandId()];
        $where = '';
        if ($townId !== null) {
            $joins .= ' LEFT JOIN provider_service_areas a ON a.provider_id=p.id AND a.town_id=?';
            $params[] = $townId;
            $where = ' AND (p.base_town_id=? OR a.id IS NOT NULL)';
            $params[] = $townId;
        } elseif ($regionId !== null) {
            $joins .= ' LEFT JOIN provider_service_areas a ON a.provider_id=p.id AND a.region_id=?';
            $params[] = $regionId;
            $where = ' AND (p.region_id=? OR a.id IS NOT NULL)';
            $params[] = $regionId;
        } elseif ($categoryId !== null) {
            $joins .= ' INNER JOIN provider_services s ON s.provider_id=p.id AND s.category_id=?';
            $params[] = $categoryId;
        }
        return Database::select(
            "SELECT DISTINCT p.id AS provider_id,COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,'')) AS email,p.business_name AS name "
            . "FROM providers p{$joins} WHERE p.status='active' AND p.deleted_at IS NULL AND p.marketing_opt_in=1{$where}",
            $params
        );
    }

    private static function brandId(): int
    {
        return BrandContext::current()->databaseId();
    }

    /** @return array<int,array<string,mixed>> */
    private static function optedInRequests(string $column, int $value): array
    {
        return Database::select(
            "SELECT DISTINCT NULL AS user_id, contact_email AS email, contact_name AS name "
            . "FROM service_requests WHERE deleted_at IS NULL AND is_spam = 0 AND marketing_opt_in = 1 "
            . "AND {$column} = ? AND contact_email <> ''",
            [$value]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{user_id:?int,email:string,name:string}>
     */
    private static function dedupe(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = [
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] ?: null : null,
                'email'   => $email,
                'name'    => trim((string) ($row['name'] ?? '')),
            ];
        }
        return $out;
    }
}
