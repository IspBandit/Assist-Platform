<?php

declare(strict_types=1);

namespace App\Services\Demand;

/** Keeps technical requests out of public website activity figures. */
final class PublicPageViewPolicy
{
    /** @var list<string> */
    private const PRIVATE_OR_TECHNICAL_PREFIXES = [
        '/admin', '/api', '/install', '/account', '/provider', '/park', '/billing',
        '/assets', '/runtime-assets', '/uploads', '/ops', '/.well-known',
    ];

    /** @var list<string> */
    private const TECHNICAL_PATHS = [
        '/healthz', '/readyz', '/sitemap.xml', '/robots.txt', '/favicon.ico',
        '/manifest.webmanifest', '/service-worker.js',
    ];

    public static function includes(string $path): bool
    {
        $path = '/' . ltrim(trim($path), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        foreach (self::PRIVATE_OR_TECHNICAL_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return false;
            }
        }
        if (in_array($path, self::TECHNICAL_PATHS, true)) {
            return false;
        }

        return preg_match(
            '/\.(?:css|js|map|png|jpe?g|webp|avif|gif|svg|ico|woff2?|ttf|json|xml|txt)(?:$|\?)/i',
            $path
        ) !== 1;
    }

    /**
     * SQL equivalent used to clean historical reports without deleting evidence.
     * The column is internal code, never request data.
     */
    public static function sqlPredicate(string $column = 'route'): string
    {
        if (preg_match('/^[a-z_][a-z0-9_.]*$/i', $column) !== 1) {
            throw new \InvalidArgumentException('Unsafe page-view column name.');
        }

        $clauses = ["{$column} IS NOT NULL", "{$column} <> ''"];
        foreach (self::PRIVATE_OR_TECHNICAL_PREFIXES as $prefix) {
            $escaped = str_replace("'", "''", $prefix);
            $clauses[] = "{$column} <> '{$escaped}'";
            $clauses[] = "{$column} NOT LIKE '{$escaped}/%'";
        }
        foreach (self::TECHNICAL_PATHS as $path) {
            $escaped = str_replace("'", "''", $path);
            $clauses[] = "{$column} <> '{$escaped}'";
        }
        $clauses[] = "{$column} NOT REGEXP '\\\\.(css|js|map|png|jpe?g|webp|avif|gif|svg|ico|woff2?|ttf|json|xml|txt)(\\\\?.*)?$'";

        return '(' . implode(' AND ', $clauses) . ')';
    }
}
