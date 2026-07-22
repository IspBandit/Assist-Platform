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
        $blocks = $this->safe(
            fn () => Database::select(
                "SELECT * FROM content_blocks WHERE block_group = 'homepage' AND is_active = 1 ORDER BY sort_order"
            )
        );

        $confirmedRuns = $this->safe(
            fn () => Database::select(
                "SELECT r.*, p.business_name FROM service_runs r "
                . "INNER JOIN providers p ON p.id = r.provider_id "
                . "WHERE r.status = 'confirmed' AND r.is_public = 1 AND r.deleted_at IS NULL "
                . "ORDER BY r.start_date ASC LIMIT 4"
            )
        );

        $formingRuns = $this->safe(
            fn () => Database::select(
                "SELECT r.*, p.business_name FROM service_runs r "
                . "INNER JOIN providers p ON p.id = r.provider_id "
                . "WHERE r.status = 'forming' AND r.is_public = 1 AND r.deleted_at IS NULL "
                . "ORDER BY r.booking_deadline ASC LIMIT 4"
            )
        );

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

        $categories = $this->safe(
            fn () => Database::select(
                "SELECT name, slug FROM service_categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 12"
            )
        );

        $providerDirectoryCount = 0;
        try {
            $providerDirectoryCount = (int) Database::scalar(
                "SELECT COUNT(*) FROM providers WHERE status = 'active' AND deleted_at IS NULL"
            );
        } catch (Throwable) {
            $providerDirectoryCount = 0;
        }

        return $this->view('public.home', [
            'title'         => 'Caravan help, wherever you travel',
            'canonical'     => url('/'),
            'blocks'        => $blocks,
            'confirmedRuns' => $confirmedRuns,
            'formingRuns'   => $formingRuns,
            'nearbyTown'        => $nearbyTown,
            'nearbyProviders'   => $nearbyProviders,
            'nearbyFindUrl'     => $nearbyFindUrl,
            'nearbyEndpoint'    => url('locations/nearby-providers'),
            'categories'        => $categories,
            'providerDirectoryCount' => $providerDirectoryCount,
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
