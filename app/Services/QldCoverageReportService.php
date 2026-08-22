<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Read-only access to offline Queensland coverage artefacts.
 * Never writes to the database.
 */
final class QldCoverageReportService
{
    private string $seedRoot;

    public function __construct(?string $seedRoot = null)
    {
        $this->seedRoot = $seedRoot ?? (BASE_PATH . '/database/seeds/qld-coverage');
    }

    /** @return array<string,mixed>|null */
    public function summary(): ?array
    {
        return $this->loadJson($this->seedRoot . '/coverage-summary.json');
    }

    /** @return array<int,array<string,mixed>> */
    public function batches(): array
    {
        $dir = $this->seedRoot . '/by-batch';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $row = $this->loadJson($file);
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) ($a['batch_id'] ?? ''), (string) ($b['batch_id'] ?? '')));
        return $out;
    }

    /**
     * Filter zero/weak coverage samples by region, town, category and status.
     *
     * @param array{batch?:string,town?:string,category?:string,status?:string,limit?:int,source?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function coverageRows(array $filters = []): array
    {
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $source = (string) ($filters['source'] ?? 'zero');
        $path = $this->seedRoot . '/' . ($source === 'weak' ? 'weak-coverage.jsonl' : 'zero-coverage.jsonl');
        if (!is_file($path)) {
            return [];
        }

        $batch = strtolower(trim((string) ($filters['batch'] ?? '')));
        $town = strtolower(trim((string) ($filters['town'] ?? '')));
        $category = strtolower(trim((string) ($filters['category'] ?? '')));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));

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
            if (!is_array($row)) {
                continue;
            }
            if ($batch !== '' && !$this->matchesBatchFilter($row, $batch)) {
                continue;
            }
            if ($town !== '' && !str_contains(strtolower((string) ($row['Town/suburb'] ?? '')), $town)) {
                continue;
            }
            if ($category !== '' && !str_contains(strtolower((string) ($row['Service category'] ?? '')), $category)) {
                continue;
            }
            if ($status !== '' && strtolower((string) ($row['Coverage status'] ?? '')) !== $status) {
                continue;
            }
            $out[] = $row;
        }
        fclose($fh);
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function zeroCoverageSample(int $limit = 100): array
    {
        return $this->coverageRows(['limit' => $limit, 'source' => 'zero']);
    }

    /**
     * Sample review-queue candidates with source and category evidence.
     *
     * @param array{batch?:string,town?:string,category?:string,limit?:int} $filters
     * @return array<int,array<string,mixed>>
     */
    public function reviewCandidates(array $filters = []): array
    {
        $path = $this->seedRoot . '/providers-review-queue.json';
        $rows = $this->loadJson($path);
        if (!is_array($rows)) {
            return [];
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 25)));
        $town = strtolower(trim((string) ($filters['town'] ?? '')));
        $category = strtolower(trim((string) ($filters['category'] ?? '')));
        $batch = strtolower(trim((string) ($filters['batch'] ?? '')));

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($town !== '' && !str_contains(strtolower((string) ($row['town'] ?? '')), $town)
                && !str_contains(strtolower((string) ($row['suburb'] ?? '')), $town)) {
                continue;
            }
            if ($category !== '') {
                $slugs = array_map('strtolower', array_map('strval', $row['category_slugs'] ?? []));
                $hit = false;
                foreach ($slugs as $slug) {
                    if (str_contains($slug, $category)) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            if ($batch !== '' && !$this->providerMatchesBatch($row, $batch)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function possibleDuplicates(int $limit = 50): array
    {
        $rows = $this->loadJson($this->seedRoot . '/possible-duplicates.json');
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= max(1, min(200, $limit))) {
                break;
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function regulatedMissingLicence(int $limit = 50): array
    {
        $rows = $this->loadJson($this->seedRoot . '/regulated-missing-licence.json');
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= max(1, min(200, $limit))) {
                break;
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $row */
    private function matchesBatchFilter(array $row, string $batch): bool
    {
        $region = strtolower((string) ($row['Region'] ?? ''));
        $batch = strtolower($batch);
        if ($region === $batch || str_contains($region, $batch)) {
            return true;
        }
        foreach ($this->batches() as $b) {
            $id = strtolower((string) ($b['batch_id'] ?? ''));
            $name = strtolower((string) ($b['batch_name'] ?? ''));
            if (($id === $batch || str_contains($id, $batch) || str_contains($name, $batch))
                && $region === $name) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $provider */
    private function providerMatchesBatch(array $provider, string $batch): bool
    {
        $region = strtolower((string) ($provider['region'] ?? ''));
        $batch = strtolower($batch);
        if ($region !== '' && (str_contains($batch, $region) || str_contains($region, $batch))) {
            return true;
        }
        // SEQ splits use batch id fragments in admin filters.
        $seqHints = [
            'brisbane-moreton-bay' => ['seq'],
            'gold-coast-scenic-rim' => ['seq'],
            'sunshine-coast-noosa' => ['seq'],
            'darling-downs-south-west' => ['downs'],
            'wide-bay-burnett' => ['widebay'],
            'central-queensland' => ['cq', 'fitzroy'],
            'mackay-whitsunday' => ['mackay'],
            'townsville-north-queensland' => ['nq'],
            'cairns-far-north' => ['fnq'],
            'gulf-cape-remote' => ['outback', 'fnq', 'nq', 'cq'],
        ];
        $hints = $seqHints[$batch] ?? [];
        return $region !== '' && in_array($region, $hints, true);
    }

    /** @return array<string,mixed>|null */
    private function loadJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }
}
