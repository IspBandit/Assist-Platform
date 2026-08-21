<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Read-only brand provider categories for Assist RIC Directory (Increment I).
 *
 * Exposes brand_provider_categories only (ADR 0016). Not service_categories.
 */
final class AdminApiCategoryService
{
    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    public function list(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('brand_provider_categories');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return [
                'items' => [],
                'meta' => [
                    'count' => 0,
                    'limit' => $limit,
                    'has_more' => false,
                    'next_cursor' => null,
                    'sparse' => true,
                    'source' => 'brand_provider_categories_missing',
                    'brand_id' => AdminApiBrandScope::brandId(),
                    'writable' => false,
                ],
                'links' => ['next' => null],
            ];
        }

        $brandId = AdminApiBrandScope::brandId();
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['brand_id = ?'];
        $params = [$brandId];

        $activeRaw = $request->query('active');
        if ($activeRaw === null || $activeRaw === '') {
            $where[] = 'is_active = 1';
        } else {
            $active = strtolower(trim((string) $activeRaw));
            if (!in_array($active, ['0', '1', 'true', 'false', 'all'], true)) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['active' => ['active must be 1, 0, true, false, or all.']]
                );
            }
            if ($active === 'all') {
                // no filter
            } elseif (in_array($active, ['1', 'true'], true)) {
                $where[] = 'is_active = 1';
            } else {
                $where[] = 'is_active = 0';
            }
        }

        $search = trim((string) $request->query('q', $request->query('search', '')));
        if ($search !== '') {
            $where[] = '(name LIKE ? OR category_key LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, brand_id, category_key, name, description, sort_order, is_active, created_at, updated_at '
            . 'FROM brand_provider_categories WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'brand_id' => (int) $row['brand_id'],
                'category_key' => (string) ($row['category_key'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ], $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'brand_id' => $brandId,
                'taxonomy' => 'brand_provider_categories',
                'writable' => false,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }
}
