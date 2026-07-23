<?php

declare(strict_types=1);

namespace App\Services;

final class ImportProvenance
{
    public static function sourceUrl(array $record): ?string
    {
        $explicit = trim((string) ($record['source_url'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $id = trim((string) ($record['id'] ?? ''));
        if (preg_match('/^osm-([nwr])(\d+)$/', $id, $matches) === 1) {
            $types = ['n' => 'node', 'w' => 'way', 'r' => 'relation'];
            return 'https://www.openstreetmap.org/' . $types[$matches[1]] . '/' . $matches[2];
        }

        $website = trim((string) ($record['website'] ?? ''));
        return $website !== '' ? $website : null;
    }
}
