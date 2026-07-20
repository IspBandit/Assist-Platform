<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceRequest;
use App\Services\Demand\DemandRecorder;
use App\Services\Demand\OutcomeService;
use App\Services\FileStorage;

final class AccountController extends Controller
{
    public function dashboard(Request $request): Response
    {
        if (current_brand()->id() === 'towsmart') {
            $items = Database::select(
                'SELECT id, label, result_status, created_at FROM towing_combinations WHERE user_id = ? AND brand_id = ? ORDER BY created_at DESC LIMIT 5',
                [(int) (current_user()['id'] ?? 0), current_brand()->databaseId()]
            );
            return $this->view('account.towsmart-dashboard', ['title' => 'My TowSmart account', 'user' => current_user(), 'items' => $items]);
        }
        if (current_brand()->id() === 'trailerwise') {
            return $this->view('account.trailerwise-dashboard', ['title' => 'My TrailerWise account', 'user' => current_user()]);
        }
        $customerId = $this->customerId();
        $requests = $customerId ? ServiceRequest::forCustomer($customerId) : [];

        return $this->view('account.dashboard', [
            'title'    => 'My account',
            'user'     => current_user(),
            'requests' => array_slice($requests, 0, 5),
        ]);
    }

    public function requests(Request $request): Response
    {
        $customerId = $this->customerId();
        return $this->view('account.requests', [
            'title'    => 'My requests',
            'requests' => $customerId ? ServiceRequest::forCustomer($customerId) : [],
        ]);
    }

    public function showRequest(Request $request): Response
    {
        $req = $this->ownedRequestOr404((string) $request->route('reference'));
        $id = (int) $req['id'];

        return $this->view('account.request-detail', [
            'title'   => 'Request ' . $req['reference'],
            'request' => $req,
            'images'  => ServiceRequest::images($id),
            'history' => ServiceRequest::statusHistory($id),
        ]);
    }

    /**
     * "Did you use a provider?" outcome-confirmation form for one of the
     * customer's own requests. Lists the providers matched to the request so
     * the customer can identify which one they actually used.
     */
    public function outcomeForm(Request $request): Response
    {
        $req = $this->ownedRequestOr404((string) $request->route('reference'));
        $id = (int) $req['id'];

        $providers = Database::select(
            'SELECT m.id AS match_id, p.id AS provider_id, p.business_name, p.slug '
            . 'FROM service_request_matches m JOIN providers p ON p.id = m.provider_id '
            . 'WHERE m.request_id = ? AND p.deleted_at IS NULL ORDER BY p.business_name',
            [$id]
        );
        $existing = Database::select(
            'SELECT provider_id, status, satisfaction_rating, value_band, would_use_again, issue_resolved '
            . 'FROM service_outcomes WHERE request_id = ?',
            [$id]
        );

        return $this->view('account.request-outcome', [
            'title'     => 'Outcome for ' . $req['reference'],
            'request'   => $req,
            'providers' => $providers,
            'existing'  => $existing,
        ]);
    }

    /** Process the customer's outcome confirmation. */
    public function outcomeSubmit(Request $request): Response
    {
        $req = $this->ownedRequestOr404((string) $request->route('reference'));
        $id = (int) $req['id'];
        $used = (string) $request->input('used');
        $redirect = '/account/requests/' . $req['reference'];

        if ($used === 'yes_vanassist') {
            $providerId = (int) $request->input('provider_id');
            if ($providerId === 0) {
                return $this->redirectWith($redirect, 'error', 'Please choose which provider you used.');
            }
            $completed = $request->input('completed') === '1';
            $booked = $completed || $request->input('booked') === '1';
            $status = $completed ? 'completed' : ($booked ? 'booked' : 'selected');

            $rating = (int) $request->input('satisfaction_rating') ?: null;
            $outcomeId = OutcomeService::upsert($id, $providerId, [
                'status'              => $status,
                'used_via_vanassist'  => 1,
                'customer_id'         => $this->customerId(),
                'issue_resolved'      => $request->input('issue_resolved') !== null ? ($request->input('issue_resolved') === '1') : null,
                'would_use_again'     => $request->input('would_use_again') !== null ? ($request->input('would_use_again') === '1') : null,
                'satisfaction_rating' => $rating,
                'value_band'          => $request->input('value_band') ?: null,
                'work_type'           => $request->input('work_type') ?: null,
                'by_user_id'          => (int) (current_user()['id'] ?? 0) ?: null,
            ], 'customer');
            if ($outcomeId === null) {
                return $this->redirectWith(
                    $redirect,
                    'error',
                    'That provider is not linked to this request. No outcome was recorded.'
                );
            }

            $reviewRating = (int) $request->input('review_rating');
            if ($reviewRating >= 1 && $reviewRating <= 5) {
                OutcomeService::submitReview($providerId, [
                    'customer_id'     => $this->customerId(),
                    'request_id'      => $id,
                    'outcome_id'      => $outcomeId,
                    'rating'          => $reviewRating,
                    'title'           => $request->input('review_title'),
                    'body'            => $request->input('review_body'),
                    'would_recommend' => $request->input('would_use_again') === '1' ? 1 : null,
                ]);
            }

            return $this->redirectWith($redirect, 'success', 'Thanks — your outcome has been recorded.');
        }

        if ($used === 'yes_elsewhere' || $used === 'could_not_find') {
            DemandRecorder::recordDemandGap(
                $used === 'yes_elsewhere' ? 'found_elsewhere' : ((string) $request->input('reason') ?: 'other'),
                [
                    'request_id'  => $id,
                    'town_id'     => $req['town_id'] ?? null,
                    'region_id'   => $req['region_id'] ?? null,
                    'category_id' => $req['primary_category_id'] ?? null,
                    'comment'     => $request->input('comment'),
                    'user_id'     => (int) (current_user()['id'] ?? 0) ?: null,
                ]
            );
            return $this->redirectWith($redirect, 'success', 'Thanks — your feedback helps us improve provider coverage.');
        }

        // not_yet / no_longer — record a light outcome_unknown signal if a provider is named.
        return $this->redirectWith($redirect, 'success', 'Thanks for letting us know.');
    }

    public function saved(Request $request): Response
    {
        $customerId = $this->customerId();
        $providers = $customerId ? Database::select(
            'SELECT p.id, p.business_name, p.slug, p.service_model, p.is_verified, sp.created_at AS saved_at '
            . 'FROM saved_providers sp JOIN providers p ON p.id = sp.provider_id '
            . "WHERE sp.customer_id = ? AND p.status = 'active' AND p.deleted_at IS NULL ORDER BY sp.created_at DESC",
            [$customerId]
        ) : [];

        return $this->view('account.saved', [
            'title'     => 'Saved providers',
            'providers' => $providers,
        ]);
    }

    public function saveProvider(Request $request): Response
    {
        $customerId = $this->customerId();
        $providerId = (int) $request->input('provider_id');
        if ($customerId !== null && $providerId > 0) {
            if ($request->input('action') === 'unsave') {
                OutcomeService::unsaveProvider($customerId, $providerId);
            } else {
                OutcomeService::saveProvider($customerId, $providerId);
            }
        }
        return $this->back();
    }

    public function requestImage(Request $request): Response
    {
        $image = Database::selectOne(
            'SELECT i.* FROM service_request_images i '
            . 'INNER JOIN service_requests sr ON sr.id = i.request_id '
            . 'WHERE i.id = ? AND sr.customer_id = ?',
            [(int) $request->input('id'), $this->customerId()]
        );
        if ($image === null) {
            $this->abort(404);
        }
        $thumb = $request->input('thumb') === '1' && $image['thumb_name'];
        $name = $thumb ? (string) $image['thumb_name'] : (string) $image['stored_name'];
        return FileStorage::serve('request_images', $name, 'request-image', (string) $image['mime_type']);
    }

    // ---- helpers -----------------------------------------------------------

    private function customerId(): ?int
    {
        $user = current_user();
        if ($user === null) {
            return null;
        }
        $customer = Database::selectOne('SELECT id FROM customers WHERE user_id = ?', [(int) $user['id']]);
        return $customer ? (int) $customer['id'] : null;
    }

    /** @return array<string,mixed> */
    private function ownedRequestOr404(string $reference): array
    {
        $customerId = $this->customerId();
        $req = $reference !== '' ? ServiceRequest::findByReference($reference) : null;
        if ($req === null || $customerId === null || (int) ($req['customer_id'] ?? 0) !== $customerId) {
            $this->abort(404, 'Request not found.');
        }
        return $req;
    }
}
