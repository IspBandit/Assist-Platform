<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ServiceRequest extends Model
{
    protected static string $table = 'service_requests';
    protected static bool $softDeletes = true;

    /** Generate a unique, human-friendly reference such as VA-7F3K9Q. */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $reference = 'VA-' . $code;
        } while ((int) Database::scalar('SELECT COUNT(*) FROM service_requests WHERE reference = ?', [$reference]) > 0);

        return $reference;
    }

    public static function findByReference(string $reference): ?array
    {
        return Database::selectOne(
            'SELECT * FROM service_requests WHERE reference = ? AND deleted_at IS NULL',
            [$reference]
        );
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
            $where[] = '(sr.reference LIKE ? OR sr.title LIKE ? OR sr.contact_email LIKE ? OR sr.contact_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*) FROM service_requests sr' . $clause, $params);
        $rows = Database::select(
            'SELECT sr.id, sr.reference, sr.title, sr.status, sr.urgency, sr.is_spam, sr.safety_concern, '
            . 'sr.created_at, t.name AS town_name, c.name AS category_name '
            . 'FROM service_requests sr '
            . 'LEFT JOIN towns t ON t.id = sr.town_id '
            . 'LEFT JOIN service_categories c ON c.id = sr.primary_category_id'
            . $clause . ' ORDER BY sr.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function adminFind(int $id): ?array
    {
        return Database::selectOne(
            'SELECT sr.*, t.name AS town_name, r.name AS region_name, s.name AS state_name, '
            . 'c.name AS category_name FROM service_requests sr '
            . 'LEFT JOIN towns t ON t.id = sr.town_id '
            . 'LEFT JOIN regions r ON r.id = sr.region_id '
            . 'LEFT JOIN states s ON s.id = sr.state_id '
            . 'LEFT JOIN service_categories c ON c.id = sr.primary_category_id '
            . 'WHERE sr.id = ? AND sr.deleted_at IS NULL',
            [$id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function forCustomer(int $customerId): array
    {
        return Database::select(
            'SELECT sr.id, sr.reference, sr.title, sr.status, sr.urgency, sr.created_at, t.name AS town_name '
            . 'FROM service_requests sr LEFT JOIN towns t ON t.id = sr.town_id '
            . 'WHERE sr.customer_id = ? AND sr.deleted_at IS NULL ORDER BY sr.created_at DESC',
            [$customerId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function images(int $requestId): array
    {
        return Database::select(
            'SELECT * FROM service_request_images WHERE request_id = ? ORDER BY sort_order, id',
            [$requestId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function statusHistory(int $requestId): array
    {
        return Database::select(
            'SELECT h.*, u.name AS changed_by_name FROM service_request_status_history h '
            . 'LEFT JOIN users u ON u.id = h.changed_by WHERE h.request_id = ? ORDER BY h.id DESC',
            [$requestId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function notes(int $requestId): array
    {
        return Database::select(
            'SELECT n.*, u.name AS author_name FROM service_request_notes n '
            . 'LEFT JOIN users u ON u.id = n.author_id WHERE n.request_id = ? ORDER BY n.id DESC',
            [$requestId]
        );
    }
}
