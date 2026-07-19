<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Response;

/**
 * Builds a CSV download Response. Values are written via fputcsv (proper
 * quoting/escaping) with a UTF-8 BOM so Excel opens accented characters
 * correctly.
 */
final class CsvExport
{
    /**
     * @param array<int,string>             $headers
     * @param iterable<array<int|string,mixed>> $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): Response
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return new Response('Unable to build export.', 500);
        }

        fwrite($stream, "\xEF\xBB\xBF"); // UTF-8 BOM
        if ($headers !== []) {
            fputcsv($stream, $headers);
        }
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static fn ($v) => $v === null ? '' : (string) $v, array_values((array) $row)));
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        $safeName = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $filename) ?: 'export.csv';

        return (new Response($csv, 200))
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $safeName . '"');
    }
}
