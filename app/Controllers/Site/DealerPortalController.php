<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Polaris\DealerClaimService;
use RuntimeException;

/**
 * Dealer claim-first portal scaffold (no used inventory).
 */
final class DealerPortalController extends Controller
{
    private DealerClaimService $claims;

    public function __construct()
    {
        $this->claims = new DealerClaimService();
    }

    public function claims(Request $request): Response
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/portal/dealer/claims'));
        }
        $q = trim((string) $request->query('q', ''));
        $matches = [];
        try {
            $matches = $this->claims->searchForClaim(current_brand()->databaseId(), $q);
        } catch (\Throwable) {
            $matches = [];
        }
        return $this->view('polaris.portal.dealer-claims', [
            'title' => 'Claim dealer profile',
            'query' => $q,
            'matches' => $matches,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function submitClaim(Request $request): Response
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/portal/dealer/claims'));
        }
        try {
            $this->claims->submitClaim(
                current_brand()->databaseId(),
                (int) $request->input('dealer_id'),
                (int) current_user()['id'],
                trim((string) $request->input('evidence', ''))
            );
            return $this->redirectWith('/portal/dealer/claims', 'success', 'Dealer claim submitted for review.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/portal/dealer/claims', 'error', $e->getMessage());
        } catch (\Throwable) {
            return $this->redirectWith('/portal/dealer/claims', 'error', 'Claim failed. Ensure migration 096 has been applied.');
        }
    }
}
