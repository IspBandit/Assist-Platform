<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Read-only catalogue recovered from the original TowWise Android app.
 * Specifications are advertised reference figures and must be confirmed
 * against the exact compliance plate and current manufacturer information.
 */
final class TowSmartCatalog
{
    /** @var array<string,list<array<string,mixed>>> */
    private static array $cache = [];

    /** @return list<array<string,mixed>> */
    public static function search(string $type, string $query, int $limit = 20): array
    {
        $items = self::load($type);
        $query = mb_strtolower(trim($query));
        $limit = max(1, min($limit, 50));
        $matches = [];

        foreach ($items as $index => $item) {
            $haystack = mb_strtolower(implode(' ', array_filter([
                (string) ($item['brand'] ?? ''),
                (string) ($item['name'] ?? ''),
                (string) ($item['years'] ?? ''),
                (string) ($item['type'] ?? ''),
            ])));
            if ($query !== '' && !str_contains($haystack, $query)) {
                continue;
            }
            $matches[] = self::summary($type, $index, $item);
            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }

    /** @return array<string,mixed>|null */
    public static function find(string $type, int $id): ?array
    {
        $items = self::load($type);
        if (!isset($items[$id])) {
            return null;
        }
        return ['id' => $id, 'catalogue_type' => $type] + $items[$id] + [
            'specification_status' => 'advertised_reference',
        ];
    }

    /** @return array{vehicles:int,trailers:int} */
    public static function counts(): array
    {
        return ['vehicles' => count(self::load('vehicles')), 'trailers' => count(self::load('trailers'))];
    }

    /** @return list<array<string,mixed>> */
    private static function load(string $type): array
    {
        if (!in_array($type, ['vehicles', 'trailers'], true)) {
            throw new RuntimeException('Unsupported TowSmart catalogue type.');
        }
        if (isset(self::$cache[$type])) {
            return self::$cache[$type];
        }
        $path = dirname(__DIR__, 2) . '/resources/towsmart/catalog/' . $type . '.json';
        $json = is_file($path) ? file_get_contents($path) : false;
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('TowSmart catalogue data is unavailable.');
        }
        return self::$cache[$type] = array_values($data);
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private static function summary(string $type, int $id, array $item): array
    {
        $label = trim(implode(' ', array_filter([
            $type === 'trailers' ? (string) ($item['brand'] ?? '') : '',
            (string) ($item['name'] ?? ''),
            (string) ($item['years'] ?? ''),
        ])));
        return [
            'id' => $id,
            'label' => $label,
            'type' => $item['type'] ?? null,
            'atm' => $item['atm'] ?? null,
            'gvm' => $item['gvm'] ?? null,
            'towing_capacity' => $item['towing_capacity'] ?? null,
        ];
    }
}
