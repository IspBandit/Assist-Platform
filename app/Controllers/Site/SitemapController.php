<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Settings;
use Throwable;

/**
 * Dynamic sitemap.xml and robots.txt. The sitemap lists public, indexable URLs
 * built from the live catalogue (pages, categories, locations, providers, runs,
 * parks). robots.txt blocks private areas and disallows everything while the
 * site is not configured for indexing (e.g. private/coming-soon launch).
 */
final class SitemapController extends Controller
{
    public function xml(Request $request): Response
    {
        $urls = [];
        $this->addStatic($urls);
        $this->addRows($urls, "SELECT slug, updated_at FROM content_pages WHERE is_published = 1 AND noindex = 0", 'slug', 0.6);
        $this->addRows($urls, "SELECT slug, updated_at FROM service_categories WHERE is_active = 1", 'services/', 0.7);
        $this->addRows($urls, "SELECT slug, updated_at FROM regions WHERE is_active = 1", 'regions/', 0.6);
        // Only surface curated/indexable towns in the sitemap; the bulk national
        // locality import is noindex by default and would otherwise flood it.
        $this->addRows($urls, "SELECT slug, updated_at FROM towns WHERE is_active = 1 AND (noindex = 0 OR is_launch_town = 1 OR is_featured = 1)", 'towns/', 0.5);
        $this->addRows($urls, "SELECT slug, updated_at FROM providers WHERE status = 'active' AND deleted_at IS NULL", 'providers/', 0.8);
        $this->addRows($urls, "SELECT slug, updated_at FROM service_runs WHERE is_public = 1 AND deleted_at IS NULL AND status IN ('forming','confirmed','limited')", 'service-runs/', 0.7);
        $this->addRows($urls, "SELECT slug, updated_at FROM caravan_parks WHERE status = 'active' AND public_page_enabled = 1 AND deleted_at IS NULL", 'caravan-parks/', 0.5);

        // De-duplicate by location (e.g. a CMS page that also has a static entry).
        $seen = [];
        $urls = array_values(array_filter($urls, static function (array $u) use (&$seen): bool {
            if (isset($seen[$u['loc']])) {
                return false;
            }
            $seen[$u['loc']] = true;
            return true;
        }));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            if (!empty($u['lastmod'])) {
                $xml .= '<lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1) . '</lastmod>';
            }
            $xml .= '<priority>' . number_format((float) $u['priority'], 1) . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";

        return (new Response($xml, 200))
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(Request $request): Response
    {
        $allowIndex = (string) Settings::get('seo_allow_indexing', Settings::launchMode() === 'public' ? '1' : '0') === '1';

        $lines = ['User-agent: *'];
        if ($allowIndex) {
            foreach (['/admin', '/account', '/provider', '/park', '/install', '/billing'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = 'Allow: /';
        } else {
            $lines[] = 'Disallow: /';
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . url('sitemap.xml');

        return (new Response(implode("\n", $lines) . "\n", 200))
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** @param array<int,array<string,mixed>> $urls */
    private function addStatic(array &$urls): void
    {
        $static = [
            ['', 1.0],
            ['how-it-works', 0.6],
            ['for-providers', 0.6],
            ['for-caravan-parks', 0.6],
            ['request-assistance', 0.9],
            ['services', 0.8],
            ['regions', 0.7],
            ['providers', 0.8],
            ['service-runs', 0.8],
            ['faqs', 0.5],
            ['caravan-parks/apply', 0.4],
        ];
        foreach ($static as [$path, $priority]) {
            $urls[] = ['loc' => url($path), 'lastmod' => null, 'priority' => $priority];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $urls
     * @param string $prefix a path prefix ending in '/', or the sentinel 'slug'
     *                       when the slug itself is a top-level page path
     */
    private function addRows(array &$urls, string $sql, string $prefix, float $priority): void
    {
        try {
            $rows = Database::select($sql);
        } catch (Throwable) {
            return;
        }
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $path = $prefix === 'slug' ? $slug : $prefix . $slug;
            $urls[] = [
                'loc'      => url($path),
                'lastmod'  => !empty($row['updated_at']) ? date('Y-m-d', strtotime((string) $row['updated_at'])) : null,
                'priority' => $priority,
            ];
        }
    }
}
