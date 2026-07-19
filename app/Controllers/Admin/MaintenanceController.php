<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\MaintenanceAutoPipeline;
use App\Services\MajorCityCoverageService;
use App\Services\TownCoverageService;
use App\Services\Migrator;
use App\Services\NationalImportSeeder;
use App\Services\NationalTownSeeder;
use App\Services\OsmRefreshService;
use App\Services\ProviderImportRunner;
use App\Services\Settings;
use App\Core\View;
use Throwable;

/**
 * Super-administrator maintenance tools that let the site owner apply pending
 * database migrations and re-run the national provider import from the browser,
 * without needing shell/CLI access on the host. Both actions are idempotent.
 */
final class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireSuperAdmin();

        if ((string) $request->input('stop_osm', '') === '1' || (string) $request->input('stop_auto', '') === '1') {
            (new MaintenanceAutoPipeline())->clear();
            Settings::set('osm_refresh_active', '0');
            return $this->redirectWith('/admin/maintenance', 'success', 'Auto-refresh stopped.');
        }

        return $this->view('admin.maintenance.index', [
            'title'        => 'Maintenance',
            'pending'      => (new Migrator())->pending(),
            'emailMissing' => $this->missingEmailTemplates(),
            'emailDrift'   => $this->emailTemplateDrift(),
            'emailTotal'   => count(require base_path('database/seeds/email_templates.php')),
            'townCount'    => $this->townCount(),
            'townSeedTotal' => $this->townSeedTotal(),
            'osmTotal'     => $this->osmSeedTotal(),
            'localityTotal' => $this->localitySeedTotal(),
            'unclaimedCount' => $this->unclaimedCount(),
            'providerSources' => $this->providerSourceBreakdown(),
            'pageStatus'   => $this->pageContentStatus(),
            'majorCityCoverage' => MajorCityCoverageService::coverageReport(),
            'townCoverage' => TownCoverageService::report(20),
        ]);
    }

    /**
     * @return array{total:int,osm:int,locality:int,national:int,other:int,unclaimed:int,claimed:int}
     */
    private function providerSourceBreakdown(): array
    {
        $empty = [
            'total' => 0, 'osm' => 0, 'locality' => 0, 'national' => 0, 'other' => 0,
            'unclaimed' => 0, 'claimed' => 0,
        ];
        try {
            $total = (int) Database::scalar('SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL');
            $unclaimed = (int) Database::scalar('SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL AND is_unclaimed = 1');
            $osm = (int) Database::scalar(
                "SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL AND (source_type = 'osm' OR slug LIKE 'osm-%')"
            );
            $locality = (int) Database::scalar(
                "SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL AND source_type = 'locality'"
            );
            $national = (int) Database::scalar(
                "SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL AND ("
                . "source_type = 'national' OR ("
                . "IFNULL(source_type,'') NOT IN ('osm','locality') AND slug NOT LIKE 'osm-%' AND source_note LIKE '%public research%')"
                . ")"
            );
            $other = max(0, $total - $osm - $locality - $national);

            return [
                'total' => $total,
                'osm' => $osm,
                'locality' => $locality,
                'national' => $national,
                'other' => $other,
                'unclaimed' => $unclaimed,
                'claimed' => max(0, $total - $unclaimed),
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    private function osmSeedTotal(): int
    {
        try {
            return OsmRefreshService::seedBusinessCount();
        } catch (Throwable) {
            return 0;
        }
    }

    private function localitySeedTotal(): int
    {
        try {
            return NationalImportSeeder::localityCoverageCount();
        } catch (Throwable) {
            return 0;
        }
    }

    private function unclaimedCount(): int
    {
        try {
            return (int) Database::scalar('SELECT COUNT(*) FROM providers WHERE is_unclaimed = 1');
        } catch (Throwable) {
            return 0;
        }
    }

    private function townCount(): int
    {
        try {
            return (int) Database::scalar('SELECT COUNT(*) FROM towns');
        } catch (Throwable) {
            return 0;
        }
    }

    private function townSeedTotal(): int
    {
        try {
            $file = base_path('database/seeds/towns_national.json');
            if (!is_file($file)) {
                return 0;
            }
            $data = json_decode((string) file_get_contents($file), true);
            return is_array($data) ? (int) ($data['count'] ?? count((array) ($data['towns'] ?? []))) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Template keys defined in the seed file that are not yet present in the
     * database (so their emails would silently fail to queue).
     *
     * @return array<int,string>
     */
    private function missingEmailTemplates(): array
    {
        try {
            $templates = require base_path('database/seeds/email_templates.php');
            $keys = array_map(static fn ($t) => (string) $t['template_key'], $templates);
            $existing = array_column(
                Database::select('SELECT template_key FROM email_templates'),
                'template_key'
            );
            return array_values(array_diff($keys, $existing));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Template keys whose stored subject/body differ from the bundled seed file.
     *
     * @return array<int,string>
     */
    private function emailTemplateDrift(): array
    {
        try {
            $templates = require base_path('database/seeds/email_templates.php');
            $drift = [];
            foreach ($templates as $seed) {
                $key = (string) $seed['template_key'];
                $row = Database::selectOne(
                    'SELECT subject, html_body, text_body FROM email_templates WHERE template_key = ?',
                    [$key]
                );
                if ($row === null) {
                    continue;
                }
                $seedSig = $this->emailTemplateSignature($seed);
                $liveSig = $this->emailTemplateSignature([
                    'subject'   => (string) $row['subject'],
                    'html_body' => (string) $row['html_body'],
                    'text_body' => (string) ($row['text_body'] ?? ''),
                ]);
                if ($seedSig !== $liveSig) {
                    $drift[] = $key;
                }
            }

            return $drift;
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string,mixed> $tpl */
    private function emailTemplateSignature(array $tpl): string
    {
        return hash('sha256', implode("\n", [
            trim((string) ($tpl['subject'] ?? '')),
            trim((string) ($tpl['html_body'] ?? '')),
            trim((string) ($tpl['text_body'] ?? '')),
        ]));
    }

    public function migrate(Request $request): Response
    {
        $this->requireSuperAdmin();

        try {
            $ran = (new Migrator())->run();
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Migration failed: ' . $e->getMessage());
        }

        AuditLog::record('system.migrate', 'system', null, null, $ran === [] ? 'none' : implode(', ', $ran));

        $msg = $ran === []
            ? 'Database already up to date — nothing to apply.'
            : 'Applied ' . count($ran) . ' update(s): ' . implode(', ', $ran) . '.';
        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    public function reimport(Request $request): Response
    {
        $this->requireSuperAdmin();

        try {
            $summary = (new NationalImportSeeder())->seed();
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Import failed: ' . $e->getMessage());
        }

        AuditLog::record('system.reimport', 'system', null, null, (string) json_encode($summary));

        if (isset($summary['error'])) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Import: ' . (string) $summary['error']);
        }

        $msg = sprintf(
            'Import complete — %d new provider(s), %d existing listing(s) enriched; %d new town(s), %d town(s) enriched with postcode/coordinates; %d states and %d regions ensured.',
            (int) ($summary['providers'] ?? 0),
            (int) ($summary['providers_enriched'] ?? 0),
            (int) ($summary['towns'] ?? 0),
            (int) ($summary['towns_enriched'] ?? 0),
            (int) ($summary['states'] ?? 0),
            (int) ($summary['regions'] ?? 0)
        );
        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * Create the complete national town list (every Australian locality across
     * all states/territories) from the generated seed. Idempotent — existing
     * towns are never overwritten.
     */
    public function seedTowns(Request $request): Response
    {
        $this->requireSuperAdmin();

        try {
            $summary = (new NationalTownSeeder())->seed();
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Town import failed: ' . $e->getMessage());
        }

        if (isset($summary['error'])) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Town import: ' . (string) $summary['error']);
        }

        AuditLog::record('system.seed_towns', 'system', null, null, (string) json_encode($summary));

        $msg = sprintf(
            'National towns imported — %d new town(s) created from %d localities across %d states and %d regions.',
            (int) ($summary['created'] ?? 0),
            (int) ($summary['considered'] ?? 0),
            (int) ($summary['states'] ?? 0),
            (int) ($summary['regions'] ?? 0)
        );
        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * Import the free OpenStreetMap business set as unclaimed listings, in
     * time-boxed batches. With auto=1 the Maintenance page continues until
     * complete without further clicks. Fully idempotent (listings key on OSM id).
     */
    public function seedOsm(Request $request): Response
    {
        $this->requireSuperAdmin();

        $offset = max(0, (int) $request->input('offset', 0));
        $auto = (string) $request->input('auto', '') === '1';
        $pipeline = (string) $request->input('auto_pipeline', '');

        $r = (new ProviderImportRunner())->runOsmPass($offset, 18.0);
        if (isset($r['error'])) {
            return $this->redirectWith('/admin/maintenance', 'error', 'OSM import: ' . (string) $r['error']);
        }

        AuditLog::record('system.seed_osm', 'system', null, null, (string) json_encode($r));

        $providers = (int) $r['providers'];
        $enriched = (int) $r['providers_enriched'];
        $towns = (int) $r['towns'];
        $total = (int) $r['total'];
        $next = (int) $r['next'];

        if ($next >= 0) {
            $msg = sprintf(
                'OpenStreetMap import in progress — %s of %s businesses processed (%d new listing(s), %d enriched, %d new town(s) this pass).',
                number_format($next),
                number_format($total),
                $providers,
                $enriched,
                $towns
            );
            if ($auto) {
                return $this->continueForm(
                    'admin/maintenance/seed-osm',
                    [
                        'offset' => (string) $next,
                        'auto' => '1',
                        'auto_pipeline' => $pipeline,
                    ],
                    $msg
                );
            }

            return $this->redirectWith('/admin/maintenance?osm_offset=' . $next, 'success', $msg . ' Click Continue import to keep going.');
        }

        $msg = sprintf(
            'OpenStreetMap import complete — all %s businesses processed (%d new listing(s), %d enriched, %d new town(s) this pass).',
            number_format($total),
            $providers,
            $enriched,
            $towns
        );

        if ($auto && ($pipeline === 'locality' || $pipeline === 'full')) {
            $localityTotal = $this->localitySeedTotal();
            if ($localityTotal > 0) {
                return $this->continueForm(
                    'admin/maintenance/seed-locality',
                    [
                        'offset' => '0',
                        'auto' => '1',
                        'auto_pipeline' => $pipeline,
                    ],
                    $msg . ' Starting locality import…'
                );
            }

            return $this->continueForm(
                'admin/maintenance/feature-major-cities',
                [],
                $msg . ' Promoting major cities…'
            );
        }
        if ($auto && $pipeline === 'feature') {
            return $this->continueForm(
                'admin/maintenance/feature-major-cities',
                [],
                $msg . ' Promoting major cities…'
            );
        }

        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * Import the locality→provider research matrix as unclaimed listings with
     * per-town service areas. Batched like the OSM import; supports auto-continue.
     */
    public function seedLocality(Request $request): Response
    {
        $this->requireSuperAdmin();

        $offset = max(0, (int) $request->input('offset', 0));
        $auto = (string) $request->input('auto', '') === '1';
        $pipeline = (string) $request->input('auto_pipeline', '');

        $r = (new ProviderImportRunner())->runLocalityPass($offset, 18.0);
        if (isset($r['error'])) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Locality import: ' . (string) $r['error']);
        }

        AuditLog::record('system.seed_locality', 'system', null, null, (string) json_encode($r));

        $providers = (int) $r['providers'];
        $enriched = (int) $r['providers_enriched'];
        $towns = (int) $r['towns'];
        $areas = (int) $r['areas'];
        $total = (int) $r['total'];
        $next = (int) $r['next'];

        if ($next >= 0) {
            $msg = sprintf(
                'Locality-provider import in progress — %s of %s assignments processed (%d new listing(s), %d enriched, %d new town(s), %d new service area(s) this pass).',
                number_format($next),
                number_format($total),
                $providers,
                $enriched,
                $towns,
                $areas
            );
            if ($auto) {
                return $this->continueForm(
                    'admin/maintenance/seed-locality',
                    [
                        'offset' => (string) $next,
                        'auto' => '1',
                        'auto_pipeline' => $pipeline,
                    ],
                    $msg
                );
            }

            return $this->redirectWith('/admin/maintenance?locality_offset=' . $next, 'success', $msg . ' Click Continue import to keep going.');
        }

        $msg = sprintf(
            'Locality-provider import complete — all %s assignments processed (%d new listing(s), %d enriched, %d new town(s), %d new service area(s) this pass).',
            number_format($total),
            $providers,
            $enriched,
            $towns,
            $areas
        );

        if ($auto && ($pipeline === 'locality' || $pipeline === 'full' || $pipeline === 'feature')) {
            return $this->continueForm(
                'admin/maintenance/feature-major-cities',
                [],
                $msg . ' Promoting major cities…'
            );
        }

        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * Fetch the next OpenStreetMap Overpass batch on the server (one state or
     * metro per request). When the scan finishes, writes storage/imports/
     * businesses_osm.json and continues into the DB import when auto=1.
     */
    public function refreshOsm(Request $request): Response
    {
        $this->requireSuperAdmin();

        $auto = (string) $request->input('auto', '') === '1';
        $pipeline = (string) $request->input('auto_pipeline', '');
        $restart = (string) $request->input('restart', '') === '1';

        $svc = new OsmRefreshService();
        if ($restart || !$svc->isActive()) {
            $svc->begin();
        }

        try {
            $r = $svc->runNextStep();
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'OpenStreetMap refresh failed: ' . $e->getMessage());
        }

        if (isset($r['error'])) {
            $retry = max(0, (int) $request->input('retry', 0));
            $msg = (string) $r['error'];
            if ($auto && $retry < 3) {
                return $this->continueForm(
                    'admin/maintenance/refresh-osm',
                    [
                        'auto' => '1',
                        'auto_pipeline' => $pipeline !== '' ? $pipeline : 'full',
                        'restart' => '0',
                        'retry' => (string) ($retry + 1),
                    ],
                    $msg . ' Retrying (' . ($retry + 1) . '/3)…',
                    'error'
                );
            }

            return $this->redirectWith(
                '/admin/maintenance',
                'error',
                $msg . ' (OpenStreetMap Overpass may be blocked or timing out on this host. Try again later, or use Import last OSM scan if a dataset is already on the server.)'
            );
        }

        AuditLog::record('system.refresh_osm', 'system', null, null, (string) json_encode([
            'step' => $r['step'] ?? null,
            'complete' => $r['complete'] ?? false,
            'businesses' => $r['businesses'] ?? null,
            'label' => $r['label'] ?? null,
        ]));

        if (empty($r['complete'])) {
            $msg = sprintf(
                'OpenStreetMap scan — step %d of %d (%s): +%d listings, %s total so far.',
                (int) ($r['step'] ?? 0),
                (int) ($r['total_steps'] ?? 0),
                (string) ($r['label'] ?? ''),
                (int) ($r['added'] ?? 0),
                number_format((int) ($r['businesses'] ?? 0))
            );
            if ($auto) {
                return $this->continueForm(
                    'admin/maintenance/refresh-osm',
                    [
                        'auto' => '1',
                        'auto_pipeline' => $pipeline !== '' ? $pipeline : 'full',
                        'restart' => '0',
                    ],
                    $msg
                );
            }

            return $this->redirectWith('/admin/maintenance', 'success', $msg);
        }

        $msg = sprintf(
            'OpenStreetMap scan complete — %s businesses saved. Loading them into the directory…',
            number_format((int) ($r['businesses'] ?? 0))
        );
        $pipe = $pipeline !== '' ? $pipeline : 'full';

        if ($auto || $pipeline !== '') {
            return $this->continueForm(
                'admin/maintenance/seed-osm',
                [
                    'offset' => '0',
                    'auto' => '1',
                    'auto_pipeline' => $pipe,
                ],
                $msg
            );
        }

        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * One-click pipeline: apply OSM (+ locality) seed into the database, then
     * promote major cities. Uses GET + HTTP Refresh so it advances without JS.
     * Optional live Overpass scan via scan_osm=1 (often blocked on shared hosts).
     */
    public function seedProviders(Request $request): Response
    {
        $this->requireSuperAdmin();

        @set_time_limit(120);
        $scan = (string) $request->input('scan_osm', '') === '1';

        if ($this->townCount() < 1000) {
            try {
                $towns = (new ProviderImportRunner())->seedTowns();
                if (isset($towns['error'])) {
                    return $this->redirectWith('/admin/maintenance', 'error', 'Town import: ' . (string) $towns['error']);
                }
            } catch (Throwable $e) {
                return $this->redirectWith('/admin/maintenance', 'error', 'Town import failed: ' . $e->getMessage());
            }
        }

        if (!$scan && OsmRefreshService::seedBusinessCount() === 0) {
            return $this->redirectWith(
                '/admin/maintenance',
                'error',
                'No OpenStreetMap dataset is on the server yet. Tick “Also live-scan OpenStreetMap” or deploy businesses_osm.json first.'
            );
        }

        try {
            $started = (new MaintenanceAutoPipeline())->start(['include_osm_scan' => $scan]);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Cannot start auto-refresh: ' . $e->getMessage());
        }

        AuditLog::record('system.seed_providers_pipeline', 'system', null, null, (string) json_encode([
            'scan' => $scan,
            'token' => $started['token'],
        ]));

        return $this->redirect('/admin/maintenance/auto?t=' . rawurlencode($started['token']));
    }

    /**
     * Advances one unit of the auto pipeline. Browser follows via Refresh header
     * and a visible Continue link (works with JavaScript disabled).
     */
    public function runAuto(Request $request): Response
    {
        $this->requireSuperAdmin();

        $token = trim((string) $request->query('t', ''));
        if ($token === '') {
            return $this->redirectWith('/admin/maintenance', 'error', 'Missing auto-refresh token. Start again from Maintenance.');
        }

        @set_time_limit(180);
        $result = (new MaintenanceAutoPipeline())->tick($token);
        $message = (string) ($result['message'] ?? 'Working…');
        $error = isset($result['error']) ? (string) $result['error'] : null;

        if (!empty($result['continue'])) {
            $nextUrl = url('admin/maintenance/auto?t=' . rawurlencode($token));

            return $this->autoPage($message, $nextUrl, false, $error);
        }

        return $this->autoPage($message, null, true, $error);
    }

    private function autoPage(string $message, ?string $nextUrl, bool $done, ?string $error): Response
    {
        $html = View::render('admin.maintenance.auto', [
            'title'   => 'Maintenance',
            'message' => $message,
            'nextUrl' => $nextUrl,
            'done'    => $done,
            'error'   => $error,
        ]);
        $response = Response::html($html);
        if ($nextUrl !== null && $nextUrl !== '') {
            $response->withHeader('Refresh', '1;url=' . $nextUrl);
        }

        return $response;
    }

    /**
     * Render a short page that auto-POSTs to the next maintenance step.
     *
     * @param array<string,string|int> $fields
     */
    private function continueForm(string $action, array $fields, string $message, string $flashType = 'success'): Response
    {
        $normalized = [];
        foreach ($fields as $k => $v) {
            $normalized[(string) $k] = (string) $v;
        }

        return $this->view('admin.maintenance.continue', [
            'title'   => 'Maintenance',
            'action'  => url($action),
            'fields'  => $normalized,
            'message' => $message,
            'flashType' => $flashType,
        ]);
    }

    /**
     * Populate Pages & Blocks (CMS) from the bundled content seed. For the known
     * default pages, homepage blocks and FAQs this writes the full default copy
     * directly: missing rows are inserted, existing default rows are overwritten
     * with the latest content. This only touches the bundled keys/questions, so
     * any custom pages, blocks or FAQs you have added yourself are left alone.
     */
    public function seedContent(Request $request): Response
    {
        $this->requireSuperAdmin();

        try {
            $seedPath = base_path('database/seeds/content.php');
            if (!is_file($seedPath)) {
                return $this->redirectWith('/admin/maintenance', 'error', 'Content seed file not found at ' . $seedPath . '.');
            }
            $content = include $seedPath;
            if (!is_array($content)) {
                return $this->redirectWith('/admin/maintenance', 'error', 'Content seed did not load as an array (got ' . gettype($content) . ').');
            }
            $seedBlocks = count($content['homepage_blocks'] ?? []);
            $seedPages = count($content['pages'] ?? []);
            $seedFaqs = count($content['faqs'] ?? []);
            $blocksAdded = 0;
            $blocksUpdated = 0;
            $pagesAdded = 0;
            $pagesVerified = 0;
            $faqsAdded = 0;
            $faqsUpdated = 0;
            $errors = [];

            foreach (($content['homepage_blocks'] ?? []) as $b) {
                try {
                    $exists = (int) Database::scalar(
                        "SELECT COUNT(*) FROM content_blocks WHERE block_group = 'homepage' AND block_key = ?",
                        [$b['block_key']]
                    );
                    if ($exists === 0) {
                        Database::query(
                            'INSERT INTO content_blocks (block_group, block_key, title, subtitle, body, button_label, button_url, sort_order, is_active, created_at, updated_at) '
                            . "VALUES ('homepage', ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())",
                            [$b['block_key'], $b['title'], $b['subtitle'] ?? null, $b['body'] ?? null, $b['button_label'] ?? null, $b['button_url'] ?? null, $b['sort_order'] ?? 0]
                        );
                        $blocksAdded++;
                    } else {
                        Database::query(
                            'UPDATE content_blocks SET title = ?, subtitle = ?, body = ?, button_label = ?, button_url = ?, sort_order = ?, is_active = 1, updated_at = NOW() '
                            . "WHERE block_group = 'homepage' AND block_key = ?",
                            [$b['title'], $b['subtitle'] ?? null, $b['body'] ?? null, $b['button_label'] ?? null, $b['button_url'] ?? null, $b['sort_order'] ?? 0, $b['block_key']]
                        );
                        $blocksUpdated++;
                    }
                } catch (Throwable $e) {
                    $errors[] = 'block "' . ($b['block_key'] ?? '?') . '": ' . $e->getMessage();
                }
            }

            foreach (($content['pages'] ?? []) as $p) {
                try {
                    $pageKey = (string) ($p['page_key'] ?? '');
                    $slug    = (string) ($p['slug'] ?? '');
                    $title   = (string) ($p['title'] ?? '');
                    $body    = (string) ($p['body'] ?? '');

                    if ($pageKey === '' || $slug === '' || $title === '') {
                        $errors[] = 'page "' . $pageKey . '": seed entry missing page_key, slug or title';
                        continue;
                    }
                    if (trim($body) === '') {
                        $errors[] = 'page "' . $pageKey . '": seed body is empty in content.php';
                        continue;
                    }

                    $seedLen = strlen($body);

                    // Remove anything that would block this system page (both
                    // page_key and slug are UNIQUE) then insert fresh. This is
                    // more reliable than upsert when legacy installer rows used
                    // mismatched keys/slugs or left bodies blank.
                    Database::query(
                        'DELETE FROM content_pages WHERE page_key = ? OR slug = ?',
                        [$pageKey, $slug]
                    );
                    Database::query(
                        'INSERT INTO content_pages (page_key, title, slug, body, is_published, is_system, created_at, updated_at) '
                        . 'VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())',
                        [$pageKey, $title, $slug, $body]
                    );
                    $pagesAdded++;

                    $dbLen = (int) Database::scalar(
                        'SELECT CHAR_LENGTH(COALESCE(body, "")) FROM content_pages WHERE page_key = ? LIMIT 1',
                        [$pageKey]
                    );
                    if ($dbLen >= (int) max(50, $seedLen * 0.8)) {
                        $pagesVerified++;
                    } else {
                        $errors[] = 'page "' . $pageKey . '": body not saved correctly (DB has '
                            . $dbLen . ' chars, seed has ' . $seedLen . ' chars)';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'page "' . ($p['page_key'] ?? '?') . '": ' . $e->getMessage();
                }
            }

            foreach (($content['faqs'] ?? []) as $f) {
                try {
                    $exists = (int) Database::scalar('SELECT COUNT(*) FROM faqs WHERE question = ?', [$f['question']]);
                    if ($exists === 0) {
                        Database::query(
                            'INSERT INTO faqs (category, question, answer, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
                            [$f['category'], $f['question'], $f['answer'], $f['sort_order'] ?? 0]
                        );
                        $faqsAdded++;
                    } else {
                        Database::query(
                            'UPDATE faqs SET category = ?, answer = ?, sort_order = ?, is_active = 1, updated_at = NOW() WHERE question = ?',
                            [$f['category'], $f['answer'], $f['sort_order'] ?? 0, $f['question']]
                        );
                        $faqsUpdated++;
                    }
                } catch (Throwable $e) {
                    $errors[] = 'faq "' . substr((string) ($f['question'] ?? '?'), 0, 40) . '": ' . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Content population failed: ' . $e->getMessage());
        }

        Logger::info(sprintf(
            'Seed content: pages %d written / %d verified, blocks %d added / %d updated, faqs %d added / %d updated.',
            $pagesAdded, $pagesVerified, $blocksAdded, $blocksUpdated, $faqsAdded, $faqsUpdated
        ), ['errors' => $errors], 'content');

        AuditLog::record('system.seed_content', 'system', null, null, (string) json_encode([
            'blocks_added' => $blocksAdded, 'blocks_updated' => $blocksUpdated,
            'pages_written' => $pagesAdded, 'pages_verified' => $pagesVerified,
            'faqs_added' => $faqsAdded, 'faqs_updated' => $faqsUpdated,
            'errors' => $errors,
        ]));

        $totalApplied = $pagesAdded + $blocksAdded + $blocksUpdated + $faqsAdded + $faqsUpdated;
        if ($totalApplied === 0) {
            return $this->redirectWith('/admin/maintenance', 'error', sprintf(
                'Nothing applied. The content seed contained %d page(s), %d block(s) and %d FAQ(s) — if pages is 0 the seed file on the server is empty/outdated (re-deploy with -Full).',
                $seedPages, $seedBlocks, $seedFaqs
            ));
        }

        if ($seedPages > 0 && $pagesAdded === 0) {
            $errors[] = 'No pages were written despite ' . $seedPages . ' in the seed file — check System logs → content';
        }

        $msg = sprintf(
            'Website content populated from seed (%d pages, %d blocks, %d FAQs in seed) — pages: %d written, %d verified in DB; homepage blocks: %d added, %d updated; FAQs: %d added, %d updated.',
            $seedPages, $seedBlocks, $seedFaqs,
            $pagesAdded, $pagesVerified, $blocksAdded, $blocksUpdated, $faqsAdded, $faqsUpdated
        );

        if ($errors !== []) {
            // Surface what failed (and why) instead of silently skipping it. Full
            // detail is also written to Admin → System logs (channel: content).
            $shown = array_slice($errors, 0, 3);
            $more = count($errors) - count($shown);
            $msg .= ' ⚠ ' . count($errors) . ' item(s) failed: ' . implode(' | ', $shown)
                . ($more > 0 ? ' (+' . $more . ' more — see System logs → content)' : '');
            return $this->redirectWith('/admin/maintenance', 'error', $msg);
        }

        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * Insert any transactional email templates that are missing from the
     * database. Idempotent: existing templates (including admin edits) are left
     * untouched because the insert uses INSERT IGNORE on the unique template_key.
     */
    public function seedEmails(Request $request): Response
    {
        $this->requireSuperAdmin();

        try {
            $templates = require base_path('database/seeds/email_templates.php');
            $added = [];
            foreach ($templates as $tpl) {
                $stmt = Database::query(
                    'INSERT IGNORE INTO email_templates (template_key, name, subject, html_body, text_body, is_enabled, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())',
                    [$tpl['template_key'], $tpl['name'], $tpl['subject'], $tpl['html_body'], $tpl['text_body']]
                );
                if ($stmt->rowCount() > 0) {
                    $added[] = (string) $tpl['template_key'];
                }
            }
            $total = count($templates);
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Email template repair failed: ' . $e->getMessage());
        }

        AuditLog::record('system.seed_emails', 'system', null, null, $added === [] ? 'none' : implode(', ', $added));

        $msg = $added === []
            ? sprintf('All %d email templates are already present — nothing to add.', $total)
            : sprintf('Added %d missing template(s) of %d: %s.', count($added), $total, implode(', ', $added));
        return $this->redirectWith('/admin/maintenance', 'success', $msg);
    }

    /**
     * Replace one email template with the current bundled seed version. Use when
     * the seed copy was improved but the live row still has an older default or
     * you want to reset a template you have not customised.
     */
    public function syncEmailTemplate(Request $request): Response
    {
        $this->requireSuperAdmin();

        $key = trim((string) $request->input('template_key'));
        if ($key === '') {
            return $this->redirectWith('/admin/maintenance', 'error', 'Template key is required.');
        }

        try {
            $templates = require base_path('database/seeds/email_templates.php');
            $seed = null;
            foreach ($templates as $tpl) {
                if ((string) $tpl['template_key'] === $key) {
                    $seed = $tpl;
                    break;
                }
            }
            if ($seed === null) {
                return $this->redirectWith('/admin/maintenance', 'error', 'Unknown template key: ' . $key);
            }

            $updated = Database::query(
                'UPDATE email_templates SET name = ?, subject = ?, html_body = ?, text_body = ?, updated_at = NOW() WHERE template_key = ?',
                [$seed['name'], $seed['subject'], $seed['html_body'], $seed['text_body'], $key]
            )->rowCount();
            if ($updated === 0) {
                Database::query(
                    'INSERT INTO email_templates (template_key, name, subject, html_body, text_body, is_enabled, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())',
                    [$key, $seed['name'], $seed['subject'], $seed['html_body'], $seed['text_body']]
                );
            }
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Template sync failed: ' . $e->getMessage());
        }

        AuditLog::record('system.sync_email_template', 'email_template', $key);
        return $this->redirectWith('/admin/maintenance', 'success', 'Email template "' . $key . '" synced from seed.');
    }

    /** Promote major-city towns for sitemap and service filters. */
    public function featureMajorCities(Request $request): Response
    {
        $this->requireSuperAdmin();

        try {
            $updated = MajorCityCoverageService::featureMajorCityTowns();
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/maintenance', 'error', 'Major city promotion failed: ' . $e->getMessage());
        }

        AuditLog::record('system.feature_major_cities', 'system', null, null, (string) $updated);

        return $this->redirectWith(
            '/admin/maintenance',
            'success',
            sprintf('Marked %d major-city town(s) as featured and visible in search.', $updated)
        );
    }

    private function requireSuperAdmin(): void
    {
        if (!Auth::instance()->isSuperAdmin()) {
            $this->abort(403, 'Maintenance tools are restricted to super administrators.');
        }
    }

    /** @return array{seed:int,total:int,with_body:int} */
    private function pageContentStatus(): array
    {
        try {
            $seedPath = base_path('database/seeds/content.php');
            $seedPages = 0;
            if (is_file($seedPath)) {
                $seed = include $seedPath;
                $seedPages = is_array($seed['pages'] ?? null) ? count($seed['pages']) : 0;
            }
            $total = (int) Database::scalar('SELECT COUNT(*) FROM content_pages');
            $withBody = (int) Database::scalar(
                'SELECT COUNT(*) FROM content_pages WHERE CHAR_LENGTH(COALESCE(body, "")) > 80'
            );
            return ['seed' => $seedPages, 'total' => $total, 'with_body' => $withBody];
        } catch (Throwable) {
            return ['seed' => 0, 'total' => 0, 'with_body' => 0];
        }
    }
}
