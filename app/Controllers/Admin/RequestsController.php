<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceRequest;
use App\Services\AuditLog;
use App\Services\AutoMatchService;
use App\Services\EmailQueue;
use App\Services\FileStorage;
use App\Services\RequestWorkflow;
use Throwable;

/**
 * Admin moderation of customer service requests: review, approve/reject, mark
 * spam, change status with an audit trail, add internal notes and view images.
 */
final class RequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('requests.manage');
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = ServiceRequest::adminListing($status ?: null, $search, $perPage, ($page - 1) * $perPage);

        return $this->view('admin.requests.index', [
            'title'    => 'Service requests',
            'requests' => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
            'search'   => $search,
            'statuses' => RequestWorkflow::LABELS,
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('requests.manage');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];

        return $this->view('admin.requests.show', [
            'title'    => 'Request ' . $req['reference'],
            'request'  => $req,
            'images'   => ServiceRequest::images($id),
            'history'  => ServiceRequest::statusHistory($id),
            'notes'    => ServiceRequest::notes($id),
            'statuses' => RequestWorkflow::LABELS,
        ]);
    }

    public function changeStatus(Request $request): Response
    {
        $this->requirePermission('requests.manage');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];

        $action = (string) $request->input('action');
        $note = trim((string) $request->input('note')) ?: null;

        $target = match ($action) {
            'approve' => 'open',
            'reject'  => 'rejected',
            'set'     => (string) $request->input('status'),
            default   => null,
        };
        if ($target === null || !array_key_exists($target, RequestWorkflow::LABELS)) {
            $this->abort(400, 'Unknown status.');
        }

        RequestWorkflow::changeStatus($id, $target, current_user()['id'] ?? null, $note);

        $autoNote = '';
        if ($action === 'approve') {
            EmailQueue::queueTemplate('request_approved', (string) $req['contact_email'], (string) $req['contact_name'], [
                'customer_name'     => (string) $req['contact_name'],
                'request_reference' => (string) $req['reference'],
            ]);

            // Auto-match on approval when enabled. Never let it break approval.
            if (AutoMatchService::enabled()) {
                try {
                    $res = (new AutoMatchService())->process($id);
                    if (($res['state'] ?? '') === 'done') {
                        $autoNote = ' Auto-matched ' . (int) ($res['invited'] ?? 0) . ' provider(s).';
                    } elseif (($res['state'] ?? '') === 'fallback_admin') {
                        $autoNote = ' No provider met the auto-invite threshold — flagged for matching.';
                    }
                } catch (Throwable $e) {
                    Logger::error('Auto-match on approval failed: ' . $e->getMessage(), ['request' => $id], 'matching');
                }
            }
        }

        AuditLog::record('request.status_' . $target, 'service_request', (string) $id, (string) $req['status'], $target);
        return $this->redirectWith('/admin/requests/show?id=' . $id, 'success', 'Status updated to ' . RequestWorkflow::label($target) . '.' . $autoNote);
    }

    public function toggleSpam(Request $request): Response
    {
        $this->requirePermission('requests.manage');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];
        $new = $req['is_spam'] ? 0 : 1;
        Database::query('UPDATE service_requests SET is_spam = ?, updated_at = NOW() WHERE id = ?', [$new, $id]);
        if ($new === 1 && !in_array($req['status'], ['rejected', 'closed', 'cancelled'], true)) {
            RequestWorkflow::changeStatus($id, 'rejected', current_user()['id'] ?? null, 'Marked as spam');
        }
        AuditLog::record('request.spam_' . $new, 'service_request', (string) $id);
        return $this->redirectWith('/admin/requests/show?id=' . $id, 'success', $new ? 'Marked as spam.' : 'Spam flag cleared.');
    }

    public function addNote(Request $request): Response
    {
        $this->requirePermission('requests.manage');
        $req = $this->findOr404((int) $request->input('id'));
        $id = (int) $req['id'];
        $body = trim((string) $request->input('body'));
        if ($body !== '') {
            Database::query(
                'INSERT INTO service_request_notes (request_id, author_id, is_internal, body, created_at) VALUES (?, ?, 1, ?, NOW())',
                [$id, current_user()['id'] ?? null, $body]
            );
        }
        return $this->redirectWith('/admin/requests/show?id=' . $id, 'success', 'Note added.');
    }

    public function downloadImage(Request $request): Response
    {
        $this->requirePermission('requests.manage');
        $image = Database::selectOne('SELECT * FROM service_request_images WHERE id = ?', [(int) $request->input('image_id')]);
        if ($image === null) {
            $this->abort(404);
        }
        $thumb = $request->input('thumb') === '1' && $image['thumb_name'];
        $name = $thumb ? (string) $image['thumb_name'] : (string) $image['stored_name'];
        return FileStorage::serve('request_images', $name, 'request-image', (string) $image['mime_type']);
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
