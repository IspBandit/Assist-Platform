<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ServiceRun;
use App\Services\AuditLog;
use App\Services\RunWorkflow;

/**
 * Provider self-service for service runs: create and edit their own runs,
 * manage stops and services, change status and review registrations.
 */
final class RunController extends Controller
{
    /** Statuses a provider may set directly; fully_booked is automatic. */
    private const PROVIDER_STATUSES = ['proposed', 'forming', 'confirmed', 'limited', 'completed', 'cancelled'];

    public function index(Request $request): Response
    {
        $provider = $this->requireProvider();
        return $this->view('provider.runs', [
            'title' => 'My service runs',
            'runs'  => ServiceRun::forProvider((int) $provider['id']),
        ]);
    }

    public function form(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $request->input('id');
        $run = $id > 0 ? $this->findOwnRun($id, (int) $provider['id']) : null;

        return $this->view('provider.run-form', [
            'title'   => $run ? 'Edit run' : 'New run',
            'run'     => $run,
            'regions' => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function save(Request $request): Response
    {
        $provider = $this->requireProvider();
        $providerId = (int) $provider['id'];
        $id = (int) $request->input('id');
        $run = $id > 0 ? $this->findOwnRun($id, $providerId) : null;

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWith($run ? '/provider/runs/form?id=' . $id : '/provider/runs/form', 'error', 'A title is required.');
        }

        $fields = [
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
        ];

        if ($run === null) {
            $fields['provider_id'] = $providerId;
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
            RunWorkflow::recordHistory($newId, null, 'proposed', current_user()['id'] ?? null, 'Run created by provider');
            AuditLog::record('run.create', 'service_run', (string) $newId);
            return $this->redirectWith('/provider/runs/show?id=' . $newId, 'success', 'Run created.');
        }

        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        $params = array_values($fields);
        $params[] = $id;
        Database::query("UPDATE service_runs SET $set, updated_at = NOW() WHERE id = ?", $params);
        AuditLog::record('run.update', 'service_run', (string) $id);
        return $this->redirectWith('/provider/runs/show?id=' . $id, 'success', 'Run updated.');
    }

    public function show(Request $request): Response
    {
        $provider = $this->requireProvider();
        $run = $this->findOwnRun((int) $request->input('id'), (int) $provider['id']);
        $id = (int) $run['id'];

        return $this->view('provider.run-detail', [
            'title'      => $run['title'],
            'run'        => $run,
            'towns'      => ServiceRun::towns($id),
            'services'   => ServiceRun::services($id),
            'bookings'   => ServiceRun::bookings($id),
            'statuses'   => array_intersect_key(RunWorkflow::LABELS, array_flip(self::PROVIDER_STATUSES)),
            'allTowns'   => Database::select(
                "SELECT t.id, CONCAT(t.name, ' / ', s.abbreviation) AS name, r.name AS region_name FROM towns t "
                . 'JOIN states s ON s.id=t.state_id LEFT JOIN regions r ON r.id = t.region_id WHERE t.is_active = 1 ORDER BY r.name, t.name'
            ),
            'categories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function setStatus(Request $request): Response
    {
        $provider = $this->requireProvider();
        $run = $this->findOwnRun((int) $request->input('id'), (int) $provider['id']);
        $id = (int) $run['id'];
        $target = (string) $request->input('status');
        if (!in_array($target, self::PROVIDER_STATUSES, true)) {
            $this->abort(400, 'You cannot set that status.');
        }
        RunWorkflow::changeStatus($id, $target, current_user()['id'] ?? null, trim((string) $request->input('note')) ?: null);
        return $this->redirectWith('/provider/runs/show?id=' . $id, 'success', 'Status updated to ' . RunWorkflow::label($target) . '.');
    }

    public function addTown(Request $request): Response
    {
        $provider = $this->requireProvider();
        $run = $this->findOwnRun((int) $request->input('id'), (int) $provider['id']);
        $id = (int) $run['id'];
        $townId = (int) $request->input('town_id');
        if ($townId > 0) {
            Database::query(
                'INSERT IGNORE INTO service_run_towns (run_id, town_id, arrival_date, sort_order) VALUES (?, ?, ?, ?)',
                [$id, $townId, $request->input('arrival_date') ?: null, (int) $request->input('sort_order')]
            );
        }
        return $this->redirectWith('/provider/runs/show?id=' . $id, 'success', 'Stop added.');
    }

    public function removeTown(Request $request): Response
    {
        $provider = $this->requireProvider();
        $run = $this->findOwnRun((int) $request->input('id'), (int) $provider['id']);
        $id = (int) $run['id'];
        Database::query('DELETE FROM service_run_towns WHERE run_id = ? AND town_id = ?', [$id, (int) $request->input('town_id')]);
        return $this->redirectWith('/provider/runs/show?id=' . $id, 'success', 'Stop removed.');
    }

    public function addService(Request $request): Response
    {
        $provider = $this->requireProvider();
        $run = $this->findOwnRun((int) $request->input('id'), (int) $provider['id']);
        $id = (int) $run['id'];
        $categoryId = (int) $request->input('category_id');
        if ($categoryId > 0) {
            Database::query('INSERT IGNORE INTO service_run_services (run_id, category_id) VALUES (?, ?)', [$id, $categoryId]);
        }
        return $this->redirectWith('/provider/runs/show?id=' . $id, 'success', 'Service added.');
    }

    public function removeService(Request $request): Response
    {
        $provider = $this->requireProvider();
        $run = $this->findOwnRun((int) $request->input('id'), (int) $provider['id']);
        $id = (int) $run['id'];
        Database::query('DELETE FROM service_run_services WHERE run_id = ? AND category_id = ?', [$id, (int) $request->input('category_id')]);
        return $this->redirectWith('/provider/runs/show?id=' . $id, 'success', 'Service removed.');
    }

    /** @return array<string,mixed> */
    private function findOwnRun(int $id, int $providerId): array
    {
        $run = Database::selectOne('SELECT * FROM service_runs WHERE id = ? AND provider_id = ? AND deleted_at IS NULL', [$id, $providerId]);
        if ($run === null) {
            $this->abort(404, 'Run not found.');
        }
        return $run;
    }

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
}
