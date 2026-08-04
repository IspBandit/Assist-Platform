<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Exceptions\AdminApiException;

/**
 * Admin API scope catalog and validation (CORE-011 Increment 3).
 */
final class AdminApiScopes
{
    /** @var list<string> */
    public const ALL = [
        'providers:read',
        'providers:write',
        'stays:read',
        'stays:write',
        'claims:read',
        'claims:write',
        'corrections:read',
        'corrections:write',
        'duplicates:read',
        'duplicates:merge',
        'datasets:read',
        'datasets:write',
        'facilities:read',
        'facilities:write',
        'drafts:read',
        'drafts:write',
        'drafts:approve',
        'imports:read',
        'imports:write',
        'sync:read',
        'analytics:read',
        'ai:read',
        'audit:read',
        'lifecycle:write',
        'recycle_bin:restore',
        'recycle_bin:purge',
        'service_accounts:admin',
        'users:admin',
        'billing:admin',
        'mfa:verify',
    ];

    /** @var list<string> */
    public const DEFAULT_SERVICE = [
        'providers:read',
        'stays:read',
        'claims:read',
        'datasets:read',
        'facilities:read',
        'drafts:read',
        'drafts:write',
        'imports:read',
        'imports:write',
        'sync:read',
        'analytics:read',
        'audit:read',
    ];

    /**
     * Least-privilege scopes for Assist RIC live sync (DATA-011).
     *
     * @var list<string>
     */
    public const RIC_SERVICE = [
        'providers:read',
        'stays:read',
        'claims:read',
        'corrections:read',
        'duplicates:read',
        'datasets:read',
        'facilities:read',
        'drafts:read',
        'drafts:write',
        'imports:read',
        'imports:write',
        'sync:read',
        'analytics:read',
        'ai:read',
        'audit:read',
        'recycle_bin:restore',
    ];

    /** @var list<string> */
    public const NEVER_SERVICE = [
        'recycle_bin:purge',
        'service_accounts:admin',
        'users:admin',
        'billing:admin',
        'duplicates:merge',
        'drafts:approve',
        'mfa:verify',
    ];

    /**
     * @param list<string>|mixed $scopes
     * @return list<string>
     */
    public static function normalize(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [$scopes];
        }
        if (!is_array($scopes)) {
            return [];
        }

        $normalized = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                continue;
            }
            $scope = trim($scope);
            if ($scope === '' || !in_array($scope, self::ALL, true)) {
                continue;
            }
            $normalized[$scope] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param list<string> $scopes
     */
    public static function rejectForbiddenForService(array $scopes): void
    {
        $forbidden = array_values(array_intersect($scopes, self::NEVER_SERVICE));
        if ($forbidden !== []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['scopes' => ['Service accounts cannot receive scopes: ' . implode(', ', $forbidden) . '.']]
            );
        }
    }

    /**
     * @param list<string> $requested
     * @param list<string> $granted
     * @return list<string>
     */
    public static function subset(array $requested, array $granted): array
    {
        $requested = self::normalize($requested);
        $granted = self::normalize($granted);
        if ($requested === []) {
            return $granted;
        }

        $invalid = array_values(array_diff($requested, $granted));
        if ($invalid !== []) {
            throw new AdminApiException(
                403,
                'forbidden',
                'Requested scopes exceed the granted scope set.'
            );
        }

        return $requested;
    }

    /** @return array<string,array{human:bool,service:bool,description:string}> */
    public static function catalog(): array
    {
        $serviceAllowed = array_flip(array_diff(self::ALL, self::NEVER_SERVICE));

        $descriptions = [
            'providers:read' => 'List and read providers',
            'providers:write' => 'Create and update providers',
            'stays:read' => 'List and read stays',
            'stays:write' => 'Create and update stays',
            'claims:read' => 'List park claims and provider invite tokens',
            'claims:write' => 'Approve, reject or request evidence for park claims',
            'corrections:read' => 'List and read listing correction submissions',
            'corrections:write' => 'Approve or reject listing corrections',
            'duplicates:read' => 'List duplicate suspects and review decisions',
            'duplicates:merge' => 'Merge duplicate records (human-preferred)',
            'datasets:read' => 'List government datasets and sync history',
            'datasets:write' => 'Update datasets and enqueue sync runs',
            'facilities:read' => 'List and read traveller facilities',
            'facilities:write' => 'Create and update traveller facilities',
            'drafts:read' => 'List and read draft submissions',
            'drafts:write' => 'Create and update drafts',
            'drafts:approve' => 'Approve or reject drafts',
            'imports:read' => 'Read import job status',
            'imports:write' => 'Submit, stage, publish and manage import packages',
            'sync:read' => 'Read sync conflicts and health metadata',
            'analytics:read' => 'Read overview, website insights, search analytics and zero-result gaps',
            'ai:read' => 'Read AI usage, cost and cache performance summaries',
            'audit:read' => 'Read audit events',
            'lifecycle:write' => 'Publish, unpublish, archive and restore lifecycle',
            'recycle_bin:restore' => 'Restore soft-deleted records',
            'recycle_bin:purge' => 'Permanently purge recycle-bin records',
            'service_accounts:admin' => 'Manage Admin API service accounts',
            'users:admin' => 'Manage users and roles',
            'billing:admin' => 'Manage billing configuration',
            'mfa:verify' => 'Complete MFA challenge during login',
        ];

        $catalog = [];
        foreach (self::ALL as $scope) {
            $catalog[$scope] = [
                'human' => true,
                'service' => isset($serviceAllowed[$scope]),
                'description' => $descriptions[$scope] ?? $scope,
            ];
        }

        return $catalog;
    }
}
