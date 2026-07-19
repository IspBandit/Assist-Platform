<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceRequest;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\MatchingService;
use App\Services\RequestWorkflow;

/**
 * Admin matching console: review open requests, see scored provider
 * suggestions, invite providers, track their interest and release customer
 * contact details once a provider is engaged.
 */
final class MatchingController extends Controller
{
    public const MATCH_LABELS = [
        'suggested'  => 'Suggested',
        'invited'    => 'Invited',
        'interested' => 'Interested',
        'declined'   => 'Declined',
        'more_info'  => 'More info requested',
        'offered'    => 'Appointment offered',
        'accepted'   => 'Accepted',
        'unsuitable' => 'Unsuitable',
        'reported'   => 'Reported',
        'withdrawn'  => 'Withdrawn',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('requests.match');
        $pipeline = ['open', 'matching', 'provider_interested', 'information_requested', 'offered_appointment'];
        $placeholders = implode(',', array_fill(0, count($pipeline), '?'));

        $rows = Database::select(
            'SELECT sr.id, sr.reference, sr.title, sr.status, sr.urgency, sr.created_at, sr.auto_match_state, t.name AS town_name, '
            . 'c.name AS category_name, '
            . '(SELECT COUNT(*) FROM service_request_matches m WHERE m.request_id = sr.id) AS match_count, '
            . "(SELECT COUNT(*) FROM service_request_matches m WHERE m.request_id = sr.id AND m.status = 'interested') AS interested_count "
            . 'FROM service_requests sr '
            . 'LEFT JOIN towns t ON t.id = sr.town_id '
            . 'LEFT JOIN service_categories c ON c.id = sr.primary_category_id '
            . "WHERE sr.status IN ({$placeholders}) AND sr.deleted_at IS NULL AND sr.is_spam = 0 "
            . 'ORDER BY FIELD(sr.urgency, \'urgent\',\'high\',\'medium\',\'low\'), sr.created_at',
            $pipeline
        );

        return $this->view('admin.matching.index', [
            'title'    => 'Matching console',
            'requests' => $rows,
        ]);
    }

    public function request(Request $request): Response
    {
        $this->requirePermission('requests.match');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];

        return $this->view('admin.matching.request', [
            'title'       => 'Match ' . $req['reference'],
            'request'     => $req,
            'matches'     => $this->matchesFor($id),
            'suggestions' => (new MatchingService())->suggest($id, 20),
            'labels'      => self::MATCH_LABELS,
        ]);
    }

    public function add(Request $request): Response
    {
        $this->requirePermission('requests.match');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];
        $providerId = (int) $request->input('provider_id');
        $invite = $request->input('invite') === '1';

        $provider = Database::selectOne(
            'SELECT p.*, u.email AS user_email FROM providers p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ?',
            [$providerId]
        );
        if ($provider === null) {
            $this->abort(404);
        }

        $status = $invite ? 'invited' : 'suggested';
        $score = $request->input('score') !== null ? (float) $request->input('score') : null;
        Database::query(
            'INSERT INTO service_request_matches (request_id, provider_id, matched_by, match_score, status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE status = VALUES(status), match_score = VALUES(match_score), updated_at = NOW()',
            [$id, $providerId, current_user()['id'] ?? null, $score, $status]
        );

        if ($req['status'] === 'open') {
            RequestWorkflow::changeStatus($id, 'matching', current_user()['id'] ?? null, 'Provider matching started');
        }

        if ($invite) {
            $matchId = (int) Database::scalar('SELECT id FROM service_request_matches WHERE request_id = ? AND provider_id = ?', [$id, $providerId]);
            $to = $provider['email'] ?: $provider['user_email'];
            if ($to) {
                EmailQueue::queueTemplate('provider_match_invitation', $to, (string) $provider['business_name'], [
                    'provider_name' => (string) $provider['business_name'],
                    'town_name'     => (string) ($req['town_name'] ?? ''),
                    'action_url'    => url('provider/requests/' . $matchId),
                ]);
            }
        }

        AuditLog::record('match.' . $status, 'service_request', (string) $id, null, (string) $providerId);
        return $this->redirectWith('/admin/matching/request?id=' . $id, 'success', $invite ? 'Provider invited.' : 'Provider added as a suggestion.');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('requests.match');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];
        $matchId = (int) $request->input('match_id');
        $newStatus = (string) $request->input('status');
        if (!array_key_exists($newStatus, self::MATCH_LABELS)) {
            $this->abort(400);
        }

        $match = Database::selectOne('SELECT * FROM service_request_matches WHERE id = ? AND request_id = ?', [$matchId, $id]);
        if ($match === null) {
            $this->abort(404);
        }

        Database::query(
            'UPDATE service_request_matches SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?',
            [$newStatus, trim((string) $request->input('admin_note')) ?: $match['admin_note'], $matchId]
        );

        $this->syncRequestStatus($req, $newStatus);
        $this->notifyOnMatchStatus($req, (int) $match['provider_id'], $newStatus);

        AuditLog::record('match.update_' . $newStatus, 'service_request', (string) $id, (string) $match['status'], $newStatus);
        return $this->redirectWith('/admin/matching/request?id=' . $id, 'success', 'Match updated.');
    }

    public function release(Request $request): Response
    {
        $this->requirePermission('requests.match');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];
        $matchId = (int) $request->input('match_id');

        $match = Database::selectOne(
            'SELECT m.*, p.business_name, p.email AS provider_email, u.email AS user_email '
            . 'FROM service_request_matches m JOIN providers p ON p.id = m.provider_id '
            . 'LEFT JOIN users u ON u.id = p.user_id WHERE m.id = ? AND m.request_id = ?',
            [$matchId, $id]
        );
        if ($match === null) {
            $this->abort(404);
        }

        Database::query('UPDATE service_request_matches SET contact_released = 1, updated_at = NOW() WHERE id = ?', [$matchId]);

        $to = $match['provider_email'] ?: $match['user_email'];
        if ($to) {
            $contact = trim((string) $req['contact_name']) . ' — ' . (string) $req['contact_email']
                . ($req['contact_phone'] ? ' / ' . (string) $req['contact_phone'] : '');
            EmailQueue::queueRaw(
                $to,
                (string) $match['business_name'],
                'Customer contact released for ' . (string) $req['reference'],
                '<p>The customer for request <strong>' . e((string) $req['reference']) . '</strong> has agreed to be contacted.</p>'
                . '<p>' . e($contact) . '</p>'
                . '<p><a href="' . e(url('provider/requests/' . $matchId)) . '">View the request</a></p>',
                'Customer contact for ' . (string) $req['reference'] . ': ' . $contact
            );
        }

        AuditLog::record('match.contact_released', 'service_request', (string) $id, null, (string) $match['provider_id']);
        return $this->redirectWith('/admin/matching/request?id=' . $id, 'success', 'Customer contact released to the provider.');
    }

    // ---- helpers -----------------------------------------------------------

    /** @param array<string,mixed> $req */
    private function syncRequestStatus(array $req, string $matchStatus): void
    {
        $map = [
            'interested' => 'provider_interested',
            'more_info'  => 'information_requested',
            'offered'    => 'offered_appointment',
            'accepted'   => 'accepted',
        ];
        if (isset($map[$matchStatus]) && $req['status'] !== $map[$matchStatus]) {
            RequestWorkflow::changeStatus((int) $req['id'], $map[$matchStatus], current_user()['id'] ?? null, 'Provider ' . self::MATCH_LABELS[$matchStatus]);
        }
    }

    /** @param array<string,mixed> $req */
    private function notifyOnMatchStatus(array $req, int $providerId, string $matchStatus): void
    {
        $provider = Database::selectOne('SELECT business_name FROM providers WHERE id = ?', [$providerId]);
        $providerName = (string) ($provider['business_name'] ?? 'A provider');
        $email = (string) $req['contact_email'];
        $name = (string) $req['contact_name'];

        if ($matchStatus === 'interested') {
            EmailQueue::queueTemplate('provider_interested', $email, $name, [
                'customer_name'     => $name,
                'provider_name'     => $providerName,
                'request_reference' => (string) $req['reference'],
            ]);
        } elseif ($matchStatus === 'more_info') {
            EmailQueue::queueTemplate('information_requested', $email, $name, [
                'customer_name'     => $name,
                'request_reference' => (string) $req['reference'],
                'action_url'        => url('account/requests/' . $req['reference']),
            ]);
        } elseif ($matchStatus === 'offered') {
            EmailQueue::queueTemplate('appointment_offer', $email, $name, [
                'customer_name'     => $name,
                'provider_name'     => $providerName,
                'request_reference' => (string) $req['reference'],
                'action_url'        => url('account/requests/' . $req['reference']),
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function matchesFor(int $requestId): array
    {
        return Database::select(
            'SELECT m.*, p.business_name, p.slug, p.is_verified FROM service_request_matches m '
            . 'JOIN providers p ON p.id = m.provider_id WHERE m.request_id = ? ORDER BY m.match_score DESC, m.id',
            [$requestId]
        );
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $req = ServiceRequest::adminFind($id);
        if ($req === null) {
            $this->abort(404, 'Request not found.');
        }
        return $req;
    }
}
