<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Billing\BillingManager;
use App\Billing\Entitlements;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\FoundingGraphicService;
use App\Services\MembershipPresentationService;
use App\Services\PlanEntitlementService;
use App\Services\UsageMeteringService;

final class ProviderController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $provider = $this->currentProvider();

        $counts = ['services' => 0, 'areas' => 0, 'documents' => 0, 'open_requests' => 0, 'active_runs' => 0];
        $checklist = [];
        if ($provider !== null) {
            $id = (int) $provider['id'];
            $counts['services'] = (int) Database::scalar('SELECT COUNT(*) FROM provider_services WHERE provider_id = ?', [$id]);
            $counts['areas'] = (int) Database::scalar('SELECT COUNT(*) FROM provider_service_areas WHERE provider_id = ?', [$id]);
            $counts['documents'] = (int) Database::scalar('SELECT COUNT(*) FROM provider_documents WHERE provider_id = ?', [$id]);
            $counts['open_requests'] = (int) Database::scalar(
                "SELECT COUNT(*) FROM service_request_matches m JOIN service_requests sr ON sr.id = m.request_id "
                . "WHERE m.provider_id = ? AND sr.status IN ('open','matching')",
                [$id]
            );
            $counts['active_runs'] = (int) Database::scalar(
                "SELECT COUNT(*) FROM service_runs WHERE provider_id = ? AND status IN ('forming','confirmed') AND deleted_at IS NULL",
                [$id]
            );

            $checklist = [
                'Add a business description'   => trim((string) ($provider['description'] ?? '')) !== '',
                'List at least one service'    => $counts['services'] > 0,
                'Define a service area'        => $counts['areas'] > 0,
                'Upload a verification document' => $counts['documents'] > 0,
            ];
        }

        $membershipPlan = $provider !== null && !empty($provider['plan_id'])
            ? Database::selectOne('SELECT slug FROM billing_plans WHERE id = ?', [(int) $provider['plan_id']])
            : null;

        return $this->view('provider.dashboard', [
            'title'          => 'Provider dashboard',
            'user'           => current_user(),
            'provider'       => $provider,
            'counts'         => $counts,
            'checklist'      => $checklist,
            'billingEnabled' => BillingManager::enabled(),
            'membershipState' => $provider !== null
                ? (new MembershipPresentationService())->forProvider(
                    $provider,
                    BillingManager::enabled(),
                    isset($membershipPlan['slug']) ? (string) $membershipPlan['slug'] : null
                )
                : null,
            'foundingPromo'  => $provider !== null
                ? FoundingGraphicService::dashboardCard((int) $provider['id'], $provider)
                : null,
        ]);
    }

    /**
     * Provider billing portal. Hidden entirely (404) while ENABLE_BILLING=false
     * so no plan, usage or payment surface appears during the free launch.
     */
    public function billing(Request $request): Response
    {
        if (!BillingManager::enabled()) {
            $this->abort(404);
        }

        $provider = $this->currentProvider();
        if ($provider === null) {
            $this->abort(404);
        }

        $providerId = (int) $provider['id'];
        $entitlements = new PlanEntitlementService();
        $usage = new UsageMeteringService();

        $plan = $provider['plan_id']
            ? Database::selectOne('SELECT * FROM billing_plans WHERE id = ?', [(int) $provider['plan_id']])
            : null;

        $limitRows = [];
        foreach (Entitlements::limits() as $limitKey) {
            $cap = $entitlements->limit($providerId, $limitKey);
            $counter = Entitlements::limitCounterMap()[$limitKey] ?? null;
            $limitRows[$limitKey] = [
                'cap'  => $cap,
                'used' => $counter ? $usage->current($providerId, $counter) : 0,
            ];
        }

        $availablePlans = Database::select(
            'SELECT id, public_name, slug, monthly_price_cents, annual_price_cents, is_recommended '
            . 'FROM billing_plans WHERE is_active = 1 AND is_public = 1 ORDER BY display_order'
        );

        return $this->view('provider.billing', [
            'title'        => 'Billing',
            'provider'     => $provider,
            'plan'         => $plan,
            'limitRows'    => $limitRows,
            'availablePlans' => $availablePlans,
            'invoices'     => Database::select('SELECT invoice_number, invoice_date, total_cents, status FROM invoices WHERE provider_id = ? ORDER BY id DESC LIMIT 20', [$providerId]),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function currentProvider(): ?array
    {
        $user = current_user();
        if ($user === null) {
            return null;
        }
        return Database::selectOne('SELECT * FROM providers WHERE user_id = ? AND deleted_at IS NULL', [(int) $user['id']]);
    }
}
