<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Fetches caravan/vehicle trade businesses from OpenStreetMap (Overpass API)
 * in short web-safe steps, writes storage/imports/businesses_osm.json, then the
 * existing NationalImportSeeder / ProviderImportRunner can load them into MySQL.
 *
 * Designed for Admin → Maintenance auto-continue (one Overpass query per request).
 */
final class OsmRefreshService
{
    private const ENDPOINT = 'https://overpass-api.de/api/interpreter';
    private const MAX_KM = 60.0;
    private const USER_AGENT = 'VanAssist-OSM-Import/1.3 (vanassist@condrendigital.com.au)';

    /** @var list<string> */
    private const STATES = ['QLD', 'NSW', 'VIC', 'SA', 'WA', 'TAS', 'NT', 'ACT'];

    /** @var list<array{sel:string,cat:string}> */
    private const SELECTORS = [
        ['sel' => '["shop"="car_repair"]', 'cat' => 'mechanical'],
        ['sel' => '["shop"="tyres"]', 'cat' => 'mechanical'],
        ['sel' => '["shop"="car_parts"]', 'cat' => 'mechanical'],
        ['sel' => '["shop"="caravan"]', 'cat' => 'caravan'],
        ['sel' => '["craft"="caravan"]', 'cat' => 'caravan'],
        ['sel' => '["shop"="trailer"]', 'cat' => 'trailer'],
        ['sel' => '["craft"="plumber"]', 'cat' => 'plumber'],
        ['sel' => '["craft"="electrician"]', 'cat' => 'autoelec'],
        ['sel' => '["craft"="hvac"]', 'cat' => 'caravan'],
        ['sel' => '["craft"="electronics_repair"]', 'cat' => 'autoelec'],
        ['sel' => '["craft"="metal_construction"]', 'cat' => 'trailer'],
        ['sel' => '["craft"="welder"]', 'cat' => 'trailer'],
        ['sel' => '["shop"="gas"]', 'cat' => 'gasfitter'],
        ['sel' => '["amenity"="vehicle_inspection"]', 'cat' => 'roadworthy'],
        ['sel' => '["service:vehicle:car_repair"="yes"]', 'cat' => 'mechanical'],
        ['sel' => '["service:vehicle:tyres"="yes"]', 'cat' => 'mechanical'],
        ['sel' => '["service:vehicle:brakes"="yes"]', 'cat' => 'mechanical'],
        ['sel' => '["service:vehicle:oil_change"="yes"]', 'cat' => 'mechanical'],
        ['sel' => '["service:vehicle:diagnostics"="yes"]', 'cat' => 'mechanical'],
        ['sel' => '["service:vehicle:electrical"="yes"]', 'cat' => 'autoelec'],
        ['sel' => '["service:vehicle:air_conditioning"="yes"]', 'cat' => 'caravan'],
        ['sel' => '["service:vehicle:truck_repair"="yes"]', 'cat' => 'mechanical'],
        ['sel' => '["service:vehicle:motorhome_repair"="yes"]', 'cat' => 'caravan'],
        ['sel' => '["service:vehicle:caravan_repair"="yes"]', 'cat' => 'caravan'],
    ];

    /** @var list<array{sel:string,cat:string}> */
    private const NAME_SELECTORS = [
        ['sel' => '["name"~"caravan",i]["name"~"repair|service|servicing",i]', 'cat' => 'caravan'],
        ['sel' => '["name"~"motorhome|campervan|camper trailer| rv ",i]["name"~"repair|service",i]', 'cat' => 'caravan'],
        ['sel' => '["name"~"mobile mechanic|mobile diesel|mobile tyre|mobile tire",i]', 'cat' => 'mechanical'],
        ['sel' => '["name"~"auto.?elect|12.?volt|12v ",i]', 'cat' => 'autoelec'],
        ['sel' => '["name"~"roadworth|safety cert|pink slip|blue slip",i]', 'cat' => 'roadworthy'],
        ['sel' => '["name"~"gas fitt|gas appliance| lpg ",i]', 'cat' => 'gasfitter'],
        ['sel' => '["name"~"trailer",i]["name"~"repair|service|engineer|weld",i]', 'cat' => 'trailer'],
        ['sel' => '["name"~"brake",i]["name"~"bearing|trailer|caravan",i]', 'cat' => 'trailer'],
        ['sel' => '["name"~"mobile weld|mobile plumb",i]', 'cat' => 'plumber'],
        ['sel' => '["name"~"air.?con|aircon|caravan fridge|rv fridge",i]', 'cat' => 'caravan'],
        ['sel' => '["name"~"roadside assist|roadside rescue",i]', 'cat' => 'roadside'],
    ];

    /** @var list<array{re:string,cat:string}> */
    private const NAME_RULES = [
        ['re' => '/auto\s?elec|auto-?electric|12\s?volt|12v\b|dual\s?battery/i', 'cat' => 'autoelec'],
        ['re' => '/caravan|camper|\brv\b|recreational vehicle|motorhome|campervan/i', 'cat' => 'caravan'],
        ['re' => '/trailer|horse float|box trailer|brake.?s?\s*(and|&)?\s*bearing/i', 'cat' => 'trailer'],
        ['re' => '/roadworth|safety cert|pink slip|blue slip|\brwc\b|e-?safety|inspection station|safety certificate/i', 'cat' => 'roadworthy'],
        ['re' => '/roadside\s*(assist|rescue|help)/i', 'cat' => 'roadside'],
        ['re' => '/plumb|mobile weld/i', 'cat' => 'plumber'],
        ['re' => '/gas\s?(fitt|appliance|service)|gasfitt|\blpg\b/i', 'cat' => 'gasfitter'],
        ['re' => '/air\s?con|aircon|caravan fridge|rv fridge|refrigerat/i', 'cat' => 'caravan'],
        ['re' => '/tyre|tire|wheel align/i', 'cat' => 'mechanical'],
        ['re' => '/mechanic|automotive|\bmotors?\b|car service|vehicle service|\b4wd\b|diesel|mobile diesel/i', 'cat' => 'mechanical'],
    ];

    /** @var array<string,string> */
    private const TRADE_BLURB = [
        'caravan' => 'Caravan and RV repairs',
        'autoelec' => 'Auto electrical (12-volt, batteries)',
        'mechanical' => 'Mechanical repairs and servicing',
        'trailer' => 'Trailer repairs and engineering',
        'plumber' => 'Plumbing',
        'gasfitter' => 'Gas appliance servicing',
        'roadworthy' => 'Roadworthy / safety-certificate inspections',
        'roadside' => 'Roadside assistance',
    ];

    public static function outputPath(): string
    {
        return base_path('storage/imports/businesses_osm.json');
    }

    public static function bundledPath(): string
    {
        return base_path('database/seeds/businesses_osm.json');
    }

    /** Prefer a freshly fetched server copy, else the deployed seed. */
    public static function resolveSeedPath(): ?string
    {
        $out = self::outputPath();
        if (is_file($out)) {
            return $out;
        }
        $bundled = self::bundledPath();

        return is_file($bundled) ? $bundled : null;
    }

    public static function seedBusinessCount(): int
    {
        $file = self::resolveSeedPath();
        if ($file === null) {
            return 0;
        }
        try {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                return 0;
            }

            return (int) ($data['count'] ?? count((array) ($data['businesses'] ?? [])));
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return list<array{type:string,id:string,label:string,state?:string,lat?:float,lng?:float,radius_km?:float}> */
    public function steps(): array
    {
        $steps = [];
        foreach (self::STATES as $state) {
            $steps[] = [
                'type' => 'state',
                'id' => $state,
                'label' => 'State ' . $state,
                'state' => $state,
            ];
        }
        foreach (MajorCityCoverageService::seedData()['metros'] as $metro) {
            $steps[] = [
                'type' => 'metro',
                'id' => (string) ($metro['id'] ?? $metro['name']),
                'label' => 'Metro ' . (string) ($metro['name'] ?? '') . ', ' . (string) ($metro['state'] ?? ''),
                'state' => (string) ($metro['state'] ?? ''),
                'lat' => (float) ($metro['lat'] ?? 0),
                'lng' => (float) ($metro['lng'] ?? 0),
                'radius_km' => (float) ($metro['scan_radius_km'] ?? 50),
            ];
        }
        foreach (MajorCityCoverageService::seedData()['cities'] as $city) {
            $name = (string) ($city['name'] ?? '');
            $state = (string) ($city['state'] ?? '');
            $steps[] = [
                'type' => 'metro',
                'id' => $this->slug($name) . '-' . strtolower($state),
                'label' => 'City ' . $name . ', ' . $state,
                'state' => $state,
                'lat' => (float) ($city['lat'] ?? 0),
                'lng' => (float) ($city['lng'] ?? 0),
                'radius_km' => (float) ($city['scan_radius_km'] ?? 45),
            ];
        }

        return $steps;
    }

    /** Reset progress and start a new Overpass refresh. */
    public function begin(): void
    {
        $this->ensureDir();
        $progressFile = $this->progressFile();
        $bizFile = $this->businessesFile();
        $progress = [
            'step' => 0,
            'started_at' => gmdate('c'),
            'per_state' => [],
            'per_metro' => [],
            'dropped' => ['unnamed' => 0, 'noCoord' => 0, 'noTown' => 0, 'dup' => 0],
            'phones' => [],
            'hosts' => [],
            'name_town' => [],
            'osm_ids' => [],
        ];
        $existing = $this->loadExistingKeys();
        $progress['phones'] = array_values($existing['phones']);
        $progress['hosts'] = array_values($existing['hosts']);
        $progress['name_town'] = array_values($existing['nameTown']);

        if (file_put_contents($progressFile, json_encode($progress, JSON_UNESCAPED_SLASHES)) === false) {
            throw new \RuntimeException('Cannot write ' . $progressFile . ' — check storage/imports permissions.');
        }
        if (is_file($bizFile)) {
            @unlink($bizFile);
        }
        if (file_put_contents($bizFile, '') === false) {
            throw new \RuntimeException('Cannot write ' . $bizFile . ' — check storage/imports permissions.');
        }
        Settings::set('osm_refresh_active', '1');
        Settings::set('osm_refresh_step', '0');
    }

    public function isActive(): bool
    {
        return (string) Settings::get('osm_refresh_active', '0') === '1';
    }

    public function currentStepIndex(): int
    {
        return max(0, (int) Settings::get('osm_refresh_step', '0'));
    }

    /**
     * Run the next Overpass query (one state or metro). When finished, writes
     * storage/imports/businesses_osm.json and clears the active flag.
     *
     * @return array<string,mixed>
     */
    public function runNextStep(): array
    {
        @set_time_limit(180);
        $this->ensureDir();

        if (!$this->isActive() || !is_file($this->progressFile())) {
            $this->begin();
        }

        $progress = $this->readProgress();
        $steps = $this->steps();
        $step = (int) ($progress['step'] ?? 0);
        $total = count($steps);

        if ($step >= $total) {
            return $this->finalize($progress);
        }

        $job = $steps[$step];
        $townsByState = $this->loadTownsByState();
        $state = (string) ($job['state'] ?? '');
        $towns = $townsByState[$state] ?? [];
        if ($towns === []) {
            $progress['step'] = $step + 1;
            $this->writeProgress($progress);
            Settings::set('osm_refresh_step', (string) $progress['step']);

            return [
                'complete' => false,
                'step' => $progress['step'],
                'total_steps' => $total,
                'label' => (string) $job['label'],
                'added' => 0,
                'skipped' => true,
                'note' => 'No towns for ' . $state,
                'businesses' => $this->countBusinessesFile(),
            ];
        }

        $townIndex = $this->buildTownNameIndex($townsByState);
        try {
            if ($job['type'] === 'metro') {
                $lat = (float) $job['lat'];
                $lng = (float) $job['lng'];
                $radius = (float) $job['radius_km'];
                $tagged = $this->fetchOverpass($this->overpassQLAround($lat, $lng, $radius, self::SELECTORS));
                $named = $this->fetchOverpass($this->overpassQLAround($lat, $lng, $radius, self::NAME_SELECTORS));
                $elements = $this->mergeElements($tagged, $named);
            } else {
                // State-wide: tags only (name regex on whole states overloads Overpass).
                $elements = $this->fetchOverpass($this->overpassQL($state, self::SELECTORS));
            }
        } catch (Throwable $e) {
            return [
                'error' => 'Overpass fetch failed for ' . $job['label'] . ': ' . $e->getMessage(),
                'complete' => false,
                'step' => $step,
                'total_steps' => $total,
                'label' => (string) $job['label'],
                'businesses' => $this->countBusinessesFile(),
            ];
        }

        $seen = [
            'phones' => array_fill_keys($progress['phones'] ?? [], true),
            'hosts' => array_fill_keys($progress['hosts'] ?? [], true),
            'nameTown' => array_fill_keys($progress['name_town'] ?? [], true),
            'osmIds' => array_fill_keys($progress['osm_ids'] ?? [], true),
            'townIndex' => $townIndex,
            'dropped' => $progress['dropped'] ?? ['unnamed' => 0, 'noCoord' => 0, 'noTown' => 0, 'dup' => 0],
        ];

        $added = 0;
        $fh = fopen($this->businessesFile(), 'ab');
        if ($fh === false) {
            return ['error' => 'Cannot write ' . $this->businessesFile()];
        }
        foreach ($elements as $el) {
            $row = $this->processElement((array) $el, $towns, $state, $seen);
            if ($row === null) {
                continue;
            }
            fwrite($fh, json_encode($row, JSON_UNESCAPED_SLASHES) . "\n");
            $added++;
        }
        fclose($fh);

        $progress['phones'] = array_keys($seen['phones']);
        $progress['hosts'] = array_keys($seen['hosts']);
        $progress['name_town'] = array_keys($seen['nameTown']);
        $progress['osm_ids'] = array_keys($seen['osmIds']);
        $progress['dropped'] = $seen['dropped'];
        if ($job['type'] === 'metro') {
            $progress['per_metro'][(string) $job['id']] = $added;
        } else {
            $progress['per_state'][$state] = $added;
        }
        $progress['step'] = $step + 1;
        $this->writeProgress($progress);
        Settings::set('osm_refresh_step', (string) $progress['step']);

        if ($progress['step'] >= $total) {
            $final = $this->finalize($progress);
            $final['added'] = $added;
            $final['label'] = (string) $job['label'];

            return $final;
        }

        return [
            'complete' => false,
            'step' => $progress['step'],
            'total_steps' => $total,
            'label' => (string) $job['label'],
            'added' => $added,
            'raw' => count($elements),
            'businesses' => $this->countBusinessesFile(),
        ];
    }

    /**
     * @param array<string,mixed> $progress
     * @return array<string,mixed>
     */
    private function finalize(array $progress): array
    {
        $businesses = [];
        $file = $this->businessesFile();
        if (is_file($file)) {
            $fh = fopen($file, 'rb');
            if ($fh !== false) {
                while (($line = fgets($fh)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $row = json_decode($line, true);
                    if (is_array($row)) {
                        $businesses[] = $row;
                    }
                }
                fclose($fh);
            }
        }

        usort($businesses, static function (array $a, array $b): int {
            $sa = (string) ($a['state'] ?? '');
            $sb = (string) ($b['state'] ?? '');
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcmp((string) ($a['town'] ?? ''), (string) ($b['town'] ?? ''));
        });

        $out = [
            '_comment' => 'Generated on-server by App\\Services\\OsmRefreshService from OpenStreetMap via Overpass. Consumed by NationalImportSeeder::seedOsm().',
            'generated_at' => gmdate('c'),
            'source' => 'OpenStreetMap contributors, via Overpass API (ODbL)',
            'count' => count($businesses),
            'perState' => $progress['per_state'] ?? [],
            'perMetro' => $progress['per_metro'] ?? [],
            'businesses' => $businesses,
        ];

        $target = self::outputPath();
        $this->ensureDir();
        $json = json_encode($out, JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($target, $json) === false) {
            return ['error' => 'Failed to write ' . $target, 'complete' => false];
        }

        // Force import cron/runner to treat this as a new seed file.
        Settings::set(ProviderImportRunner::SETTING_OSM_OFFSET, '0');
        Settings::set(ProviderImportRunner::SETTING_OSM_FP, '');
        Settings::set('osm_refresh_active', '0');
        Settings::set('osm_refresh_step', '0');

        return [
            'complete' => true,
            'step' => count($this->steps()),
            'total_steps' => count($this->steps()),
            'businesses' => count($businesses),
            'path' => $target,
            'perState' => $out['perState'],
            'perMetro' => $out['perMetro'],
        ];
    }

    private function ensureDir(): void
    {
        $dir = base_path('storage/imports/osm');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $parent = base_path('storage/imports');
        if (!is_dir($parent)) {
            @mkdir($parent, 0755, true);
        }
    }

    private function progressFile(): string
    {
        return base_path('storage/imports/osm/progress.json');
    }

    private function businessesFile(): string
    {
        return base_path('storage/imports/osm/businesses.jsonl');
    }

    /** @return array<string,mixed> */
    private function readProgress(): array
    {
        $file = $this->progressFile();
        if (!is_file($file)) {
            return ['step' => 0];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : ['step' => 0];
    }

    /** @param array<string,mixed> $progress */
    private function writeProgress(array $progress): void
    {
        file_put_contents(
            $this->progressFile(),
            json_encode($progress, JSON_UNESCAPED_SLASHES)
        );
    }

    private function countBusinessesFile(): int
    {
        $file = $this->businessesFile();
        if (!is_file($file)) {
            return 0;
        }
        $n = 0;
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            return 0;
        }
        while (fgets($fh) !== false) {
            $n++;
        }
        fclose($fh);

        return $n;
    }

    /** @return array{phones:list<string>,hosts:list<string>,nameTown:list<string>} */
    private function loadExistingKeys(): array
    {
        $phones = [];
        $hosts = [];
        $nameTown = [];
        $file = base_path('database/seeds/national_import.json');
        if (!is_file($file)) {
            return ['phones' => $phones, 'hosts' => $hosts, 'nameTown' => $nameTown];
        }
        try {
            $data = json_decode((string) file_get_contents($file), true);
            foreach ((array) ($data['businesses'] ?? []) as $b) {
                $b = (array) $b;
                $ph = $this->digits((string) ($b['phone'] ?? ''));
                if ($ph !== '') {
                    $phones[] = $ph;
                }
                $h = $this->host((string) ($b['website'] ?? ''));
                if ($h !== '') {
                    $hosts[] = $h;
                }
                if (!empty($b['name']) && !empty($b['town'])) {
                    $nameTown[] = $this->slug((string) $b['name']) . '@' . $this->slug((string) $b['town']);
                }
            }
        } catch (Throwable) {
        }

        return [
            'phones' => array_values(array_unique($phones)),
            'hosts' => array_values(array_unique($hosts)),
            'nameTown' => array_values(array_unique($nameTown)),
        ];
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function loadTownsByState(): array
    {
        $file = base_path('database/seeds/towns_national.json');
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        $byState = [];
        foreach ((array) ($data['towns'] ?? []) as $t) {
            $t = (array) $t;
            $st = (string) ($t['state'] ?? '');
            if ($st === '') {
                continue;
            }
            $byState[$st][] = $t;
        }

        return $byState;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $townsByState
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function buildTownNameIndex(array $townsByState): array
    {
        $out = [];
        foreach ($townsByState as $state => $towns) {
            $map = [];
            foreach ($towns as $town) {
                $map[strtolower((string) $town['name'])] = $town;
            }
            $out[$state] = $map;
        }

        return $out;
    }

    /** @param list<array{sel:string,cat:string}> $selectors */
    private function overpassQL(string $state, array $selectors): string
    {
        $parts = [];
        foreach ($selectors as $s) {
            $parts[] = '  nwr' . $s['sel'] . '(area.a);';
        }

        return '[out:json][timeout:120];' . "\n"
            . 'area["ISO3166-2"="AU-' . $state . '"]->.a;' . "\n"
            . "(\n" . implode("\n", $parts) . "\n);\n"
            . 'out center tags;';
    }

    /** @param list<array{sel:string,cat:string}> $selectors */
    private function overpassQLAround(float $lat, float $lng, float $radiusKm, array $selectors): string
    {
        $meters = (int) round($radiusKm * 1000);
        $parts = [];
        foreach ($selectors as $s) {
            $parts[] = '  nwr' . $s['sel'] . '(around:' . $meters . ',' . $lat . ',' . $lng . ');';
        }

        return '[out:json][timeout:90];' . "\n"
            . "(\n" . implode("\n", $parts) . "\n);\n"
            . 'out center tags;';
    }

    /**
     * @param list<array<string,mixed>> $a
     * @param list<array<string,mixed>> $b
     * @return list<array<string,mixed>>
     */
    private function mergeElements(array $a, array $b): array
    {
        $map = [];
        foreach (array_merge($a, $b) as $el) {
            $key = (string) ($el['type'] ?? 'n') . ':' . (string) ($el['id'] ?? '');
            if ($key !== ':' && !isset($map[$key])) {
                $map[$key] = $el;
            }
        }

        return array_values($map);
    }

    /** @return list<array<string,mixed>> */
    private function fetchOverpass(string $ql): array
    {
        $body = 'data=' . rawurlencode($ql);
        $lastErr = 'unknown';
        $waits = [0, 5, 15];

        foreach ($waits as $i => $wait) {
            if ($wait > 0) {
                sleep($wait);
            }
            try {
                $raw = $this->httpPost(self::ENDPOINT, $body);
                $json = json_decode($raw, true);
                if (!is_array($json)) {
                    throw new \RuntimeException('Invalid JSON from Overpass');
                }

                return array_values((array) ($json['elements'] ?? []));
            } catch (Throwable $e) {
                $lastErr = $e->getMessage();
            }
        }

        throw new \RuntimeException($lastErr);
    }

    private function httpPost(string $url, string $body): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('curl_init failed');
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 150,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: ' . self::USER_AGENT,
                ],
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                throw new \RuntimeException($err !== '' ? $err : 'curl failed');
            }
            if ($code < 200 || $code >= 300) {
                throw new \RuntimeException('HTTP ' . $code);
            }

            return (string) $raw;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: " . self::USER_AGENT . "\r\n",
                'content' => $body,
                'timeout' => 150,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('HTTP request failed (allow_url_fopen / outbound HTTPS may be blocked)');
        }

        return $raw;
    }

    /**
     * @param array<string,mixed> $el
     * @param list<array<string,mixed>> $towns
     * @param array<string,mixed> $seen
     * @return array<string,mixed>|null
     */
    private function processElement(array $el, array $towns, string $state, array &$seen): ?array
    {
        $t = (array) ($el['tags'] ?? []);
        $name = trim((string) ($t['name'] ?? ''));
        if ($name === '') {
            $seen['dropped']['unnamed']++;

            return null;
        }

        $type = (string) ($el['type'] ?? 'n');
        $osmId = 'osm-' . ($type[0] ?? 'n') . (string) ($el['id'] ?? '');
        if (isset($seen['osmIds'][$osmId])) {
            return null;
        }

        $ll = $this->elementLatLng($el);
        if ($ll === null) {
            $seen['dropped']['noCoord']++;

            return null;
        }

        $near = $this->resolveTown($t, $ll[0], $ll[1], $state, $towns, $seen['townIndex']);
        if ($near === null) {
            $seen['dropped']['noTown']++;

            return null;
        }

        $phone = trim((string) ($t['phone'] ?? $t['contact:phone'] ?? ''));
        $website = trim((string) ($t['website'] ?? $t['contact:website'] ?? ''));
        $email = trim((string) ($t['email'] ?? $t['contact:email'] ?? ''));
        $ph = $this->digits($phone);
        $hh = $this->host($website);
        $nt = $this->slug($name) . '@' . $this->slug((string) $near['town']['name']);

        if (($ph !== '' && isset($seen['phones'][$ph]))
            || ($hh !== '' && isset($seen['hosts'][$hh]))
            || isset($seen['nameTown'][$nt])) {
            $seen['dropped']['dup']++;

            return null;
        }

        $defCat = 'mechanical';
        foreach (self::SELECTORS as $s) {
            $pair = explode('=', str_replace(['[', ']', '"'], '', $s['sel']), 2);
            if (count($pair) === 2 && ($t[$pair[0]] ?? null) === $pair[1]) {
                $defCat = $s['cat'];
                break;
            }
        }
        $cats = $this->catsFor($name, $defCat);
        $modes = preg_match('/mobile/i', $name) ? ['mobile', 'workshop'] : ['workshop'];
        $services = [];
        foreach ($cats as $c) {
            if (isset(self::TRADE_BLURB[$c])) {
                $services[] = self::TRADE_BLURB[$c];
            }
        }

        $seen['osmIds'][$osmId] = true;
        if ($ph !== '') {
            $seen['phones'][$ph] = true;
        }
        if ($hh !== '') {
            $seen['hosts'][$hh] = true;
        }
        $seen['nameTown'][$nt] = true;

        return [
            'id' => $osmId,
            'name' => $name,
            'town' => (string) $near['town']['name'],
            'region' => (string) ($near['town']['region'] ?? ''),
            'state' => $state,
            'cats' => $cats,
            'phone' => $phone,
            'website' => $website === '' ? '' : (preg_match('#^https?://#i', $website) ? $website : 'https://' . $website),
            'email' => $email,
            'address' => $this->buildAddress($t),
            'modes' => $modes,
            'services' => implode('; ', array_unique($services)),
            'source_type' => 'osm',
            'note' => 'Sourced from OpenStreetMap (community-maintained). Details may be incomplete — please confirm before booking.',
        ];
    }

    /** @return list<string> */
    private function catsFor(string $name, string $defCat): array
    {
        $set = [$defCat => true];
        foreach (self::NAME_RULES as $rule) {
            if (preg_match($rule['re'], $name)) {
                $set[$rule['cat']] = true;
            }
        }

        return array_keys($set);
    }

    /** @param array<string,mixed> $el @return array{0:float,1:float}|null */
    private function elementLatLng(array $el): ?array
    {
        if (isset($el['lat'], $el['lon']) && is_numeric($el['lat']) && is_numeric($el['lon'])) {
            return [(float) $el['lat'], (float) $el['lon']];
        }
        $c = $el['center'] ?? null;
        if (is_array($c) && isset($c['lat'], $c['lon'])) {
            return [(float) $c['lat'], (float) $c['lon']];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $tags
     * @param list<array<string,mixed>> $towns
     * @param array<string,array<string,array<string,mixed>>> $townIndex
     * @return array{town:array<string,mixed>,km:float}|null
     */
    private function resolveTown(array $tags, float $lat, float $lng, string $state, array $towns, array $townIndex): ?array
    {
        $locality = trim((string) ($tags['addr:suburb'] ?? $tags['addr:city'] ?? $tags['addr:town'] ?? ''));
        if ($locality !== '' && isset($townIndex[$state][strtolower($locality)])) {
            $hit = $townIndex[$state][strtolower($locality)];

            return [
                'town' => $hit,
                'km' => $this->distKm($lat, $lng, (float) $hit['lat'], (float) $hit['lng']),
            ];
        }

        return $this->nearestTown($towns, $lat, $lng, $state);
    }

    /**
     * @param list<array<string,mixed>> $towns
     * @return array{town:array<string,mixed>,km:float}|null
     */
    private function nearestTown(array $towns, float $lat, float $lng, string $state): ?array
    {
        $best = null;
        $bestD = INF;
        foreach ($towns as $t) {
            $dd = $this->distKm($lat, $lng, (float) $t['lat'], (float) $t['lng']);
            if ($dd < $bestD) {
                $bestD = $dd;
                $best = $t;
            }
        }
        if ($best !== null && $bestD <= self::MAX_KM) {
            return ['town' => $best, 'km' => $bestD];
        }

        $major = $this->nearestMajorCity($lat, $lng, $state);
        if ($major === null) {
            return null;
        }
        $radius = (float) ($major['scan_radius_km'] ?? 80);
        if ($best !== null && $bestD <= $radius) {
            return ['town' => $best, 'km' => $bestD];
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function nearestMajorCity(float $lat, float $lng, string $state): ?array
    {
        $data = MajorCityCoverageService::seedData();
        $best = null;
        $bestD = INF;
        foreach (array_merge($data['metros'], $data['cities']) as $row) {
            if ($state !== '' && ($row['state'] ?? '') !== $state) {
                continue;
            }
            $radius = (float) ($row['scan_radius_km'] ?? 50);
            $d = $this->distKm($lat, $lng, (float) $row['lat'], (float) $row['lng']);
            if ($d <= $radius && $d < $bestD) {
                $bestD = $d;
                $best = $row;
            }
        }

        return $best;
    }

    /** @param array<string,mixed> $t */
    private function buildAddress(array $t): string
    {
        $line = trim(implode(' ', array_filter([
            (string) ($t['addr:housenumber'] ?? ''),
            (string) ($t['addr:street'] ?? ''),
        ])));
        $locality = trim((string) ($t['addr:suburb'] ?? $t['addr:city'] ?? $t['addr:town'] ?? ''));
        $pc = trim((string) ($t['addr:postcode'] ?? ''));

        return trim(implode(', ', array_filter([$line, $locality, $pc])));
    }

    private function distKm(float $a, float $b, float $c, float $d): float
    {
        $R = 6371.0;
        $toRad = static fn (float $x): float => $x * M_PI / 180.0;
        $dLat = $toRad($c - $a);
        $dLng = $toRad($d - $b);
        $s = sin($dLat / 2) ** 2
            + cos($toRad($a)) * cos($toRad($c)) * sin($dLng / 2) ** 2;

        return 2 * $R * asin(min(1.0, sqrt($s)));
    }

    private function digits(string $s): string
    {
        $d = preg_replace('/\D+/', '', $s) ?? '';
        if (strlen($d) < 6) {
            return '';
        }
        $d = preg_replace('/^0/', '', $d) ?? $d;

        return substr($d, -9);
    }

    private function host(string $u): string
    {
        if ($u === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }
        $h = parse_url($u, PHP_URL_HOST);
        if (!is_string($h) || $h === '') {
            return '';
        }

        return strtolower(preg_replace('/^www\./', '', $h) ?? $h);
    }

    private function slug(string $s): string
    {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';

        return trim($s, '-');
    }
}
