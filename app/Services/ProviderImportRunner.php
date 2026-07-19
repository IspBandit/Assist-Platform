<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Time-boxed, resumable provider imports for OSM and locality research seeds.
 * Used by Admin Maintenance, CLI (`scripts/seed.php`), and cron.
 *
 * Idempotent: listings key on slug / OSM id. Cron skips work when the seed file
 * fingerprint matches a completed import; a new deploy of the JSON resets progress.
 */
final class ProviderImportRunner
{
    public const SETTING_OSM_OFFSET = 'import_osm_offset';
    public const SETTING_OSM_FP = 'import_osm_fp';
    public const SETTING_LOCALITY_OFFSET = 'import_locality_offset';
    public const SETTING_LOCALITY_FP = 'import_locality_fp';

    /**
     * @return array{
     *   error?:string,
     *   providers:int,
     *   providers_enriched:int,
     *   towns:int,
     *   areas:int,
     *   total:int,
     *   next:int,
     *   complete:bool,
     *   offset:int,
     *   skipped?:bool,
     *   note?:string
     * }
     */
    public function runOsmPass(int $offset = 0, float $seconds = 18.0, int $batchSize = 200): array
    {
        return $this->runPass('osm', $offset, $seconds, $batchSize);
    }

    /**
     * @return array{
     *   error?:string,
     *   providers:int,
     *   providers_enriched:int,
     *   towns:int,
     *   areas:int,
     *   total:int,
     *   next:int,
     *   complete:bool,
     *   offset:int,
     *   skipped?:bool,
     *   note?:string
     * }
     */
    public function runLocalityPass(int $offset = 0, float $seconds = 18.0, int $batchSize = 300): array
    {
        return $this->runPass('locality', $offset, $seconds, $batchSize);
    }

    /**
     * Process until complete (CLI / long-running shell). Echoes progress via $onProgress.
     *
     * @param callable(array):void|null $onProgress
     * @return array<string,mixed>
     */
    public function runOsmToCompletion(?callable $onProgress = null, float $passSeconds = 45.0): array
    {
        return $this->runToCompletion('osm', $onProgress, $passSeconds);
    }

    /**
     * @param callable(array):void|null $onProgress
     * @return array<string,mixed>
     */
    public function runLocalityToCompletion(?callable $onProgress = null, float $passSeconds = 45.0): array
    {
        return $this->runToCompletion('locality', $onProgress, $passSeconds);
    }

    /**
     * Cron: one time-budgeted OSM pass. Resumes from saved offset; no-ops when
     * the current seed file was already fully imported.
     *
     * @return array<string,mixed>
     */
    public function cronOsm(float $seconds = 45.0): array
    {
        return $this->cronPass('osm', $seconds, 200);
    }

    /**
     * @return array<string,mixed>
     */
    public function cronLocality(float $seconds = 45.0): array
    {
        return $this->cronPass('locality', $seconds, 300);
    }

    /** @return array<string,mixed> */
    public function seedTowns(): array
    {
        return (new NationalTownSeeder())->seed();
    }

    /** Fingerprint of the OSM seed file (size + mtime + count). */
    public function osmFingerprint(): string
    {
        $file = OsmRefreshService::resolveSeedPath();
        if ($file === null) {
            return '';
        }

        return $this->fingerprint($file, 'businesses', 'count');
    }

    public function localityFingerprint(): string
    {
        $metaFile = base_path('database/seeds/businesses_locality.meta.json');
        if (is_file($metaFile)) {
            return $this->fingerprint($metaFile, 'coverage', 'coverage_count');
        }
        $covFile = base_path('database/seeds/businesses_locality_coverage.jsonl');
        if (!is_file($covFile)) {
            return '';
        }

        return hash('sha256', (string) filesize($covFile) . ':' . (string) filemtime($covFile));
    }

    /**
     * @return array{
     *   error?:string,
     *   providers:int,
     *   providers_enriched:int,
     *   towns:int,
     *   areas:int,
     *   total:int,
     *   next:int,
     *   complete:bool,
     *   offset:int
     * }
     */
    private function runPass(string $kind, int $offset, float $seconds, int $batchSize): array
    {
        $seeder = new NationalImportSeeder();
        $providers = 0;
        $enriched = 0;
        $towns = 0;
        $areas = 0;
        $total = 0;
        $next = max(0, $offset);
        $deadline = microtime(true) + max(1.0, $seconds);

        try {
            @set_time_limit(0);
            do {
                $r = $kind === 'osm'
                    ? $seeder->seedOsm($next, $batchSize)
                    : $seeder->seedLocality($next, $batchSize);
                if (isset($r['error'])) {
                    return [
                        'error' => (string) $r['error'],
                        'providers' => $providers,
                        'providers_enriched' => $enriched,
                        'towns' => $towns,
                        'areas' => $areas,
                        'total' => $total,
                        'next' => $next,
                        'complete' => false,
                        'offset' => $offset,
                    ];
                }
                $providers += (int) ($r['providers'] ?? 0);
                $enriched += (int) ($r['providers_enriched'] ?? 0);
                $towns += (int) ($r['towns'] ?? 0);
                $areas += (int) ($r['areas'] ?? 0);
                $total = (int) ($r['total'] ?? 0);
                $next = (int) ($r['next'] ?? -1);
            } while ($next >= 0 && microtime(true) < $deadline);
        } catch (Throwable $e) {
            return [
                'error' => $e->getMessage(),
                'providers' => $providers,
                'providers_enriched' => $enriched,
                'towns' => $towns,
                'areas' => $areas,
                'total' => $total,
                'next' => $next,
                'complete' => false,
                'offset' => $offset,
            ];
        }

        $complete = $next < 0;

        return [
            'providers' => $providers,
            'providers_enriched' => $enriched,
            'towns' => $towns,
            'areas' => $areas,
            'total' => $total,
            'next' => $complete ? -1 : $next,
            'complete' => $complete,
            'offset' => $offset,
        ];
    }

    /**
     * @param callable(array):void|null $onProgress
     * @return array<string,mixed>
     */
    private function runToCompletion(string $kind, ?callable $onProgress, float $passSeconds): array
    {
        $offset = 0;
        $totals = [
            'providers' => 0,
            'providers_enriched' => 0,
            'towns' => 0,
            'areas' => 0,
            'total' => 0,
            'passes' => 0,
        ];

        do {
            $r = $kind === 'osm'
                ? $this->runOsmPass($offset, $passSeconds)
                : $this->runLocalityPass($offset, $passSeconds);
            if (isset($r['error'])) {
                return $r;
            }
            $totals['providers'] += (int) $r['providers'];
            $totals['providers_enriched'] += (int) $r['providers_enriched'];
            $totals['towns'] += (int) $r['towns'];
            $totals['areas'] += (int) $r['areas'];
            $totals['total'] = (int) $r['total'];
            $totals['passes']++;
            if ($onProgress !== null) {
                $onProgress($r);
            }
            $offset = (int) $r['next'];
            $this->persistProgress($kind, $r);
        } while ($offset >= 0);

        $totals['complete'] = true;
        $totals['next'] = -1;

        return $totals;
    }

    /** @return array<string,mixed> */
    private function cronPass(string $kind, float $seconds, int $batchSize): array
    {
        $fpKey = $kind === 'osm' ? self::SETTING_OSM_FP : self::SETTING_LOCALITY_FP;
        $offKey = $kind === 'osm' ? self::SETTING_OSM_OFFSET : self::SETTING_LOCALITY_OFFSET;
        $fp = $kind === 'osm' ? $this->osmFingerprint() : $this->localityFingerprint();

        if ($fp === '') {
            return ['skipped' => true, 'note' => $kind . ' seed file not found'];
        }

        $savedFp = (string) Settings::get($fpKey, '');
        $savedOff = (string) Settings::get($offKey, '0');

        if ($savedFp === $fp && $savedOff === 'done') {
            return ['skipped' => true, 'note' => 'up to date', 'fingerprint' => $fp];
        }

        if ($savedFp !== $fp) {
            Settings::set($fpKey, $fp);
            Settings::set($offKey, '0');
            $offset = 0;
        } else {
            $offset = $savedOff === 'done' ? 0 : max(0, (int) $savedOff);
        }

        $r = $kind === 'osm'
            ? $this->runOsmPass($offset, $seconds, $batchSize)
            : $this->runLocalityPass($offset, $seconds, $batchSize);

        if (isset($r['error'])) {
            return $r;
        }

        $this->persistProgress($kind, $r);

        if (!empty($r['complete']) && $kind === 'osm') {
            try {
                $r['featured_towns'] = MajorCityCoverageService::featureMajorCityTowns();
            } catch (Throwable) {
                // Optional nicety; import itself succeeded.
            }
        }

        return $r;
    }

    /** @param array<string,mixed> $result */
    private function persistProgress(string $kind, array $result): void
    {
        $offKey = $kind === 'osm' ? self::SETTING_OSM_OFFSET : self::SETTING_LOCALITY_OFFSET;
        $fpKey = $kind === 'osm' ? self::SETTING_OSM_FP : self::SETTING_LOCALITY_FP;
        $fp = $kind === 'osm' ? $this->osmFingerprint() : $this->localityFingerprint();

        if ($fp !== '') {
            Settings::set($fpKey, $fp);
        }

        if (!empty($result['complete'])) {
            Settings::set($offKey, 'done');
        } else {
            Settings::set($offKey, (string) max(0, (int) ($result['next'] ?? 0)));
        }
    }

    private function fingerprint(string $file, string $listKey, string $countKey): string
    {
        if (!is_file($file)) {
            return '';
        }
        $size = (string) filesize($file);
        $mtime = (string) filemtime($file);
        $count = '';
        try {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $count = (string) ($data[$countKey] ?? count((array) ($data[$listKey] ?? [])));
            }
        } catch (Throwable) {
            $count = '';
        }

        return hash('sha256', $size . ':' . $mtime . ':' . $count);
    }
}
