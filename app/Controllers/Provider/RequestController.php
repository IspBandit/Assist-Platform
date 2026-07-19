<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceRequest;
use App\Services\AuditLog;
use App\Services\AutoMatchService;
use App\Services\EmailQueue;
use App\Services\FileStorage;
use App\Services\RequestWorkflow;

/**
 * Provider view of matched customer requests. Providers can express interest,
 * decline or ask for more information. Customer contact details are only shown
 * after an administrator has released them.
 */
final class RequestController extends Controller
{
    private const VISIBLE = ['invited', 'interested', 'more_info', 'offered', 'accepted'];

    public function incoming(Request $request): Response
    {
        $provider = $this->requireProvider();
        $placeholders = implode(',', array_fill(0, count(self::VISIBLE), '?'));

        $matches = Database::select(
            'SELECT m.id AS match_id, m.status AS match_status, m.contact_released, '
            . 'sr.reference, sr.title, sr.urgency, sr.status AS request_status, sr.created_at, '
            . 't.name AS town_name, c.name AS category_name '
            . 'FROM service_request_matches m '
            . 'JOIN service_requests sr ON sr.id = m.request_id '
            . 'LEFT JOIN towns t ON t.id = sr.town_id '
            . 'LEFT JOIN service_categories c ON c.id = sr.primary_category_id '
            . "WHERE m.provider_id = ? AND m.status IN ({$placeholders}) AND sr.deleted_at IS NULL "
            . 'ORDER BY m.updated_at DESC',
            array_merge([(int) $provider['id']], self::VISIBLE)
        );

        return $this->view('provider.requests', [
            'title'    => 'Incoming requests',
            'provider' => $provider,
            'matches'  => $matches,
        ]);
    }

    public function show(Request $request): Response
    {
        $provider = $this->requireProvider();
        $match = $this->matchOr404((int) $request->route('match'), (int) $provider['id']);
        $req = ServiceRequest::adminFind((int) $match['request_id']);
        if ($req === null) {
            $this->abort(404);
        }

        return $this->view('provider.request-detail', [
            'title'   => 'Request ' . $req['reference'],
            'provider' => $provider,
            'match'   => $match,
            'request' => $req,
            'images'  => ServiceRequest::images((int) $req['id']),
            'contactReleased' => (bool) $match['contact_released'],
        ]);
    }

    public function respond(Request $request): Response
    {
        $provider = $this->requireProvider();
        $match = $this->matchOr404((int) $request->route('match'), (int) $provider['id']);
        $matchId = (int) $match['id'];
        $action = (string) $request->input('action');

        $map = ['interested' => 'interested', 'decline' => 'declined', 'more_info' => 'more_info'];
        if (!isset($map[$action])) {
            $this->abort(400);
        }
        $newStatus = $map[$action];
        $note = trim((string) $request->input('provider_note')) ?: null;

        Database::query(
            'UPDATE service_request_matches SET status = ?, provider_note = COALESCE(?, provider_note), updated_at = NOW() WHERE id = ?',
            [$newStatus, $note, $matchId]
        );

        $req = Database::selectOne('SELECT * FROM service_requests WHERE id = ?', [(int) $match['request_id']]);
        if ($req !== null) {
            if ($newStatus === 'interested' && !in_array($req['status'], ['accepted', 'completed', 'closed'], true)) {
                RequestWorkflow::changeStatus((int) $req['id'], 'provider_interested', null, $provider['business_name'] . ' is interested');
                EmailQueue::queueTemplate('provider_interested', (string) $req['contact_email'], (string) $req['contact_name'], [
                    'customer_name'     => (string) $req['contact_name'],
                    'provider_name'     => (string) $provider['business_name'],
                    'request_reference' => (string) $req['reference'],
                ]);

                // When auto-matching is on, release the customer's contact to
                // this provider automatically (subject to consent + a cap).
                if (AutoMatchService::enabled()) {
                    (new AutoMatchService())->releaseContactOnInterest($match, $req, $provider);
                }
            } elseif ($newStatus === 'more_info') {
                RequestWorkflow::changeStatus((int) $req['id'], 'information_requested', null, $provider['business_name'] . ' requested more information');
                EmailQueue::queueTemplate('information_requested', (string) $req['contact_email'], (string) $req['contact_name'], [
                    'customer_name'     => (string) $req['contact_name'],
                    'request_reference' => (string) $req['reference'],
                    'action_url'        => url('account/requests/' . $req['reference']),
                ]);
            }
        }

        AuditLog::record('match.provider_' . $newStatus, 'service_request', (string) $match['request_id'], null, (string) $provider['id']);
        return $this->redirectWith('/provider/requests/' . $matchId, 'success', 'Thanks — your response has been recorded.');
    }

    /**
     * Provider updates the job/outcome status for a matched request. This feeds
     * the service_outcomes record of truth (provider-confirmed side) used by the
     * demand analytics. A provider confirmation never overrides a customer one.
     */
    public function outcome(Request $request): Response
    {
        $provider = $this->requireProvider();
        $match = $this->matchOr404((int) $request->route('match'), (int) $provider['id']);
        $status = (string) $request->input('outcome_status');

        $allowed = ['contacted', 'responded', 'quoted', 'selected', 'booked', 'in_progress', 'completed',
            'cancelled', 'unable_to_assist', 'outside_area', 'no_response'];
        if (!in_array($status, $allowed, true)) {
            $this->abort(400, 'Unknown status.');
        }

        \App\Services\Demand\OutcomeService::upsert(
            (int) $match['request_id'],
            (int) $provider['id'],
            [
                'status'              => $status,
                'match_id'            => (int) $match['id'],
                'work_type'           => $request->input('work_type') ?: null,
                'cancellation_reason' => in_array($status, ['cancelled', 'unable_to_assist', 'outside_area'], true) ? ($request->input('reason') ?: null) : null,
                'by_user_id'          => (int) (current_user()['id'] ?? 0) ?: null,
            ],
            'provider'
        );

        AuditLog::record('outcome.provider_' . $status, 'service_request', (string) $match['request_id'], null, (string) $provider['id']);
        return $this->redirectWith('/provider/requests/' . (int) $match['id'], 'success', 'Job status updated.');
    }

    public function image(Request $request): Response
    {
        $provider = $this->requireProvider();
        $image = Database::selectOne(
            'SELECT i.* FROM service_request_images i '
            . 'INNER JOIN service_request_matches m ON m.request_id = i.request_id '
            . 'WHERE i.id = ? AND m.provider_id = ?',
            [(int) $request->input('id'), (int) $provider['id']]
        );
        if ($image === null) {
            $this->abort(404);
        }
        $thumb = $request->input('thumb') === '1' && $image['thumb_name'];
        $name = $thumb ? (string) $image['thumb_name'] : (string) $image['stored_name'];
        return FileStorage::serve('request_images', $name, 'request-image', (string) $image['mime_type']);
    }

    // ---- helpers -----------------------------------------------------------

    /** @return array<string,mixed> */
    private function requireProvider(): array
    {
        $user = current_user();
        $provider = $user ? Database::selectOne('SELECT * FROM providers WHERE user_id = ? AND deleted_at IS NULL', [(int) $user['id']]) : null;
        if ($provider === null) {
            $this->abort(404, 'No provider profile is linked to your account.');
        }
        return $provider;
    }

    /** @return array<string,mixed> */
    private function matchOr404(int $matchId, int $providerId): array
    {
        $match = Database::selectOne(
            'SELECT * FROM service_request_matches WHERE id = ? AND provider_id = ?',
            [$matchId, $providerId]
        );
        if ($match === null) {
            $this->abort(404, 'Request not found.');
        }
        return $match;
    }
}
