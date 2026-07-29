<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\BroadcastAudience;
use App\Services\CampaignRecipientManager;
use App\Services\NotificationService;
use App\Services\ProviderCampaignCopy;
use App\Services\ProviderCampaignDrafts;
use RuntimeException;

/**
 * Admin broadcasts: compose a targeted email to an audience (town, region,
 * category, providers, open-request customers, or everyone opted in), preview
 * the recipient count, then use the gated test, pilot and daily batch stages.
 */
final class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        ProviderCampaignDrafts::prepareForBrand(current_brand()->databaseId());
        return $this->view('admin.notifications.index', [
            'title'         => 'Notifications',
            'notifications' => Database::select(
                'SELECT n.*,u.name AS author FROM notifications n LEFT JOIN users u ON u.id=n.created_by WHERE n.brand_id=? ORDER BY n.id DESC LIMIT 100',
                [current_brand()->databaseId()]
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
            'copy_style'    => (string) $request->input('copy_style'),
        ];

        if ($action === 'starter') {
            $style = ProviderCampaignCopy::styles()[$values['copy_style']] ?? null;
            if ($style === null) {
                return $this->renderForm($values, null, 'Choose a valid provider-email starter.');
            }
            $values['title'] = $style['subject'];
            $values['body'] = $style['body'];
            return $this->renderForm($values, null);
        }

        $error = $this->validate($values, $action);
        if ($error !== null) {
            return $this->renderForm($values, null, $error);
        }

        $count = BroadcastAudience::count($values['audience_type'], $values['town_id'], $values['region_id'], $values['category_id']);

        if ($action === 'preview') {
            return $this->renderForm($values, $count);
        }

        $id = Database::insert(
            'INSERT INTO notifications (brand_id,title,body,channel,audience_type,town_id,region_id,category_id,status,scheduled_at,created_by,created_at,updated_at) '
            . "VALUES (?, ?, ?, 'email', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                current_brand()->databaseId(), $values['title'], $values['body'], $values['audience_type'],
                $values['town_id'], $values['region_id'], $values['category_id'],
                'draft', null, current_user()['id'] ?? null,
            ]
        );
        AuditLog::record('notification.create', 'notification', (string) $id, null, $action);
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Draft saved. Send an internal test before the 25-provider pilot can be queued.');
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $notification = Database::selectOne('SELECT * FROM notifications WHERE id=? AND brand_id=?', [(int) $request->input('id'), current_brand()->databaseId()]);
        if ($notification === null) {
            $this->abort(404, 'Notification not found.');
        }
        $isProviderCampaign = in_array((string) $notification['audience_type'], ['providers', 'provider_category'], true);
        $resolved = in_array($notification['status'], ['draft', 'scheduled', 'sending'], true)
            ? BroadcastAudience::resolve(
                (string) $notification['audience_type'],
                $notification['town_id'] !== null ? (int) $notification['town_id'] : null,
                $notification['region_id'] !== null ? (int) $notification['region_id'] : null,
                $notification['category_id'] !== null ? (int) $notification['category_id'] : null,
            ) : [];
        if ($isProviderCampaign && $resolved !== []) {
            $resolved = CampaignRecipientManager::filter((int) $notification['id'], $resolved);
        }
        $previewCount = in_array($notification['status'], ['draft', 'scheduled', 'sending'], true)
            ? count($resolved)
            : (int) $notification['recipient_count'];
        $recipientSearch = trim((string) $request->input('recipient_search'));

        return $this->view('admin.notifications.show', [
            'title'        => 'Broadcast: ' . $notification['title'],
            'notification' => $notification,
            'recipients'   => Database::select('SELECT email, status FROM notification_recipients WHERE notification_id = ? ORDER BY id LIMIT 200', [(int) $notification['id']]),
            'tests'        => Database::select('SELECT recipient_email,created_at FROM notification_test_deliveries WHERE notification_id=? ORDER BY id DESC LIMIT 10', [(int) $notification['id']]),
            'previewCount' => $previewCount,
            'providerSummary' => $isProviderCampaign ? CampaignRecipientManager::summary($notification) : null,
            'providerCandidates' => $isProviderCampaign ? CampaignRecipientManager::candidates($notification, $recipientSearch) : [],
            'recipientSearch' => $recipientSearch,
            'consentBases' => CampaignRecipientManager::CONSENT_BASES,
        ]);
    }

    public function recipientExclude(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $providerId = (int) $request->input('provider_id');
        $notification = $this->findBrandNotification($id);
        try {
            CampaignRecipientManager::exclude($notification, $providerId, (string) $request->input('reason'), $this->currentUserId());
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', $error->getMessage());
        }
        AuditLog::record('notification.recipient.exclude', 'provider', (string) $providerId, null, 'notification:' . $id);
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Provider removed from this campaign.');
    }

    public function recipientRestore(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $providerId = (int) $request->input('provider_id');
        $notification = $this->findBrandNotification($id);
        try {
            CampaignRecipientManager::restore($notification, $providerId);
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', $error->getMessage());
        }
        AuditLog::record('notification.recipient.restore', 'provider', (string) $providerId, null, 'notification:' . $id);
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Provider restored to this campaign.');
    }

    public function recipientInclude(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $providerId = (int) $request->input('provider_id');
        $notification = $this->findBrandNotification($id);
        try {
            CampaignRecipientManager::recordConsentAndInclude(
                $notification,
                $providerId,
                (string) $request->input('consent_basis'),
                (string) $request->input('consent_evidence'),
                (string) $request->input('consented_at')
            );
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', $error->getMessage());
        }
        AuditLog::record('notification.recipient.consent', 'provider', (string) $providerId, null, (string) $request->input('consent_basis'));
        return $this->redirectWith('/admin/notifications/show?id=' . $id, 'success', 'Consent evidence recorded and provider added to the eligible campaign audience.');
    }

    public function test(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $this->findBrandNotification($id);
        try {
            $queued = NotificationService::queueTest($id, (string) $request->input('test_email'), isset(current_user()['id']) ? (int) current_user()['id'] : null);
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', $error->getMessage());
        }
        AuditLog::record('notification.test', 'notification', (string) $id, null, (string) $request->input('test_email'));
        return $this->redirectWith('/admin/notifications/show?id=' . $id, $queued ? 'success' : 'error', $queued ? 'Internal test queued. Check the mailbox before starting the pilot.' : 'The test could not be queued.');
    }

    public function stage(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $this->findBrandNotification($id);
        $stage = (string) $request->input('stage');
        try {
            $result = NotificationService::queueStage($id, $stage, isset(current_user()['id']) ? (int) current_user()['id'] : null);
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', $error->getMessage());
        }
        AuditLog::record('notification.stage', 'notification', (string) $id, null, $stage . ':' . $result['recipients']);
        $message = $result['limited']
            ? 'The rolling daily limit has been reached. No extra emails were queued; try again after 24 hours.'
            : $result['recipients'] . ' email(s) queued. ' . $result['remaining'] . ' eligible recipient(s) remain.';
        return $this->redirectWith('/admin/notifications/show?id=' . $id, $result['limited'] ? 'error' : 'success', $message);
    }

    public function cancel(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        $notification = Database::selectOne('SELECT status FROM notifications WHERE id=? AND brand_id=?', [$id, current_brand()->databaseId()]);
        if ($notification === null) {
            $this->abort(404);
        }
        if (in_array($notification['status'], ['sent', 'sending'], true)) {
            if ($notification['status'] === 'sent') {
                return $this->redirectWith('/admin/notifications/show?id=' . $id, 'error', 'A completed broadcast cannot be cancelled.');
            }
        }
        Database::query("UPDATE email_queue SET status='cancelled' WHERE notification_id=? AND status='pending'", [$id]);
        Database::query("UPDATE notification_recipients SET status='failed' WHERE notification_id=? AND status='queued'", [$id]);
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
        if (in_array($values['audience_type'], ['category', 'provider_category'], true) && $values['category_id'] === null) {
            return 'Select a service category for this audience.';
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function findBrandNotification(int $id): array
    {
        $notification = Database::selectOne('SELECT * FROM notifications WHERE id=? AND brand_id=?', [$id, current_brand()->databaseId()]);
        if ($notification === null) {
            $this->abort(404, 'Notification not found.');
        }
        return $notification;
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
            'campaignStyles' => ProviderCampaignCopy::styles(),
            'providerSummary' => $previewCount !== null && in_array((string) ($values['audience_type'] ?? ''), ['providers', 'provider_category'], true)
                ? BroadcastAudience::providerEmailSummary(isset($values['category_id']) && $values['category_id'] !== null ? (int) $values['category_id'] : null)
                : null,
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

    private function currentUserId(): ?int
    {
        return isset(current_user()['id']) ? (int) current_user()['id'] : null;
    }
}
