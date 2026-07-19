<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
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
                        "SELECT id AS user_id, email, name FROM users "
                        . "WHERE status = 'active' AND deleted_at IS NULL AND marketing_opt_in = 1 AND email <> ''"
                    );
                    break;

                case 'providers':
                    $rows = self::activeProviders();
                    break;

                case 'customers_open':
                    $rows = Database::select(
                        "SELECT DISTINCT NULL AS user_id, contact_email AS email, contact_name AS name "
                        . "FROM service_requests WHERE deleted_at IS NULL AND is_spam = 0 "
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
                        Database::select(
                            "SELECT DISTINCT p.id AS provider_id, COALESCE(NULLIF(p.email,''), NULLIF(p.public_email,'')) AS email, p.business_name AS name "
                            . "FROM providers p LEFT JOIN provider_service_areas a ON a.provider_id = p.id AND a.town_id = ? "
                            . "WHERE p.status = 'active' AND p.deleted_at IS NULL AND (p.base_town_id = ? OR a.id IS NOT NULL)",
                            [$townId, $townId]
                        )
                    );
                    break;

                case 'region':
                    if ($regionId === null) {
                        return [];
                    }
                    $rows = array_merge(
                        self::optedInRequests('region_id', $regionId),
                        Database::select(
                            "SELECT DISTINCT p.id AS provider_id, COALESCE(NULLIF(p.email,''), NULLIF(p.public_email,'')) AS email, p.business_name AS name "
                            . "FROM providers p LEFT JOIN provider_service_areas a ON a.provider_id = p.id AND a.region_id = ? "
                            . "WHERE p.status = 'active' AND p.deleted_at IS NULL AND (p.region_id = ? OR a.id IS NOT NULL)",
                            [$regionId, $regionId]
                        )
                    );
                    break;

                case 'category':
                    if ($categoryId === null) {
                        return [];
                    }
                    $rows = array_merge(
                        self::optedInRequests('primary_category_id', $categoryId),
                        Database::select(
                            "SELECT DISTINCT p.id AS provider_id, COALESCE(NULLIF(p.email,''), NULLIF(p.public_email,'')) AS email, p.business_name AS name "
                            . "FROM providers p INNER JOIN provider_services s ON s.provider_id = p.id AND s.category_id = ? "
                            . "WHERE p.status = 'active' AND p.deleted_at IS NULL",
                            [$categoryId]
                        )
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
    private static function activeProviders(): array
    {
        return Database::select(
            "SELECT id AS provider_id, COALESCE(NULLIF(email,''), NULLIF(public_email,'')) AS email, business_name AS name "
            . "FROM providers WHERE status = 'active' AND deleted_at IS NULL"
        );
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
