<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use RuntimeException;
use ZipArchive;

/**
 * Minimal XLSX → row maps reader (first sheet). No Composer spreadsheet dependency.
 */
final class XlsxSheetReader
{
    /**
     * @return list<array<string,string>>
     */
    public static function rowsFromFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('XLSX file not found.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open XLSX archive.');
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($sharedXml) && $sharedXml !== '') {
            if (preg_match_all('/<si[^>]*>.*?<t[^>]*>(.*?)<\/t>/s', $sharedXml, $m)) {
                foreach ($m[1] as $value) {
                    $shared[] = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1);
                }
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!is_string($sheetXml) || $sheetXml === '') {
            throw new RuntimeException('XLSX sheet1.xml missing.');
        }

        $grid = [];
        if (preg_match_all('/<c\s+r="([A-Z]+)(\d+)"([^>]*)>(?:<v>(.*?)<\/v>)?/s', $sheetXml, $cells, PREG_SET_ORDER)) {
            foreach ($cells as $cell) {
                $col = self::columnIndex($cell[1]);
                $row = (int) $cell[2];
                $attrs = $cell[3];
                $raw = $cell[4] ?? '';
                $value = $raw;
                if (str_contains($attrs, 't="s"') && $raw !== '' && isset($shared[(int) $raw])) {
                    $value = $shared[(int) $raw];
                }
                $grid[$row][$col] = (string) $value;
            }
        }
        if ($grid === []) {
            return [];
        }
        ksort($grid);
        $headerRow = array_shift($grid);
        if ($headerRow === null) {
            return [];
        }
        ksort($headerRow);
        $headers = [];
        foreach ($headerRow as $col => $label) {
            $headers[$col] = strtolower(trim((string) $label));
        }
        $out = [];
        foreach ($grid as $row) {
            $mapped = [];
            $blank = true;
            foreach ($headers as $col => $key) {
                if ($key === '') {
                    continue;
                }
                $val = (string) ($row[$col] ?? '');
                if ($val !== '') {
                    $blank = false;
                }
                $mapped[$key] = $val;
            }
            if (!$blank) {
                $out[] = $mapped;
            }
        }
        return $out;
    }

    private static function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = ($n * 26) + (ord($letters[$i]) - 64);
        }
        return $n;
    }
}
