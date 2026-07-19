<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Provider;
use App\Services\Demand\DemandRecorder;
use App\Services\FoundingGraphicService;

/**
 * Public provider directory and profile pages. Only active providers are
 * listed, and contact details are shown only where the provider has opted in.
 */
final class ProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 18;

        $townId = (int) $request->input('town') ?: null;
        $categoryId = (int) $request->input('category') ?: null;

        $result = Provider::publicDirectory($townId, $categoryId, $search, $perPage, ($page - 1) * $perPage);

        return $this->view('public.providers-index', [
            'title'           => 'Find a caravan service provider — VanAssist',
            'metaDescription' => 'Browse verified mobile and workshop caravan and RV service providers across the VanAssist network.',
            'canonical'       => url('providers'),
            'providers'       => $result['rows'],
            'total'           => $result['total'],
            'page'            => $page,
            'perPage'         => $perPage,
            'search'          => $search,
            'townId'          => $townId,
            'categoryId'      => $categoryId,
            'towns'           => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'categories'      => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $provider = Provider::findPublicBySlug($slug);
        if ($provider === null) {
            $this->abort(404, 'Provider not found.');
        }
        $id = (int) $provider['id'];

        DemandRecorder::recordProfileView($id);

        $runs = [];
        if (Database::tableExists('service_runs')) {
            $runs = Database::select(
                "SELECT id, title, slug, status, start_date FROM service_runs "
                . "WHERE provider_id = ? AND status IN ('forming','confirmed','limited') AND is_public = 1 AND deleted_at IS NULL "
                . "ORDER BY start_date LIMIT 10",
                [$id]
            );
        }

        return $this->view('public.provider-profile', [
            'title'           => $provider['seo_title'] ?: ($provider['business_name'] . ' — VanAssist'),
            'metaDescription' => $provider['seo_description'] ?: ('Caravan and RV services from ' . $provider['business_name'] . '.'),
            'canonical'       => url('providers/' . $provider['slug']),
            'provider'        => $provider,
            'services'        => Provider::services($id),
            'areas'           => Provider::areas($id),
            'licences'        => Database::select(
                "SELECT licence_type, issuing_authority FROM provider_licences "
                . "WHERE provider_id = ? AND verification_status = 'verified' AND display_publicly = 1 ORDER BY licence_type",
                [$id]
            ),
            'runs'            => $runs,
            'jsonLd'          => $this->providerSchema($provider),
            'promotionAd'     => FoundingGraphicService::deliveredAd($id),
        ]);
    }

    /**
     * LocalBusiness structured data for a provider profile. Only opted-in public
     * contact details are exposed, mirroring what the page itself shows.
     *
     * @param array<string,mixed> $provider
     */
    private function providerSchema(array $provider): ?string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'LocalBusiness',
            'name'     => (string) $provider['business_name'],
            'url'      => url('providers/' . $provider['slug']),
        ];
        if (!empty($provider['description'])) {
            $data['description'] = mb_substr(strip_tags((string) $provider['description']), 0, 300);
        }
        if (!empty($provider['show_public_phone']) && !empty($provider['public_phone'])) {
            $data['telephone'] = (string) $provider['public_phone'];
        }
        if (!empty($provider['town_name'])) {
            $data['address'] = [
                '@type'           => 'PostalAddress',
                'addressLocality' => (string) $provider['town_name'],
                'addressRegion'   => (string) ($provider['region_name'] ?? ''),
                'addressCountry'  => 'AU',
            ];
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}
