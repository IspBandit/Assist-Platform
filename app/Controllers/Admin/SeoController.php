<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\Settings;

/**
 * Site-wide SEO settings: default title/description, social share image and the
 * master search-indexing switch. Stored in site_settings and consumed by the
 * shared SEO meta partial, sitemap and robots.txt.
 */
final class SeoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('seo.manage');
        return $this->view('admin.seo.index', [
            'title'       => 'SEO settings',
            'siteName'    => Settings::get('site_name', 'VanAssist'),
            'description' => Settings::get('seo_default_description', ''),
            'ogImage'     => Settings::get('seo_og_image', ''),
            'allowIndex'  => (string) Settings::get('seo_allow_indexing', Settings::launchMode() === 'public' ? '1' : '0') === '1',
            'launchMode'  => Settings::launchMode(),
            'sitemapUrl'  => url('sitemap.xml'),
            'robotsUrl'   => url('robots.txt'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('seo.manage');

        Settings::set('site_name', trim((string) $request->input('site_name')) ?: 'VanAssist');
        Settings::set('seo_default_description', trim((string) $request->input('seo_default_description')));
        Settings::set('seo_og_image', trim((string) $request->input('seo_og_image')));
        Settings::set('seo_allow_indexing', $request->input('seo_allow_indexing') ? '1' : '0');

        AuditLog::record('seo.settings_updated', 'site_settings', null);
        return $this->redirectWith('/admin/seo', 'success', 'SEO settings saved.');
    }
}
