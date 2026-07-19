<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaravanPark;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\FileStorage;

/**
 * Admin management of caravan park partners: review applications, approve/reject/
 * suspend, edit details, review documents and triage service-day requests.
 */
final class ParksController extends Controller
{
    private const STATUSES = [
        'draft' => 'Draft', 'pending' => 'Pending', 'active' => 'Active',
        'suspended' => 'Suspended', 'rejected' => 'Rejected',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = CaravanPark::adminListing($status ?: null, $search, $perPage, ($page - 1) * $perPage);

        return $this->view('admin.parks.index', [
            'title'    => 'Caravan parks',
            'parks'    => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
            'search'   => $search,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];

        return $this->view('admin.parks.show', [
            'title'       => $park['name'],
            'park'        => $park,
            'documents'   => CaravanPark::documents($id),
            'serviceDays' => CaravanPark::serviceDayRequests($id),
            'managers'    => Database::select(
                'SELECT u.name, u.email, cpu.role FROM caravan_park_users cpu '
                . 'JOIN users u ON u.id = cpu.user_id WHERE cpu.park_id = ?',
                [$id]
            ),
            'requestCount' => (int) Database::scalar('SELECT COUNT(*) FROM service_requests WHERE park_id = ? AND deleted_at IS NULL', [$id]),
            'statuses'    => self::STATUSES,
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        return $this->view('admin.parks.form', [
            'title'   => 'Edit ' . $park['name'],
            'park'    => $park,
            'towns'   => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWith('/admin/parks/form?id=' . $id, 'error', 'Park name is required.');
        }
        $townId = (int) $request->input('town_id') ?: null;
        $town = $townId ? Database::selectOne('SELECT region_id, state_id FROM towns WHERE id = ?', [$townId]) : null;

        Database::query(
            'UPDATE caravan_parks SET name = ?, address = ?, town_id = ?, region_id = ?, state_id = ?, phone = ?, email = ?, '
            . 'website = ?, number_of_sites = ?, public_page_enabled = ?, updated_at = NOW() WHERE id = ?',
            [
                $name,
                trim((string) $request->input('address')) ?: null,
                $townId,
                $town['region_id'] ?? null,
                $town['state_id'] ?? null,
                trim((string) $request->input('phone')) ?: null,
                trim((string) $request->input('email')) ?: null,
                trim((string) $request->input('website')) ?: null,
                (int) $request->input('number_of_sites') ?: null,
                $request->input('public_page_enabled') ? 1 : 0,
                $id,
            ]
        );
        AuditLog::record('park.updated', 'caravan_park', (string) $id);
        return $this->redirectWith('/admin/parks/show?id=' . $id, 'success', 'Park updated.');
    }

    public function setStatus(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];
        $target = (string) $request->input('status');
        if (!array_key_exists($target, self::STATUSES)) {
            $this->abort(400, 'Unknown status.');
        }

        Database::query('UPDATE caravan_parks SET status = ?, updated_at = NOW() WHERE id = ?', [$target, $id]);
        AuditLog::record('park.status_' . $target, 'caravan_park', (string) $id, (string) $park['status'], $target);

        if ($park['email']) {
            $messages = [
                'active'    => 'Good news — your caravan park is now approved and active on VanAssist.',
                'rejected'  => 'Thank you for your interest. Unfortunately we are unable to approve your park at this time.',
                'suspended' => 'Your caravan park partnership has been suspended. Please contact us for details.',
            ];
            if (isset($messages[$target])) {
                EmailQueue::queueRaw(
                    (string) $park['email'],
                    (string) $park['name'],
                    'VanAssist caravan park update',
                    '<p>Hi ' . e((string) $park['name']) . ',</p><p>' . e($messages[$target]) . '</p>',
                    $messages[$target]
                );
            }
        }

        return $this->redirectWith('/admin/parks/show?id=' . $id, 'success', 'Status updated to ' . self::STATUSES[$target] . '.');
    }

    public function serviceDayStatus(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $park = $this->findOr404((int) $request->input('id'));
        $id = (int) $park['id'];
        $sdrId = (int) $request->input('sdr_id');
        $status = (string) $request->input('sdr_status');
        $allowed = ['open', 'reviewing', 'arranged', 'declined', 'completed'];
        if (in_array($status, $allowed, true)) {
            Database::query(
                'UPDATE caravan_park_service_day_requests SET status = ?, updated_at = NOW() WHERE id = ? AND park_id = ?',
                [$status, $sdrId, $id]
            );
        }
        return $this->redirectWith('/admin/parks/show?id=' . $id, 'success', 'Service-day request updated.');
    }

    public function downloadDocument(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $doc = Database::selectOne('SELECT * FROM caravan_park_documents WHERE id = ?', [(int) $request->input('document_id')]);
        if ($doc === null) {
            $this->abort(404);
        }
        return FileStorage::serve('park_documents', (string) $doc['stored_name'], (string) ($doc['original_name'] ?? 'document'), (string) $doc['mime_type'], false);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $park = CaravanPark::adminFind($id);
        if ($park === null) {
            $this->abort(404, 'Park not found.');
        }
        return $park;
    }
}
