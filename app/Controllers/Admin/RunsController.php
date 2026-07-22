<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceRun;
use App\Services\AuditLog;
use App\Services\RunWorkflow;

/**
 * Admin management of service runs: create/edit runs, manage their towns,
 * services and status, link matched requests, and review registrations.
 */
final class RunsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = ServiceRun::adminListing($status ?: null, $search, $perPage, ($page - 1) * $perPage);

        return $this->view('admin.runs.index', [
            'title'    => 'Service runs',
            'runs'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => $perPage,
            'status'   => $status,
            'search'   => $search,
            'statuses' => RunWorkflow::LABELS,
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $id = (int) $request->input('id');
        $run = $id > 0 ? $this->findOr404($id) : null;

        return $this->view('admin.runs.form', [
            'title'     => $run ? 'Edit run' : 'New run',
            'run'       => $run,
            'providers' => Database::select("SELECT id, business_name FROM providers WHERE deleted_at IS NULL ORDER BY business_name"),
            'regions'   => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $id = (int) $request->input('id');
        $run = $id > 0 ? $this->findOr404($id) : null;

        $providerId = (int) $request->input('provider_id');
        $title = trim((string) $request->input('title'));
        if ($providerId <= 0 || $title === '') {
            return $this->redirectWith($run ? '/admin/runs/form?id=' . $id : '/admin/runs/form', 'error', 'A provider and title are required.');
        }

        $fields = [
            'provider_id'            => $providerId,
            'title'                  => $title,
            'start_date'             => $request->input('start_date') ?: null,
            'end_date'               => $request->input('end_date') ?: null,
            'booking_deadline'       => $request->input('booking_deadline') ?: null,
            'region_id'              => (int) $request->input('region_id') ?: null,
            'appointments_total'     => (int) $request->input('appointments_total') ?: null,
            'min_bookings'           => (int) $request->input('min_bookings'),
            'travel_fee_description' => trim((string) $request->input('travel_fee_description')) ?: null,
            'mobile_only'            => $request->input('mobile_only') ? 1 : 0,
            'notes'                  => trim((string) $request->input('notes')) ?: null,
            'is_public'              => $request->input('is_public') ? 1 : 0,
            'is_featured'            => $request->input('is_featured') ? 1 : 0,
        ];

        if ($run === null) {
            $fields['slug'] = ServiceRun::uniqueSlug($title);
            $fields['status'] = 'proposed';
            $fields['run_type'] = 'proposed';
            $fields['created_by'] = current_user()['id'] ?? null;
            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $newId = Database::insert(
                "INSERT INTO service_runs ($cols, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())",
                array_values($fields)
            );
            RunWorkflow::recordHistory($newId, null, 'proposed', current_user()['id'] ?? null, 'Run created');
            AuditLog::record('run.create', 'service_run', (string) $newId);
            return $this->redirectWith('/admin/runs/show?id=' . $newId, 'success', 'Run created.');
        }

        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        Database::query("UPDATE service_runs SET $set, updated_at = NOW() WHERE id = ?", $params);
        AuditLog::record('run.update', 'service_run', (string) $id);
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Run updated.');
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];

        $candidates = Database::select(
            'SELECT sr.id, sr.reference, sr.title, m.status AS match_status, t.name AS town_name '
            . 'FROM service_request_matches m '
            . 'JOIN service_requests sr ON sr.id = m.request_id '
            . 'LEFT JOIN towns t ON t.id = sr.town_id '
            . "WHERE m.provider_id = ? AND m.run_id IS NULL AND m.status IN ('interested','offered','accepted') "
            . 'AND sr.deleted_at IS NULL ORDER BY sr.created_at DESC',
            [(int) $run['provider_id']]
        );

        return $this->view('admin.runs.show', [
            'title'      => $run['title'],
            'run'        => $run,
            'towns'      => ServiceRun::towns($id),
            'services'   => ServiceRun::services($id),
            'bookings'   => ServiceRun::bookings($id),
            'requests'   => ServiceRun::linkedRequests($id),
            'candidates' => $candidates,
            'history'    => Database::select(
                'SELECT h.*, u.name AS by_name FROM service_run_status_history h '
                . 'LEFT JOIN users u ON u.id = h.changed_by WHERE h.run_id = ? ORDER BY h.id DESC',
                [$id]
            ),
            'statuses'   => RunWorkflow::LABELS,
            'allTowns'   => Database::select(
                "SELECT t.id, CONCAT(t.name, ' / ', s.abbreviation) AS name, r.name AS region_name FROM towns t "
                . 'JOIN states s ON s.id=t.state_id LEFT JOIN regions r ON r.id = t.region_id WHERE t.is_active = 1 ORDER BY r.name, t.name'
            ),
            'categories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function setStatus(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        $target = (string) $request->input('status');
        if (!array_key_exists($target, RunWorkflow::LABELS)) {
            $this->abort(400, 'Unknown status.');
        }
        RunWorkflow::changeStatus($id, $target, current_user()['id'] ?? null, trim((string) $request->input('note')) ?: null);
        AuditLog::record('run.status_' . $target, 'service_run', (string) $id, (string) $run['status'], $target);
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Status updated to ' . RunWorkflow::label($target) . '.');
    }

    public function addTown(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        $townId = (int) $request->input('town_id');
        if ($townId > 0) {
            Database::query(
                'INSERT IGNORE INTO service_run_towns (run_id, town_id, arrival_date, sort_order) VALUES (?, ?, ?, ?)',
                [$id, $townId, $request->input('arrival_date') ?: null, (int) $request->input('sort_order')]
            );
        }
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Stop added.');
    }

    public function removeTown(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        Database::query('DELETE FROM service_run_towns WHERE run_id = ? AND town_id = ?', [$id, (int) $request->input('town_id')]);
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Stop removed.');
    }

    public function addService(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        $categoryId = (int) $request->input('category_id');
        if ($categoryId > 0) {
            Database::query('INSERT IGNORE INTO service_run_services (run_id, category_id) VALUES (?, ?)', [$id, $categoryId]);
        }
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Service added.');
    }

    public function removeService(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        Database::query('DELETE FROM service_run_services WHERE run_id = ? AND category_id = ?', [$id, (int) $request->input('category_id')]);
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Service removed.');
    }

    public function linkRequest(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        $requestId = (int) $request->input('request_id');
        if ($requestId > 0) {
            Database::query(
                'INSERT IGNORE INTO service_run_requests (run_id, request_id, added_by, created_at) VALUES (?, ?, ?, NOW())',
                [$id, $requestId, current_user()['id'] ?? null]
            );
            Database::query(
                'UPDATE service_request_matches SET run_id = ?, updated_at = NOW() WHERE request_id = ? AND provider_id = ?',
                [$id, $requestId, (int) $run['provider_id']]
            );
        }
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Request linked to run.');
    }

    public function unlinkRequest(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        $requestId = (int) $request->input('request_id');
        Database::query('DELETE FROM service_run_requests WHERE run_id = ? AND request_id = ?', [$id, $requestId]);
        Database::query('UPDATE service_request_matches SET run_id = NULL WHERE run_id = ? AND request_id = ?', [$id, $requestId]);
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Request unlinked.');
    }

    public function setBookingStatus(Request $request): Response
    {
        $this->requirePermission('runs.manage');
        $run = $this->findOr404((int) $request->input('id'));
        $id = (int) $run['id'];
        $bookingId = (int) $request->input('booking_id');
        $status = (string) $request->input('booking_status');
        $allowed = ['joined', 'confirmed', 'cancelled', 'completed', 'no_show'];
        if (in_array($status, $allowed, true)) {
            Database::query(
                'UPDATE service_run_bookings SET status = ?, updated_at = NOW() WHERE id = ? AND run_id = ?',
                [$status, $bookingId, $id]
            );
            RunWorkflow::recalcCapacity($id);
        }
        return $this->redirectWith('/admin/runs/show?id=' . $id, 'success', 'Registration updated.');
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $run = ServiceRun::adminFind($id);
        if ($run === null) {
            $this->abort(404, 'Run not found.');
        }
        return $run;
    }
}
