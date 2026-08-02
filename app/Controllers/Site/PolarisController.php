<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Polaris\AnalyticsService;
use App\Services\Polaris\BuyerStateService;
use App\Services\Polaris\CatalogueRepository;
use App\Services\Polaris\CatalogueService;
use App\Services\Polaris\ComparisonService;
use App\Services\Polaris\MatchScorer;
use App\Services\Polaris\NaturalLanguagePreferenceMapper;
use App\Services\Polaris\PreferenceProfile;
use App\Services\Polaris\SavedCatalogueService;
use App\Services\Polaris\TowCompatibilityService;
use App\Services\Polaris\VanAssistSurfacingService;
use RuntimeException;

final class PolarisController extends Controller
{
    private CatalogueRepository $catalogue;
    private MatchScorer $scorer;
    private TowCompatibilityService $tow;
    private ComparisonService $comparison;
    private VanAssistSurfacingService $vanAssist;
    private SavedCatalogueService $saved;
    private BuyerStateService $buyerState;

    public function __construct()
    {
        $this->catalogue = new CatalogueRepository();
        $this->scorer = new MatchScorer();
        $this->tow = new TowCompatibilityService();
        $this->comparison = new ComparisonService();
        $this->vanAssist = new VanAssistSurfacingService();
        $this->saved = new SavedCatalogueService();
        $this->buyerState = new BuyerStateService();
    }

    public function home(Request $request): Response
    {
        $this->requireCatalogue();
        $brandId = current_brand()->databaseId();
        $models = $this->catalogue->publishedModels($brandId, [], 6);
        $cards = $this->mapCards($models);

        return $this->view('polaris.home', [
            'title' => 'Find the right RV for the way you travel',
            'metaDescription' => 'Tell us what you tow, where you go and what matters most. Match new Australian caravans, hybrids and campers that genuinely fit.',
            'canonical' => current_brand()->url() . '/',
            'metaRobots' => $this->robotsMeta(),
            'models' => $cards,
            'heroExamples' => [
                'I have a Prado 250, travel as a couple, want to free camp for a week and have a budget under $90,000.',
                'We tow with a LandCruiser 300, need bunks for two kids, and mostly stay in caravan parks.',
                'Looking for a lightweight hybrid under 2,000 kg ATM for sealed and maintained gravel roads.',
            ],
            'jsonLd' => $this->organisationSchema(),
        ]);
    }

    public function find(Request $request): Response
    {
        $this->requireCatalogue();
        $prompt = trim((string) $request->query('q', ''));
        $stage = max(1, min(10, (int) $request->query('stage', $request->method() === 'POST' ? 10 : 1)));

        $hasExplicitAdults = $request->query('adults') !== null || $request->input('adults') !== null;
        $categories = $request->input('categories', $request->query('categories', []));
        if (!is_array($categories)) {
            $categories = $categories !== '' && $categories !== null ? [(string) $categories] : [];
        }

        $profile = PreferenceProfile::fromArray([
            'adults' => $request->input('adults', $request->query('adults', 2)),
            'children' => $request->input('children', $request->query('children', 0)),
            'max_budget_aud' => $request->input('max_budget_aud', $request->query('max_budget_aud')),
            'max_atm_kg' => $request->input('max_atm_kg', $request->query('max_atm_kg')),
            'max_length_m' => $request->input('max_length_m', $request->query('max_length_m')),
            'min_sleeps' => $request->input('min_sleeps', $request->query('min_sleeps')),
            'categories' => $categories,
            'require_bathroom' => (bool) $request->input('require_bathroom', $request->query('require_bathroom', false)),
            'off_grid_nights' => $request->input('off_grid_nights', $request->query('off_grid_nights', 0)),
            'priority_towability' => $request->input('priority_towability', $request->query('priority_towability', 'strong')),
            'priority_price' => $request->input('priority_price', $request->query('priority_price', 'strong')),
            'priority_off_grid' => $request->input('priority_off_grid', $request->query('priority_off_grid', 'nice')),
            'priority_comfort' => $request->input('priority_comfort', $request->query('priority_comfort', 'nice')),
            'priority_payload' => $request->input('priority_payload', $request->query('priority_payload', 'strong')),
        ]);

        $nlHints = [];
        $nlConfidence = null;
        $towHint = null;
        if ($prompt !== '') {
            $mapped = NaturalLanguagePreferenceMapper::map($prompt, $hasExplicitAdults ? $profile : null);
            if (!$hasExplicitAdults) {
                $profile = $mapped['profile'];
            }
            $nlHints = $mapped['hints'];
            $nlConfidence = $mapped['confidence'];
            $towHint = $mapped['tow_query'];
        }

        $matches = [];
        if ($stage >= 10) {
            $cards = $this->mapCards($this->catalogue->publishedModels(current_brand()->databaseId(), [], 60));
            foreach ($cards as $card) {
                $score = $this->scorer->score($card, $profile);
                $matches[] = $card + ['match' => $score];
            }
            usort($matches, static function (array $a, array $b): int {
                if ($a['match']['eligible'] !== $b['match']['eligible']) {
                    return $a['match']['eligible'] ? -1 : 1;
                }
                return $b['match']['overall'] <=> $a['match']['overall'];
            });
            try {
                $userId = current_user() !== null ? (int) current_user()['id'] : null;
                $this->buyerState->savePreference($userId, null, $profile);
            } catch (\Throwable) {
                // Preference persistence is best-effort for guided matching.
            }
            AnalyticsService::track(
                'match_completed',
                current_user() !== null ? (int) current_user()['id'] : null,
                null,
                null,
                ['matches' => count($matches)],
                current_user() !== null ? 'authenticated' : 'anonymous'
            );
        }

        return $this->view('polaris.find', [
            'title' => 'Find My RV',
            'metaDescription' => 'Guided matching for new Australian RVs based on how you travel.',
            'canonical' => current_brand()->url() . '/find',
            'metaRobots' => $this->robotsMeta(),
            'prompt' => $prompt,
            'stage' => $stage,
            'stages' => $this->guidedStages(),
            'profile' => $profile,
            'matches' => $matches,
            'categories' => CatalogueService::categoryLabels(),
            'nlHints' => $nlHints,
            'nlConfidence' => $nlConfidence,
            'towHint' => $towHint,
            'vehicleQ' => trim((string) $request->query('vehicle_q', $towHint ?? '')),
            'travelSurface' => trim((string) $request->query('travel_surface', '')),
            'layoutPref' => trim((string) $request->query('layout_pref', '')),
        ]);
    }

    public function browse(Request $request): Response
    {
        $this->requireCatalogue();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
            'production_status' => trim((string) $request->query('production_status', '')),
            'min_sleeps' => trim((string) $request->query('min_sleeps', '')),
            'max_atm_kg' => trim((string) $request->query('max_atm_kg', '')),
            'max_length_m' => trim((string) $request->query('max_length_m', '')),
            'max_budget_aud' => trim((string) $request->query('max_budget_aud', '')),
            'sort' => trim((string) $request->query('sort', 'name')),
        ];
        $models = $this->catalogue->publishedModels(current_brand()->databaseId(), $filters, 60);

        return $this->view('polaris.browse', [
            'title' => 'Browse new RVs',
            'metaDescription' => 'Browse current-production caravans, hybrids, campers and motorhomes.',
            'canonical' => current_brand()->url() . '/rvs',
            'metaRobots' => $this->robotsMeta(),
            'models' => $this->mapCards($models),
            'filters' => $filters,
            'categories' => CatalogueService::categoryLabels(),
            'sortOptions' => CatalogueService::sortOptions(),
        ]);
    }

    public function showModel(Request $request): Response
    {
        $this->requireCatalogue();
        $manufacturerSlug = (string) $request->route('manufacturer');
        $modelSlug = (string) $request->route('model');
        $model = $this->catalogue->findPublishedModel(
            current_brand()->databaseId(),
            $manufacturerSlug,
            $modelSlug
        );
        if ($model === null) {
            $this->abort(404);
        }

        $variants = $this->catalogue->variantsForModel((int) $model['id']);
        $enriched = [];
        foreach ($variants as $variant) {
            $extra = CatalogueService::enrichVariant($variant, (bool) $model['is_demo']);
            $enriched[] = $variant + $extra;
        }
        $primary = $enriched[0] ?? null;
        $modelSources = $this->catalogue->sourcesForModel((int) $model['id']);
        if ($modelSources === []) {
            $modelSources = $this->catalogue->sourcesForBrand(current_brand()->databaseId(), 3);
        }
        $provenance = array_map(
            static fn (array $source): array => CatalogueService::provenanceChip($source) + [
                'title' => (string) ($source['title'] ?? ''),
                'url' => (string) ($source['url'] ?? ''),
                'is_primary' => !empty($source['is_primary']),
            ],
            $modelSources
        );
        $specProvenance = $this->catalogue->variantProvenanceRows($primary, $modelSources);
        $relatedServices = $this->vanAssist->relatedServices(null, 6);
        AnalyticsService::track(
            'rv_viewed',
            current_user() !== null ? (int) current_user()['id'] : null,
            'model',
            (int) $model['id'],
            ['category' => (string) $model['category']],
            current_user() !== null ? 'authenticated' : 'anonymous'
        );

        return $this->view('polaris.model', [
            'title' => $model['manufacturer_name'] . ' ' . $model['name'],
            'metaDescription' => mb_substr(strip_tags((string) ($model['description'] ?? '')), 0, 300),
            'canonical' => current_brand()->url() . '/rvs/' . $manufacturerSlug . '/' . $modelSlug,
            'metaRobots' => $this->robotsMeta(),
            'model' => $model,
            'variants' => $enriched,
            'primary' => $primary,
            'floorplans' => $this->catalogue->floorplansForModel((int) $model['id']),
            'categoryLabel' => CatalogueService::categoryLabel((string) $model['category']),
            'provenance' => $provenance,
            'specProvenance' => $specProvenance,
            'relatedServices' => $relatedServices,
            'isSaved' => current_user() !== null && $this->isModelSaved((int) current_user()['id'], (int) $model['id']),
        ]);
    }

    public function manufacturers(Request $request): Response
    {
        $this->requireCatalogue();
        return $this->view('polaris.manufacturers', [
            'title' => 'Manufacturers',
            'metaDescription' => 'Australian RV manufacturers on Polaris.',
            'canonical' => current_brand()->url() . '/manufacturers',
            'metaRobots' => $this->robotsMeta(),
            'manufacturers' => $this->catalogue->publishedManufacturers(current_brand()->databaseId()),
        ]);
    }

    public function showManufacturer(Request $request): Response
    {
        $this->requireCatalogue();
        $slug = (string) $request->route('manufacturer');
        $manufacturer = $this->catalogue->findPublishedManufacturer(current_brand()->databaseId(), $slug);
        if ($manufacturer === null) {
            $this->abort(404);
        }

        return $this->view('polaris.manufacturer', [
            'title' => (string) $manufacturer['trading_name'],
            'metaDescription' => mb_substr(strip_tags((string) ($manufacturer['description'] ?? '')), 0, 300),
            'canonical' => current_brand()->url() . '/manufacturers/' . $slug,
            'metaRobots' => $this->robotsMeta(),
            'manufacturer' => $manufacturer,
            'models' => $this->catalogue->modelsForManufacturer((int) $manufacturer['id']),
        ]);
    }

    public function compare(Request $request): Response
    {
        $this->requireCatalogue();
        $token = trim((string) $request->route('token', ''));
        $ids = [];
        if ($token !== '') {
            $ids = $this->buyerState->loadComparisonModelIds(current_brand()->databaseId(), $token) ?? [];
            if ($ids === []) {
                $this->abort(404);
            }
        } else {
            $raw = (string) $request->query('ids', '');
            if ($raw !== '') {
                foreach (explode(',', $raw) as $part) {
                    $ids[] = (int) trim($part);
                }
            }
            foreach ((array) $request->query('id', []) as $id) {
                $ids[] = (int) $id;
            }
        }
        $cards = $this->mapCards($this->catalogue->findPublishedModelsByIds(current_brand()->databaseId(), $ids));
        $built = $this->comparison->build($cards);
        $catalogue = $this->mapCards($this->catalogue->publishedModels(current_brand()->databaseId(), [], 40));
        $shareUrl = null;
        if ($token !== '') {
            $shareUrl = current_brand()->url() . '/compare/' . $token;
        }

        return $this->view('polaris.compare', [
            'title' => 'Compare RVs',
            'metaDescription' => 'Compare up to four new RV models with transparent differences.',
            'canonical' => $shareUrl ?? (current_brand()->url() . '/compare'),
            'metaRobots' => $this->robotsMeta(),
            'comparison' => $built,
            'catalogue' => $catalogue,
            'selectedIds' => array_map(static fn (array $m): int => (int) $m['id'], $cards),
            'differencesOnly' => (string) $request->query('diff', '') === '1',
            'shareUrl' => $shareUrl,
            'shareToken' => $token !== '' ? $token : null,
        ]);
    }

    public function shareCompare(Request $request): Response
    {
        $this->requireCatalogue();
        $ids = array_map('intval', (array) $request->input('ids', []));
        try {
            $token = $this->buyerState->saveComparison(
                current_brand()->databaseId(),
                $ids,
                current_user() !== null ? (int) current_user()['id'] : null,
                trim((string) $request->input('title', '')) ?: null
            );
            return $this->redirect('/compare/' . $token);
        } catch (RuntimeException $e) {
            return $this->redirectWith('/compare', 'error', $e->getMessage());
        }
    }

    public function accountPreferences(Request $request): Response
    {
        $this->requireCatalogue();
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/account/preferences'));
        }
        $profile = $this->buyerState->loadPreferenceForUser((int) current_user()['id'])
            ?? PreferenceProfile::fromArray([]);
        return $this->view('polaris.account-preferences', [
            'title' => 'Travel preferences',
            'profile' => $profile,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function saveAccountPreferences(Request $request): Response
    {
        $this->requireCatalogue();
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/account/preferences'));
        }
        $profile = PreferenceProfile::fromArray([
            'adults' => $request->input('adults', 2),
            'children' => $request->input('children', 0),
            'max_budget_aud' => $request->input('max_budget_aud'),
            'max_atm_kg' => $request->input('max_atm_kg'),
            'max_length_m' => $request->input('max_length_m'),
            'min_sleeps' => $request->input('min_sleeps'),
            'require_bathroom' => $request->input('require_bathroom') === '1',
            'off_grid_nights' => $request->input('off_grid_nights', 0),
            'priority_towability' => $request->input('priority_towability', 'strong'),
            'priority_price' => $request->input('priority_price', 'strong'),
            'priority_off_grid' => $request->input('priority_off_grid', 'nice'),
            'priority_comfort' => $request->input('priority_comfort', 'nice'),
            'priority_payload' => $request->input('priority_payload', 'strong'),
        ]);
        $this->buyerState->savePreference((int) current_user()['id'], null, $profile);
        return $this->redirectWith('/account/preferences', 'success', 'Preferences saved for guided matching.');
    }

    public function accountComparisons(Request $request): Response
    {
        return $this->accountShell($request, 'comparisons', 'Saved comparisons',
            'Shared comparison links you create from Compare appear here once account history is wired. Create a shareable link from /compare for now.');
    }

    public function accountAlerts(Request $request): Response
    {
        return $this->accountShell($request, 'alerts', 'Price & update alerts',
            'Alert subscriptions are scaffolded. Prefer checking Saved models and manufacturer pages until notification delivery is enabled.');
    }

    public function accountTowVehicles(Request $request): Response
    {
        return $this->accountShell($request, 'tow-vehicles', 'Tow vehicles',
            'Garage tow vehicles remain on TowSmart. Use Tow Match on Polaris to assess catalogue models against a selected vehicle.');
    }

    private function accountShell(Request $request, string $section, string $title, string $note): Response
    {
        unset($request);
        $this->requireCatalogue();
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/account/' . $section));
        }
        return $this->view('polaris.account-shell', [
            'title' => $title,
            'section' => $section,
            'note' => $note,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function towMatch(Request $request): Response
    {
        $this->requireCatalogue();
        $query = trim((string) $request->query('vehicle_q', $request->input('vehicle_q', '')));
        $vehicleId = (int) $request->query('vehicle_id', $request->input('vehicle_id', 0));
        $modelId = (int) $request->query('model_id', $request->input('model_id', 0));

        $vehicles = $query !== '' ? $this->tow->searchVehicles($query, 12) : [];
        $vehicle = $vehicleId > 0 ? $this->tow->findVehicle($vehicleId) : null;
        $models = $this->mapCards($this->catalogue->publishedModels(current_brand()->databaseId(), [], 40));
        $selectedModel = null;
        foreach ($models as $model) {
            if ((int) $model['id'] === $modelId) {
                $selectedModel = $model;
                break;
            }
        }

        $result = null;
        if ($vehicle !== null && $selectedModel !== null) {
            $result = $this->tow->assess($vehicle, $selectedModel);
        }

        return $this->view('polaris.tow-match', [
            'title' => 'Tow Match',
            'metaDescription' => 'Check tow vehicle compatibility using TowSmart-powered guidance.',
            'canonical' => current_brand()->url() . '/tow-match',
            'metaRobots' => $this->robotsMeta(),
            'query' => $query,
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'models' => $models,
            'selectedModel' => $selectedModel,
            'result' => $result,
        ]);
    }

    public function floorplans(Request $request): Response
    {
        $this->requireCatalogue();
        $rows = \App\Core\Database::select(
            'SELECT f.*, m.name AS model_name, m.slug AS model_slug, mf.trading_name AS manufacturer_name, mf.slug AS manufacturer_slug
             FROM polaris_floorplans f
             INNER JOIN polaris_rv_models m ON m.id = f.model_id
             INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id
             WHERE m.brand_id = ? AND f.publication_status = \'published\' AND f.lifecycle_status = \'active\' AND f.deleted_at IS NULL
               AND m.publication_status = \'published\' AND m.lifecycle_status = \'active\' AND m.deleted_at IS NULL
             ORDER BY mf.trading_name ASC, m.name ASC, f.title ASC LIMIT 60',
            [current_brand()->databaseId()]
        );
        return $this->view('polaris.floorplans', [
            'title' => 'Floorplans',
            'metaDescription' => 'Browse RV floorplan layouts.',
            'canonical' => current_brand()->url() . '/floorplans',
            'metaRobots' => $this->robotsMeta(),
            'floorplans' => $rows,
        ]);
    }

    public function saved(Request $request): Response
    {
        $this->requireCatalogue();
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/saved'));
        }
        $models = $this->saved->listModels((int) current_user()['id']);
        $searches = $this->saved->listSearches((int) current_user()['id']);
        return $this->view('polaris.saved', [
            'title' => 'Saved',
            'metaDescription' => 'Saved RVs, searches and comparisons.',
            'canonical' => current_brand()->url() . '/saved',
            'metaRobots' => $this->robotsMeta(),
            'models' => $models,
            'searches' => $searches,
        ]);
    }

    public function saveModel(Request $request): Response
    {
        $this->requireCatalogue();
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode((string) ($request->input('return', '/saved'))));
        }
        try {
            $modelId = (int) $request->input('model_id');
            $this->saved->saveModel((int) current_user()['id'], $modelId);
            AnalyticsService::track('rv_saved', (int) current_user()['id'], 'model', $modelId, [], 'authenticated');
            return $this->redirectWith((string) $request->input('return', '/saved'), 'success', 'Model saved to your shortlist.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/saved', 'error', $e->getMessage());
        }
    }

    public function unsaveModel(Request $request): Response
    {
        $this->requireCatalogue();
        if (current_user() === null) {
            return $this->redirect('/login');
        }
        $modelId = (int) $request->input('model_id');
        $this->saved->removeModel((int) current_user()['id'], $modelId);
        return $this->redirectWith((string) $request->input('return', '/saved'), 'success', 'Removed from shortlist.');
    }

    private function isModelSaved(int $userId, int $modelId): bool
    {
        foreach ($this->saved->listModels($userId) as $row) {
            if ((int) $row['id'] === $modelId) {
                return true;
            }
        }
        return false;
    }

    public function buyingGuides(Request $request): Response
    {
        $this->requireCatalogue();
        return $this->view('polaris.buying-guides', [
            'title' => 'Buying guides',
            'metaDescription' => 'Practical Australian guides to weights, payload, construction and towing.',
            'canonical' => current_brand()->url() . '/buying-guides',
            'metaRobots' => $this->robotsMeta(),
            'guides' => [
                ['slug' => 'payload', 'title' => 'Payload', 'blurb' => 'Why ATM minus tare matters more than brochure optimism.'],
                ['slug' => 'caravan-weights', 'title' => 'Caravan weights', 'blurb' => 'ATM, GTM, tare and towball mass in plain English.'],
                ['slug' => 'construction', 'title' => 'Construction', 'blurb' => 'Chassis, frames and travel-condition suitability.'],
                ['slug' => 'batteries-and-solar', 'title' => 'Batteries and solar', 'blurb' => 'Off-grid expectations without false certainty.'],
                ['slug' => 'floorplans', 'title' => 'Floorplans', 'blurb' => 'Beds, bathrooms and living space trade-offs.'],
                ['slug' => 'warranties', 'title' => 'Warranties', 'blurb' => 'What manufacturer support language usually means.'],
                ['slug' => 'towing-compatibility', 'title' => 'Towing compatibility', 'blurb' => 'More than advertised tow capacity.'],
            ],
        ]);
    }

    public function buyingGuide(Request $request): Response
    {
        $this->requireCatalogue();
        $slug = (string) $request->route('slug');
        $guides = [
            'payload' => 'Payload',
            'caravan-weights' => 'Caravan weights',
            'construction' => 'Construction',
            'batteries-and-solar' => 'Batteries and solar',
            'floorplans' => 'Floorplans',
            'warranties' => 'Warranties',
            'towing-compatibility' => 'Towing compatibility',
        ];
        if (!isset($guides[$slug])) {
            $this->abort(404);
        }

        return $this->view('polaris.buying-guide', [
            'title' => $guides[$slug],
            'metaDescription' => 'Polaris buying guide: ' . $guides[$slug],
            'canonical' => current_brand()->url() . '/buying-guides/' . $slug,
            'metaRobots' => $this->robotsMeta(),
            'guideTitle' => $guides[$slug],
            'slug' => $slug,
        ]);
    }

    private function requireCatalogue(): void
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
    }

    private function robotsMeta(): string
    {
        return current_brand()->status() === 'private' ? 'noindex,nofollow' : 'index,follow';
    }

    /**
     * @param array<int,array<string,mixed>> $models
     * @return array<int,array<string,mixed>>
     */
    private function mapCards(array $models): array
    {
        $cards = [];
        foreach ($models as $model) {
            $extra = CatalogueService::enrichVariant([
                'tare_kg' => $model['tare_kg'] ?? null,
                'atm_kg' => $model['atm_kg'] ?? null,
                'price_status' => $model['price_status'] ?? null,
                'price_aud_cents' => $model['price_aud_cents'] ?? null,
                'price_effective_on' => $model['price_effective_on'] ?? null,
            ], (bool) ($model['is_demo'] ?? false));
            $cards[] = $model + $extra + [
                'category_label' => CatalogueService::categoryLabel((string) $model['category']),
                'url' => url('rvs/' . $model['manufacturer_slug'] . '/' . $model['slug']),
            ];
        }
        return $cards;
    }

    /** @return array<int,array{title:string,summary:string}> */
    private function guidedStages(): array
    {
        return [
            1 => ['title' => 'Who travels?', 'summary' => 'Adults, children, guests, pets and bed needs.'],
            2 => ['title' => 'What do you tow with?', 'summary' => 'Select a TowSmart vehicle or skip for now.'],
            3 => ['title' => 'Where do you travel?', 'summary' => 'Sealed roads through to remote tracks — clearly defined.'],
            4 => ['title' => 'How long off-grid?', 'summary' => 'Powered sites through to week-plus independence.'],
            5 => ['title' => 'Essential facilities', 'summary' => 'Bathroom, kitchen, climate and must-have features.'],
            6 => ['title' => 'Layout preferences', 'summary' => 'Beds, ensuite position and living arrangements.'],
            7 => ['title' => 'Capacity and independence', 'summary' => 'Water, solar, battery, gas and storage.'],
            8 => ['title' => 'Budget', 'summary' => 'Target and maximum spend in AUD.'],
            9 => ['title' => 'Priorities', 'summary' => 'Weight what matters most to you.'],
            10 => ['title' => 'Results', 'summary' => 'Explained matches with compromises and gaps.'],
        ];
    }

    /** @return array<int,string> */
    private function organisationSchema(): array
    {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Polaris',
            'url' => current_brand()->url() . '/',
            'description' => 'Australia’s new RV decision platform.',
        ];
        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'Polaris',
            'url' => current_brand()->url() . '/',
        ];

        return array_filter([
            json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
        ]);
    }
}
