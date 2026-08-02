<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Support;

use App\Core\Database;
use App\Helpers\Env;
use App\Platform\AiSearch\Budget\AiSettings;
use Throwable;

/**
 * Pre-release / ops checklist for Assist AI (AI-7 + DATA-012). Does not authorise production alone.
 */
final class AiReleaseGate
{
    /**
     * @return array{status:string,checks:list<array{id:string,ok:bool,detail:string}>}
     */
    public function evaluate(): array
    {
        $settings = AiSettings::get();
        $checks = [];

        $checks[] = [
            'id' => 'ask_flag_off_or_intentional',
            'ok' => true,
            'detail' => AiSearchFeature::enabled()
                ? 'Ask VanAssist flag is ON — confirm intentional for this environment.'
                : 'Ask VanAssist flag is OFF (safe default).',
        ];

        $paidOff = empty($settings['ai_enabled']) || empty($settings['openai_enabled']);
        $checks[] = [
            'id' => 'paid_ai_disabled_or_capped',
            'ok' => $paidOff || (
                (int) $settings['daily_request_cap'] > 0
                && (float) $settings['daily_budget_aud'] > 0
                && $settings['model_allowlist'] !== []
            ),
            'detail' => $paidOff
                ? 'Paid AI disabled (safe default).'
                : 'Paid AI enabled — require non-zero caps, budget and allowlist.',
        ];

        $keyInEnv = trim((string) Env::get('OPENAI_API_KEY', '')) !== '';
        $checks[] = [
            'id' => 'api_key_not_required_when_off',
            'ok' => $paidOff || $keyInEnv,
            'detail' => $paidOff
                ? 'No paid AI key required while interpreters are off.'
                : ($keyInEnv ? 'OPENAI_API_KEY present in environment.' : 'OPENAI_API_KEY missing while paid AI enabled.'),
        ];

        $checks[] = [
            'id' => 'datasets_labelled_path',
            'ok' => true,
            'detail' => DatasetSearchFeature::enabled()
                ? 'Dataset routing ON — confirm provenance labels on Ask results.'
                : 'Dataset routing OFF (safe default).',
        ];

        $routes = (string) @file_get_contents(base_path('routes/web.php'));
        $rateWired = str_contains($routes, 'ask_rate:public.ask-vanassist')
            || str_contains($routes, 'rate:public.ask-vanassist,');
        $checks[] = [
            'id' => 'ask_rate_limit_wired',
            'ok' => $rateWired,
            'detail' => $rateWired
                ? 'Ask VanAssist route has rate middleware (with CAPTCHA unlock escalation).'
                : 'Ask VanAssist rate middleware missing.',
        ];

        $captchaEscalation = str_contains($routes, 'ask.unlock')
            && is_file(base_path('app/Middleware/AskVanAssistRateLimit.php'));
        $checks[] = [
            'id' => 'ask_captcha_escalation_wired',
            'ok' => $captchaEscalation,
            'detail' => $captchaEscalation
                ? 'Ask Turnstile unlock path is wired.'
                : 'Ask CAPTCHA unlock path missing.',
        ];

        $osmOffline = is_file(base_path('app/Platform/DataSources/Connectors/OsmOfflineSeedConnector.php'))
            && is_file(base_path('database/migrations/113_osm_offline_seed_connector.sql'));
        $checks[] = [
            'id' => 'osm_offline_seed_wired',
            'ok' => $osmOffline,
            'detail' => $osmOffline
                ? 'Offline OSM seed connector registered (no Ask Overpass).'
                : 'Offline OSM seed connector missing.',
        ];

        $adminRoutes = (string) @file_get_contents(base_path('routes/admin.php'));
        $data012Wired = str_contains($adminRoutes, 'admin.data-sources.datasets')
            && is_file(base_path('database/migrations/108_assist_traveller_facilities.sql'))
            && is_file(base_path('database/migrations/109_government_datasets.sql'))
            && is_file(base_path('database/migrations/110_government_dataset_au_toilet_map.sql'));
        $checks[] = [
            'id' => 'data012_ingest_wired',
            'ok' => $data012Wired,
            'detail' => $data012Wired
                ? 'DATA-012 catalogue routes and migrations 108–110 are present.'
                : 'DATA-012 catalogue routes or migrations 108–110 missing.',
        ];

        $facilitiesOn = TravellerFacilitiesFeature::enabled();
        $reviewedCount = $this->reviewedFacilityCount();
        $facilitiesReady = !$facilitiesOn || $reviewedCount > 0;
        $checks[] = [
            'id' => 'traveller_facilities_populated',
            'ok' => $facilitiesReady,
            'detail' => $facilitiesOn
                ? ($reviewedCount > 0
                    ? 'Traveller facilities flag ON with ' . $reviewedCount . ' active reviewed/verified row(s).'
                    : 'Traveller facilities flag ON but no active reviewed/verified rows — import and approve via DATA-012 first.')
                : 'Traveller facilities flag OFF (safe default). Populate via /admin/data-sources/datasets before enabling.',
        ];

        $routerSrc = (string) @file_get_contents(base_path('app/Platform/AiSearch/Routing/SearchRouter.php'));
        $routerOk = str_contains($routerSrc, "'traveller_facilities'");
        $checks[] = [
            'id' => 'traveller_facilities_router',
            'ok' => $routerOk,
            'detail' => $routerOk
                ? 'SearchRouter can select traveller_facilities adapter.'
                : 'SearchRouter does not include traveller_facilities.',
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if (!$check['ok']) {
                $allOk = false;
                break;
            }
        }

        return [
            'status' => $allOk ? 'ready_for_conditional_ops' : 'blocked',
            'checks' => $checks,
        ];
    }

    private function reviewedFacilityCount(): int
    {
        try {
            return (int) Database::scalar(
                "SELECT COUNT(*) FROM traveller_facilities
                 WHERE deleted_at IS NULL AND status = 'active'
                   AND verification_status IN ('reviewed', 'verified')"
            );
        } catch (Throwable) {
            return 0;
        }
    }
}
