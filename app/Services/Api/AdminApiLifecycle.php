<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Maps legacy provider/stay columns to Admin API lifecycle values (PHASE1 §5).
 */
final class AdminApiLifecycle
{
    /** @var list<string> */
    public const PROVIDER_STATUSES = ['draft', 'pending', 'active', 'suspended', 'rejected'];

    /** @var list<string> */
    public const STAY_STATUSES = ['draft', 'pending', 'active', 'suspended', 'rejected'];

    /** @var list<string> */
    public const LIFECYCLE_VALUES = [
        'draft',
        'pending_review',
        'published',
        'unpublished',
        'inactive',
        'rejected',
    ];

    /**
     * @param array<string,mixed> $row Provider joined with brand listing columns when available.
     */
    public static function forProvider(array $row): string
    {
        if (($row['deleted_at'] ?? null) !== null) {
            return 'deleted';
        }

        $status = (string) ($row['status'] ?? '');
        return match ($status) {
            'draft' => 'draft',
            'pending' => 'pending_review',
            'rejected' => 'rejected',
            'suspended' => 'inactive',
            'active' => self::providerActiveLifecycle($row),
            default => $status !== '' ? $status : 'draft',
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function forStay(array $row): string
    {
        if (($row['deleted_at'] ?? null) !== null) {
            return 'deleted';
        }

        $status = (string) ($row['status'] ?? '');
        return match ($status) {
            'draft' => 'draft',
            'pending' => 'pending_review',
            'rejected' => 'rejected',
            'suspended' => 'inactive',
            'active' => ((int) ($row['public_page_enabled'] ?? 0)) === 1 ? 'published' : 'unpublished',
            default => $status !== '' ? $status : 'draft',
        };
    }

    /**
     * @return array{clause:string,params:list<mixed>}|null
     */
    public static function providerFilterClause(string $filter): ?array
    {
        $filter = strtolower(trim($filter));
        if ($filter === '') {
            return null;
        }

        if (in_array($filter, self::PROVIDER_STATUSES, true)) {
            return ['clause' => 'p.status = ?', 'params' => [$filter]];
        }

        return match ($filter) {
            'draft' => ['clause' => 'p.status = ?', 'params' => ['draft']],
            'pending_review' => ['clause' => 'p.status = ?', 'params' => ['pending']],
            'rejected' => ['clause' => 'p.status = ?', 'params' => ['rejected']],
            'inactive' => ['clause' => 'p.status = ?', 'params' => ['suspended']],
            'published' => [
                'clause' => "p.status = 'active' AND pbl.status = 'active' AND pbl.search_visible = 1",
                'params' => [],
            ],
            'unpublished' => [
                'clause' => "p.status = 'active' AND (pbl.status != 'active' OR pbl.search_visible = 0)",
                'params' => [],
            ],
            default => null,
        };
    }

    /**
     * @return array{clause:string,params:list<mixed>}|null
     */
    public static function stayFilterClause(string $filter): ?array
    {
        $filter = strtolower(trim($filter));
        if ($filter === '') {
            return null;
        }

        if (in_array($filter, self::STAY_STATUSES, true)) {
            return ['clause' => 'cp.status = ?', 'params' => [$filter]];
        }

        return match ($filter) {
            'draft' => ['clause' => 'cp.status = ?', 'params' => ['draft']],
            'pending_review' => ['clause' => 'cp.status = ?', 'params' => ['pending']],
            'rejected' => ['clause' => 'cp.status = ?', 'params' => ['rejected']],
            'inactive' => ['clause' => 'cp.status = ?', 'params' => ['suspended']],
            'published' => [
                'clause' => "cp.status = 'active' AND cp.public_page_enabled = 1",
                'params' => [],
            ],
            'unpublished' => [
                'clause' => "cp.status = 'active' AND cp.public_page_enabled = 0",
                'params' => [],
            ],
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function providerActiveLifecycle(array $row): string
    {
        $listingStatus = (string) ($row['listing_status'] ?? $row['brand_listing_status'] ?? 'active');
        $searchVisible = (int) ($row['search_visible'] ?? $row['brand_search_visible'] ?? 1);

        if ($listingStatus === 'active' && $searchVisible === 1) {
            return 'published';
        }

        return 'unpublished';
    }
}
