<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\BroadcastAudience;
use App\Services\NotificationService;

/**
 * Admin broadcasts: compose a targeted email to an audience (town, region,
 * category, providers, open-request customers, or everyone opted in), preview
 * the recipient count, then send now, schedule for later, or save as a draft.
 */
final class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        return $this->view('admin.notifications.index', [
            'title'         => 'Notifications',
            'notifications' => Database::select(
                'SELECT n.*, u.name AS author FROM notifications n LEFT JOIN users u ON u.id = n.created_by ORDER BY n.id DESC LIMIT 100'
            ),
            'queue'         => $this->queueStats(),
        ]);
    }

    public function compose(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        return $this->renderForm([], null);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('notifications.send');

        $action = (string) $request->input('action', 'preview');
        $values = [
            'title'         => trim((string) $request->input('title')),
            'body'          => (string) $request->input('body'),
            'audience_type' => (string) $request->input('audience_type'),
            'town_id'       => (int) $request->input('town_id') ?: null,
            'region_id'     => (int) $request->input('region_id') ?: null,
            'category_id'   => (int) $request->input('category_id') ?: null,
            'scheduled_at'  => trim((string) $request->input('scheduled_at')),
        ];

        $error = $this->validate($values, $action);
        if ($error !== null) {
            return $this->renderForm($values, null, $error);
        }

        $count = BroadcastAudience::count($values['audience_type'], $values['town_id'], $values['region_id'], $values['category_id']);

        if ($action === 'preview') {
            return $this->renderForm($values, $count);
        }

        if ($count === 0 && $action !== 'draft') {
            return $this->renderForm($values, 0, 'That audience currently has no recipients. Save as a draft or adjust the audience.');
        }

        $status = $action === 'schedule' ? 'scheduled' : 'draft';
        $scheduledAt = $action === 'schedule' ? date('Y-m-d H:i:s', strtotime($values['scheduled_at'])) : null;

        $id = Database::insert(
            'INSERT INTO notifications (title, body, channel, audience_type, town_id, region_id, category_id, status, scheduled_at, created_by, created_at, updated_at) '
            . "VALUES (?, ?, 'email', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $values['title'], $values['body'], $values['audience_type'],
                $values['town_id'], $values['region_id'], $values['category_id'],
                $status, $scheduledAt, current_user()['id'] ?? null,
            ]
        );
        AuditLog::record('notification.create', 'notification', (string) $id, null, $action);

        if ($action === 'send') {
            $result = NotificationService::dispatch($id);
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Broadcast queued to ' . $result['recipients'] . ' recipient(s).');
        }
        if ($action === 'schedule') {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Broadcast scheduled for ' . $scheduledAt . '.');
        }
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Draft saved.');
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $notification = Database::selectOne('SELECT * FROM notifications WHERE id = ?', [(int) $request->input('id')]);
        if ($notification === null) {
            $this->abort(404, 'Notification not found.');
        }
        $previewCount = in_array($notification['status'], ['draft', 'scheduled'], true)
            ? BroadcastAudience::count(
                (string) $notification['audience_type'],
                $notification['town_id'] !== null ? (int) $notification['town_id'] : null,
                $notification['region_id'] !== null ? (int) $notification['region_id'] : null,
                $notification['category_id'] !== null ? (int) $notification['category_id'] : null,
            )
            : (int) $notification['recipient_count'];

        return $this->view('admin.notifications.show', [
            'title'        => 'Broadcast: ' . $notification['title'],
            'notification' => $notification,
            'recipients'   => Database::select('SELECT email, status FROM notification_recipients WHERE notification_id = ? ORDER BY id LIMIT 200', [(int) $notification['id']]),
            'previewCount' => $previewCount,
        ]);
    }

    public function send(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $notification = Database::selectOne('SELECT status FROM notifications WHERE id = ?', [$id]);
        if ($notification === null) {
            $this->abort(404);
        }
        if (!in_array($notification['status'], ['draft', 'scheduled'], true)) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', 'Only draft or scheduled broadcasts can be sent.');
        }
        $result = NotificationService::dispatch($id);
        AuditLog::record('notification.send', 'notification', (string) $id, null, (string) $result['recipients']);
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Broadcast queued to ' . $result['recipients'] . ' recipient(s).');
    }

    public function cancel(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $notification = Database::selectOne('SELECT status FROM notifications WHERE id = ?', [$id]);
        if ($notification === null) {
            $this->abort(404);
        }
        if (in_array($notification['status'], ['sent', 'sending'], true)) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', 'A broadcast that is sending or sent cannot be cancelled.');
        }
        Database::query("UPDATE notifications SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [$id]);
        AuditLog::record('notification.cancel', 'notification', (string) $id);
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Broadcast cancelled.');
    }

    /** @param array<string,mixed> $values */
    private function validate(array $values, string $action): ?string
    {
        if ($values['title'] === '' || trim($values['body']) === '') {
            return 'A title and message body are required.';
        }
        if (!isset(BroadcastAudience::TYPES[$values['audience_type']])) {
            return 'Choose a valid audience.';
        }
        if ($values['audience_type'] === 'town' && $values['town_id'] === null) {
            return 'Select a town for this audience.';
        }
        if ($values['audience_type'] === 'region' && $values['region_id'] === null) {
            return 'Select a region for this audience.';
        }
        if ($values['audience_type'] === 'category' && $values['category_id'] === null) {
            return 'Select a service category for this audience.';
        }
        if ($action === 'schedule') {
            $ts = strtotime((string) $values['scheduled_at']);
            if ($ts === false || $ts < time() + 60) {
                return 'Choose a schedule time at least a minute in the future.';
            }
        }
        return null;
    }

    /** @param array<string,mixed> $values */
    private function renderForm(array $values, ?int $previewCount, ?string $error = null): Response
    {
        return $this->view('admin.notifications.compose', [
            'title'        => 'Compose broadcast',
            'values'       => $values,
            'previewCount' => $previewCount,
            'formError'    => $error,
            'audiences'    => BroadcastAudience::TYPES,
            'towns'        => Database::select("SELECT t.id, CONCAT(t.name, ' / ', s.abbreviation) AS name FROM towns t JOIN states s ON s.id=t.state_id WHERE t.is_active=1 ORDER BY t.name,s.abbreviation"),
            'regions'      => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'categories'   => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    /** @return array<string,int> */
    private function queueStats(): array
    {
        $stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        $rows = Database::select("SELECT status, COUNT(*) AS c FROM email_queue GROUP BY status");
        foreach ($rows as $row) {
            $stats[(string) $row['status']] = (int) $row['c'];
        }
        return $stats;
    }
}
