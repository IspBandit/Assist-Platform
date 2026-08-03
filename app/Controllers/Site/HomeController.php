<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Provider;
use App\Models\Town;
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
        if (current_brand()->id() === 'localtorque') {
            return $this->view('localtorque.home', [
                'title' => 'Find local automotive expertise',
                'canonical' => url('/'),
                'jsonLd' => $this->organisationSchema(),
            ]);
        }
        if (current_brand()->id() === 'polaris') {
            return (new PolarisController())->home($request);
        }
        $nearbyTown = null;
        try {
            $nearbyTown = Town::defaultLaunchTown();
        } catch (Throwable) {
            $nearbyTown = null;
        }
        $nearbyProviders = [];
        $nearbyFindUrl = url('find');
        if ($nearbyTown !== null) {
            $nearbyProviders = $this->safe(static fn (): array => Provider::forHomeNearTown(
                (int) $nearbyTown['id'],
                isset($nearbyTown['region_id']) ? (int) $nearbyTown['region_id'] : null,
            ));
            if ($nearbyProviders !== []) {
                $label = (string) $nearbyTown['name'];
                if (!empty($nearbyTown['state_abbr'])) {
                    $label .= ', ' . $nearbyTown['state_abbr'];
                }
                $nearbyFindUrl = url('find') . '?' . http_build_query(['location' => $label]);
            }
        }

        $categories = $this->safe(fn () => \App\Models\ServiceCategory::activeAll());
        $categoryGroups = \App\Models\ServiceCategory::groupedForVanAssist($categories);
        $popularCategories = array_slice($categories, 0, 12);

        return $this->view('public.home', [
            'title'         => 'Caravan help, wherever you travel',
            'canonical'     => url('/'),
            'nearbyTown'        => $nearbyTown,
            'nearbyProviders'   => $nearbyProviders,
            'nearbyFindUrl'     => $nearbyFindUrl,
            'nearbyEndpoint'    => url('locations/nearby-providers'),
            'categories'        => $categories,
            'categoryGroups'    => $categoryGroups,
            'popularCategories' => $popularCategories,
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
