<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Settings;
use Throwable;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (current_brand()->id() === 'towsmart') {
            return (new TowSmartController())->home($request);
        }
        if (current_brand()->id() === 'trailerwise') {
            return (new TrailerWiseController())->home($request);
        }
        if (current_brand()->id() === 'polaris') {
            return (new PolarisController())->home($request);
        }

        $categories = $this->safe(fn () => \App\Models\ServiceCategory::activeAll());
        $categoryGroups = \App\Models\ServiceCategory::groupedForVanAssist($categories);

        return $this->view('public.home', [
            'title'         => 'Caravan help, wherever you travel',
            'canonical'     => url('/'),
            'categories'        => $categories,
            'categoryGroups'    => $categoryGroups,
            'freeMessage'   => Settings::get('free_launch_message', ''),
            'jsonLd'        => $this->organisationSchema(),
        ]);
    }

    /** @return array<int,string> Organization + WebSite JSON-LD blocks. */
    private function organisationSchema(): array
    {
        $siteName = (string) Settings::get('site_name', 'VanAssist');
        $org = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $siteName,
            'url'      => url('/'),
            'description' => (string) Settings::get('seo_default_description', 'Find caravan and RV specialists coming to your area across regional Australia.'),
        ];
        $logo = (string) Settings::get('seo_og_image', '');
        if ($logo !== '') {
            $org['logo'] = $logo;
        }
        $website = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $siteName,
            'url'      => url('/'),
        ];

        return array_filter([
            json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
        ]);
    }

    private function safe(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }
}
