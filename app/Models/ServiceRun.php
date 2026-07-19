<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ServiceRun extends Model
{
    protected static string $table = 'service_runs';
    protected static bool $softDeletes = true;

    /** Statuses shown publicly and open for joining. */
    public const PUBLIC_STATUSES = ['forming', 'confirmed', 'limited'];

    public static function uniqueSlug(string $source): string
    {
        $base = str_slug($source) ?: 'run';
        $slug = $base;
        $n = 1;
        while ((int) Database::scalar('SELECT COUNT(*) FROM service_runs WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public static function adminListing(?string $status, string $search, int $limit, int $offset): array
    {
        $where = ['sr.deleted_at IS NULL'];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'sr.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(sr.title LIKE ? OR p.business_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*) FROM service_runs sr LEFT JOIN providers p ON p.id = sr.provider_id' . $clause, $params);
        $rows = Database::select(
            'SELECT sr.id, sr.title, sr.slug, sr.status, sr.start_date, sr.appointments_total, sr.bookings_count, '
            . 'sr.is_public, p.business_name FROM service_runs sr '
            . 'LEFT JOIN providers p ON p.id = sr.provider_id'
            . $clause . ' ORDER BY sr.start_date IS NULL, sr.start_date, sr.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public static function forProvider(int $providerId): array
    {
        return Database::select(
            'SELECT id, title, slug, status, start_date, appointments_total, bookings_count, is_public '
            . 'FROM service_runs WHERE provider_id = ? AND deleted_at IS NULL ORDER BY start_date IS NULL, start_date, id DESC',
            [$providerId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function publicListing(?int $regionId = null, ?int $categoryId = null): array
    {
        $where = ["sr.is_public = 1", 'sr.deleted_at IS NULL', "sr.status IN ('forming','confirmed','limited')"];
        $params = [];
        $join = '';
        if ($categoryId !== null) {
            $join = ' INNER JOIN service_run_services rs ON rs.run_id = sr.id AND rs.category_id = ?';
            $params[] = $categoryId;
        }
        if ($regionId !== null) {
            $where[] = 'sr.region_id = ?';
            $params[] = $regionId;
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        return Database::select(
            'SELECT DISTINCT sr.id, sr.title, sr.slug, sr.status, sr.start_date, sr.end_date, sr.booking_deadline, '
            . 'sr.appointments_total, sr.bookings_count, sr.min_bookings, p.business_name, p.slug AS provider_slug, '
            . 'r.name AS region_name FROM service_runs sr '
            . 'LEFT JOIN providers p ON p.id = sr.provider_id '
            . 'LEFT JOIN regions r ON r.id = sr.region_id' . $join
            . $clause . ' ORDER BY sr.start_date IS NULL, sr.start_date',
            $params
        );
    }

    public static function findPublicBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT sr.*, p.business_name, p.slug AS provider_slug, p.is_verified, r.name AS region_name '
            . 'FROM service_runs sr LEFT JOIN providers p ON p.id = sr.provider_id '
            . 'LEFT JOIN regions r ON r.id = sr.region_id '
            . "WHERE sr.slug = ? AND sr.is_public = 1 AND sr.deleted_at IS NULL",
            [$slug]
        );
    }

    public static function adminFind(int $id): ?array
    {
        return Database::selectOne(
            'SELECT sr.*, p.business_name FROM service_runs sr '
            . 'LEFT JOIN providers p ON p.id = sr.provider_id WHERE sr.id = ? AND sr.deleted_at IS NULL',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function towns(int $runId): array
    {
        return Database::select(
            'SELECT srt.id, srt.town_id, srt.arrival_date, srt.sort_order, t.name AS town_name '
            . 'FROM service_run_towns srt JOIN towns t ON t.id = srt.town_id '
            . 'WHERE srt.run_id = ? ORDER BY srt.sort_order, t.name',
            [$runId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function services(int $runId): array
    {
        return Database::select(
            'SELECT rs.category_id, c.name, c.slug FROM service_run_services rs '
            . 'JOIN service_categories c ON c.id = rs.category_id WHERE rs.run_id = ? ORDER BY c.name',
            [$runId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function bookings(int $runId): array
    {
        return Database::select(
            'SELECT b.*, t.name AS town_name, sr.reference AS request_reference, u.name AS customer_name, u.email AS customer_email '
            . 'FROM service_run_bookings b '
            . 'LEFT JOIN towns t ON t.id = b.town_id '
            . 'LEFT JOIN service_requests sr ON sr.id = b.request_id '
            . 'LEFT JOIN customers c ON c.id = b.customer_id '
            . 'LEFT JOIN users u ON u.id = c.user_id '
            . 'WHERE b.run_id = ? ORDER BY b.id DESC',
            [$runId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function linkedRequests(int $runId): array
    {
        return Database::select(
            'SELECT sr.id, sr.reference, sr.title, sr.status, t.name AS town_name '
            . 'FROM service_run_requests rr JOIN service_requests sr ON sr.id = rr.request_id '
            . 'LEFT JOIN towns t ON t.id = sr.town_id WHERE rr.run_id = ? ORDER BY sr.created_at',
            [$runId]
        );
    }
}
