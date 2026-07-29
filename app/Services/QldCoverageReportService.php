<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Read-only access to offline Queensland coverage artefacts.
 * Never writes to the database.
 */
final class QldCoverageReportService
{
    /** @return array<string,mixed>|null */
    public function summary(): ?array
    {
        $path = BASE_PATH . '/database/seeds/qld-coverage/coverage-summary.json';
        if (!is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function batches(): array
    {
        $dir = BASE_PATH . '/database/seeds/qld-coverage/by-batch';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $row = json_decode((string) file_get_contents($file), true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) ($a['batch_id'] ?? ''), (string) ($b['batch_id'] ?? '')));
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function zeroCoverageSample(int $limit = 100): array
    {
        $path = BASE_PATH . '/database/seeds/qld-coverage/zero-coverage.jsonl';
        if (!is_file($path)) {
            return [];
        }
        $out = [];
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        while (!feof($fh) && count($out) < $limit) {
            $line = fgets($fh);
            if ($line === false || trim($line) === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        fclose($fh);
        return $out;
    }
}
