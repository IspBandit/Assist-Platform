<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Services\FeatureFlag;

/**
 * Read-only feature flag catalogue for Assist RIC Operations.
 * Writes remain PHP website admin only (`feature_flags.manage`).
 */
final class AdminApiFeatureFlagService
{
    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>}
     */
    public function list(): array
    {
        $rows = FeatureFlag::all();
        $items = array_map(static fn (array $row): array => [
            'key' => (string) ($row['flag_key'] ?? ''),
            'enabled' => (bool) ($row['is_enabled'] ?? false),
            'brand_scope' => 'platform',
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ], $rows);

        return [
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'brand_scope' => 'platform',
                'sparse' => $items === [],
                'writable' => false,
            ],
        ];
    }
}
