<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Demand\DemandRecorder;
use App\Services\Demand\FollowupService;
use App\Services\Demand\OutcomeService;

/**
 * Login-free customer outcome follow-up landing. The tokenised email link opens
 * a short "how did it go?" form so even anonymous customers can confirm which
 * provider they used, without needing an account.
 */
final class FollowupController extends Controller
{
    public function show(Request $request): Response
    {
        $followup = FollowupService::findByToken((string) $request->route('token'));
        if ($followup === null) {
            return $this->view('public.followup', [
                'title' => 'Link expired',
                'noindex' => true,
                'followup' => null,
                'request' => null,
                'providers' => [],
            ]);
        }
        $req = Database::selectOne('SELECT id, reference, title FROM service_requests WHERE id = ?', [(int) $followup['request_id']]);
        $providers = $req ? Database::select(
            'SELECT p.id AS provider_id, p.business_name FROM service_request_matches m '
            . 'JOIN providers p ON p.id = m.provider_id WHERE m.request_id = ? AND p.deleted_at IS NULL ORDER BY p.business_name',
            [(int) $req['id']]
        ) : [];

        return $this->view('public.followup', [
            'title'     => 'How did it go?',
            'noindex'   => true,
            'token'     => (string) $request->route('token'),
            'followup'  => $followup,
            'request'   => $req,
            'providers' => $providers,
        ]);
    }

    public function submit(Request $request): Response
    {
        $token = (string) $request->route('token');
        $followup = FollowupService::findByToken($token);
        if ($followup === null) {
            return $this->view('public.followup', [
                'title' => 'Link expired',
                'noindex' => true,
                'followup' => null,
                'request' => null,
                'providers' => [],
            ]);
        }
        $requestId = (int) $followup['request_id'];
        $used = (string) $request->input('used');

        if ($used === 'yes_vanassist' && (int) $request->input('provider_id') > 0) {
            $providerId = (int) $request->input('provider_id');
            $completed = $request->input('completed') === '1';
            $outcomeId = OutcomeService::upsert($requestId, $providerId, [
                'status'              => $completed ? 'completed' : 'selected',
                'used_via_vanassist'  => 1,
                'customer_id'         => $followup['customer_id'] !== null ? (int) $followup['customer_id'] : null,
                'satisfaction_rating' => (int) $request->input('satisfaction_rating') ?: null,
                'would_use_again'     => $request->input('would_use_again') !== null ? ($request->input('would_use_again') === '1') : null,
            ], 'customer');
            if ($outcomeId === null) {
                return $this->redirectWith(
                    '/followup/' . rawurlencode($token),
                    'error',
                    'That provider is not linked to this request. Please choose from the available providers.'
                );
            }
            FollowupService::markResponded((int) $followup['id'], $outcomeId);
        } else {
            if ($used === 'could_not_find' || $used === 'yes_elsewhere') {
                DemandRecorder::recordDemandGap(
                    $used === 'yes_elsewhere' ? 'found_elsewhere' : ((string) $request->input('reason') ?: 'other'),
                    ['request_id' => $requestId, 'comment' => $request->input('comment')]
                );
            }
            FollowupService::markResponded((int) $followup['id']);
        }

        return $this->view('public.followup', [
            'title' => 'Thank you',
            'noindex' => true,
            'followup' => $followup,
            'request' => null,
            'providers' => [],
            'done' => true,
        ]);
    }
}
