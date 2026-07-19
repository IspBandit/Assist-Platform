<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use Throwable;

/**
 * Session-backed maintenance pipeline that advances one unit of work per
 * browser request. Driven by GET + meta-refresh so it works without JavaScript.
 */
final class MaintenanceAutoPipeline
{
    private const SESSION_KEY = 'maintenance_auto_pipeline';

    /**
     * @param array{include_osm_scan?:bool} $options
     * @return array{token:string}
     */
    public function start(array $options = []): array
    {
        $token = bin2hex(random_bytes(16));
        $phases = [];

        // Live Overpass scan is optional — often blocked/slow on shared hosts.
        if (!empty($options['include_osm_scan'])) {
            $phases[] = 'osm_scan';
        }

        $phases[] = 'osm_import';
        if (NationalImportSeeder::localityCoverageCount() > 0) {
            $phases[] = 'locality_import';
        }
        $phases[] = 'feature_cities';

        Session::set(self::SESSION_KEY, [
            'token' => $token,
            'phases' => $phases,
            'phase_index' => 0,
            'offset' => 0,
            'retries' => 0,
            'started_at' => time(),
            'stats' => [
                'providers' => 0,
                'enriched' => 0,
                'towns' => 0,
                'areas' => 0,
            ],
        ]);

        if (!empty($options['include_osm_scan'])) {
            (new OsmRefreshService())->begin();
        }

        return ['token' => $token];
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        try {
            \App\Core\Database::reconnect();
            Settings::set('osm_refresh_active', '0');
        } catch (Throwable) {
            // Session clear is enough to stop the pipeline; settings write is best-effort
            // after a dropped MySQL connection.
        }
    }

    /** @return array<string,mixed>|null */
    public function job(): ?array
    {
        $job = Session::get(self::SESSION_KEY);
        return is_array($job) ? $job : null;
    }

    public function tokenMatches(string $token): bool
    {
        $job = $this->job();
        return $job !== null && hash_equals((string) ($job['token'] ?? ''), $token);
    }

    /**
     * Run one unit of work. Returns a result describing the next browser action.
     *
     * @return array{
     *   done?:bool,
     *   error?:string,
     *   message:string,
     *   continue:bool,
     *   token:string
     * }
     */
    public function tick(string $token): array
    {
        if (!$this->tokenMatches($token)) {
            return [
                'done' => true,
                'error' => 'This auto-refresh session expired or is invalid. Start again from Maintenance.',
                'message' => 'Stopped.',
                'continue' => false,
                'token' => $token,
            ];
        }

        $job = $this->job();
        assert(is_array($job));
        $phases = array_values((array) ($job['phases'] ?? []));
        $index = (int) ($job['phase_index'] ?? 0);

        // Long import passes can outlive shared-host MySQL wait_timeout.
        \App\Core\Database::reconnect();

        if ($index >= count($phases)) {
            $this->clear();
            $stats = (array) ($job['stats'] ?? []);

            return [
                'done' => true,
                'message' => sprintf(
                    'Provider refresh complete — %d new listing(s), %d enriched, %d town(s), %d service area(s).',
                    (int) ($stats['providers'] ?? 0),
                    (int) ($stats['enriched'] ?? 0),
                    (int) ($stats['towns'] ?? 0),
                    (int) ($stats['areas'] ?? 0)
                ),
                'continue' => false,
                'token' => $token,
            ];
        }

        $phase = (string) $phases[$index];

        try {
            return match ($phase) {
                'osm_scan' => $this->tickOsmScan($job, $token),
                'osm_import' => $this->tickOsmImport($job, $token),
                'locality_import' => $this->tickLocalityImport($job, $token),
                'feature_cities' => $this->tickFeatureCities($job, $token),
                default => [
                    'done' => true,
                    'error' => 'Unknown pipeline phase: ' . $phase,
                    'message' => 'Stopped.',
                    'continue' => false,
                    'token' => $token,
                ],
            };
        } catch (Throwable $e) {
            $original = $e->getMessage();
            try {
                $this->clear();
            } catch (Throwable) {
            }

            return [
                'done' => true,
                'error' => $original,
                'message' => 'Stopped with an error.',
                'continue' => false,
                'token' => $token,
            ];
        }
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private function tickOsmScan(array $job, string $token): array
    {
        $svc = new OsmRefreshService();
        if (!$svc->isActive()) {
            $svc->begin();
        }

        $r = $svc->runNextStep();
        if (isset($r['error'])) {
            $retries = (int) ($job['retries'] ?? 0) + 1;
            if ($retries >= 3) {
                $this->clear();

                return [
                    'done' => true,
                    'error' => (string) $r['error'] . ' Live OpenStreetMap scan failed after 3 tries (Overpass may be blocked on this host).',
                    'message' => 'Stopped.',
                    'continue' => false,
                    'token' => $token,
                ];
            }
            $job['retries'] = $retries;
            Session::set(self::SESSION_KEY, $job);

            return [
                'message' => (string) $r['error'] . ' Retrying (' . $retries . '/3)…',
                'continue' => true,
                'token' => $token,
            ];
        }

        $job['retries'] = 0;
        if (!empty($r['complete'])) {
            $job['phase_index'] = (int) $job['phase_index'] + 1;
            $job['offset'] = 0;
            Session::set(self::SESSION_KEY, $job);

            return [
                'message' => sprintf(
                    'OpenStreetMap scan complete — %s businesses saved. Importing into the directory…',
                    number_format((int) ($r['businesses'] ?? 0))
                ),
                'continue' => true,
                'token' => $token,
            ];
        }

        Session::set(self::SESSION_KEY, $job);

        return [
            'message' => sprintf(
                'OpenStreetMap scan — step %d of %d (%s): +%d, %s total.',
                (int) ($r['step'] ?? 0),
                (int) ($r['total_steps'] ?? 0),
                (string) ($r['label'] ?? ''),
                (int) ($r['added'] ?? 0),
                number_format((int) ($r['businesses'] ?? 0))
            ),
            'continue' => true,
            'token' => $token,
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private function tickOsmImport(array $job, string $token): array
    {
        $offset = max(0, (int) ($job['offset'] ?? 0));
        $r = (new ProviderImportRunner())->runOsmPass($offset, 8.0, 80);
        \App\Core\Database::reconnect();
        if (isset($r['error'])) {
            $this->clear();

            return [
                'done' => true,
                'error' => 'OSM import: ' . (string) $r['error'],
                'message' => 'Stopped.',
                'continue' => false,
                'token' => $token,
            ];
        }

        $this->accumulateStats($job, $r);
        $next = (int) ($r['next'] ?? -1);

        if ($next >= 0) {
            $job['offset'] = $next;
            Session::set(self::SESSION_KEY, $job);

            return [
                'message' => sprintf(
                    'Importing OpenStreetMap listings — %s of %s processed (+%d new, +%d enriched).',
                    number_format($next),
                    number_format((int) ($r['total'] ?? 0)),
                    (int) ($r['providers'] ?? 0),
                    (int) ($r['providers_enriched'] ?? 0)
                ),
                'continue' => true,
                'token' => $token,
            ];
        }

        $job['phase_index'] = (int) $job['phase_index'] + 1;
        $job['offset'] = 0;
        Session::set(self::SESSION_KEY, $job);

        return [
            'message' => sprintf(
                'OpenStreetMap import complete — %s businesses processed. Continuing…',
                number_format((int) ($r['total'] ?? 0))
            ),
            'continue' => true,
            'token' => $token,
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private function tickLocalityImport(array $job, string $token): array
    {
        $offset = max(0, (int) ($job['offset'] ?? 0));
        $r = (new ProviderImportRunner())->runLocalityPass($offset, 8.0, 120);
        \App\Core\Database::reconnect();
        if (isset($r['error'])) {
            // Missing locality seed is not fatal — skip ahead.
            $job['phase_index'] = (int) $job['phase_index'] + 1;
            $job['offset'] = 0;
            Session::set(self::SESSION_KEY, $job);

            return [
                'message' => 'Locality import skipped (' . (string) $r['error'] . '). Continuing…',
                'continue' => true,
                'token' => $token,
            ];
        }

        $this->accumulateStats($job, $r);
        $next = (int) ($r['next'] ?? -1);

        if ($next >= 0) {
            $job['offset'] = $next;
            Session::set(self::SESSION_KEY, $job);

            return [
                'message' => sprintf(
                    'Importing locality research — %s of %s processed (+%d new, +%d areas).',
                    number_format($next),
                    number_format((int) ($r['total'] ?? 0)),
                    (int) ($r['providers'] ?? 0),
                    (int) ($r['areas'] ?? 0)
                ),
                'continue' => true,
                'token' => $token,
            ];
        }

        $job['phase_index'] = (int) $job['phase_index'] + 1;
        $job['offset'] = 0;
        Session::set(self::SESSION_KEY, $job);

        return [
            'message' => 'Locality import complete. Promoting major cities…',
            'continue' => true,
            'token' => $token,
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private function tickFeatureCities(array $job, string $token): array
    {
        $updated = MajorCityCoverageService::featureMajorCityTowns();
        $job['phase_index'] = (int) $job['phase_index'] + 1;
        Session::set(self::SESSION_KEY, $job);

        return [
            'message' => sprintf('Marked %d major-city town(s) as featured. Finishing…', $updated),
            'continue' => true,
            'token' => $token,
        ];
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $r
     */
    private function accumulateStats(array &$job, array $r): void
    {
        $stats = (array) ($job['stats'] ?? []);
        $stats['providers'] = (int) ($stats['providers'] ?? 0) + (int) ($r['providers'] ?? 0);
        $stats['enriched'] = (int) ($stats['enriched'] ?? 0) + (int) ($r['providers_enriched'] ?? 0);
        $stats['towns'] = (int) ($stats['towns'] ?? 0) + (int) ($r['towns'] ?? 0);
        $stats['areas'] = (int) ($stats['areas'] ?? 0) + (int) ($r['areas'] ?? 0);
        $job['stats'] = $stats;
    }
}
