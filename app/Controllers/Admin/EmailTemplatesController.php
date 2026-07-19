<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\Mailer;

/**
 * Admin editor for transactional email templates. Subjects/bodies use
 * {{placeholder}} tokens replaced at send time. Includes a server-side preview
 * with sample data and a "send test" action that queues a real email.
 */
final class EmailTemplatesController extends Controller
{
    /** Sample values used for preview and test sends. */
    private const SAMPLE = [
        'customer_name'     => 'Sam Traveller',
        'provider_name'     => 'Outback Caravan Repairs',
        'business_name'     => 'Outback Caravan Repairs',
        'greeting'          => 'Chris',
        'town_line'         => '<p style="margin:4px 0 0;font-size:.95rem;color:#5c6369">Based in Emerald, QLD</p>',
        'services_line'     => '<p style="margin:6px 0 0;font-size:.9rem;color:#5c6369">Services on file: Brakes &amp; bearings, Solar &amp; 12V</p>',
        'listing_url'       => 'https://example.com/providers/outback-caravan-repairs',
        'site_url'          => 'https://example.com/',
        'expiry_days'       => '14',
        'action_url'        => 'https://example.com/provider/claim/abc123',
        'request_reference' => 'VA-2026-0042',
        'town_name'         => 'Emerald',
        'run_title'         => 'Central QLD service run',
        'founding_offer_line' => '<p style="background:#eef9f7;border-left:4px solid #0f6e6e;padding:12px 16px">Launch offer: free ad graphic.</p>',
        'founding_offer_text' => "\n\nLaunch offer: free ad graphic.",
        'image_url'         => 'https://example.com/uploads/ad-desktop.webp',
        'image_url_mobile'  => 'https://example.com/uploads/ad-mobile.webp',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('email.manage');

        $cfg = Mailer::config();
        $recentFailures = Database::select(
            "SELECT recipient_email, subject, last_error, last_attempt_at FROM email_queue "
            . "WHERE status = 'failed' ORDER BY last_attempt_at DESC LIMIT 5"
        );

        return $this->view('admin.email-templates.index', [
            'title'        => 'Email templates',
            'templates'    => Database::select('SELECT id, template_key, name, subject, is_enabled, updated_at FROM email_templates ORDER BY name'),
            'pendingCount' => (int) Database::scalar("SELECT COUNT(*) FROM email_queue WHERE status IN ('pending','processing')"),
            'failedCount'  => (int) Database::scalar("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'"),
            'sentCount'    => (int) Database::scalar("SELECT COUNT(*) FROM email_queue WHERE status = 'sent'"),
            'mailConfigured' => trim((string) $cfg['host']) !== '',
            'mailHost'     => (string) $cfg['host'],
            'recentFailures' => $recentFailures,
        ]);
    }

    /**
     * Send the queued emails immediately (without waiting for cron) and report
     * the outcome. Useful for delivering test emails and for diagnosing SMTP
     * problems, since failures surface the SMTP error directly.
     */
    public function processQueueNow(Request $request): Response
    {
        $this->requirePermission('email.manage');

        $cfg = Mailer::config();
        if (trim((string) $cfg['host']) === '') {
            return $this->redirectWith('/admin/email-templates', 'error', 'SMTP is not configured yet. Add your mail host, username and password in Admin → Settings first.');
        }

        // Re-queue recently failed messages (resetting attempts) so a manual run retries them.
        Database::query("UPDATE email_queue SET status = 'pending', attempts = 0 WHERE status = 'failed'");
        $res = Mailer::processQueue(100);

        if ($res['sent'] === 0 && $res['failed'] === 0) {
            return $this->redirectWith('/admin/email-templates', 'success', 'No emails were waiting to send.');
        }

        $type = $res['failed'] > 0 ? ($res['sent'] > 0 ? 'success' : 'error') : 'success';
        $msg = "Email queue processed — sent: {$res['sent']}, failed: {$res['failed']}.";
        if ($res['failed'] > 0) {
            $err = (string) Database::scalar(
                "SELECT last_error FROM email_queue WHERE status = 'failed' AND last_error IS NOT NULL ORDER BY last_attempt_at DESC LIMIT 1"
            );
            if ($err !== '') {
                $msg .= ' Last error: ' . $err;
            }
        }
        return $this->redirectWith('/admin/email-templates', $type, $msg);
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('email.manage');
        $template = Database::selectOne('SELECT * FROM email_templates WHERE id = ?', [(int) $request->input('id')]);
        if ($template === null) {
            $this->abort(404, 'Template not found.');
        }
        return $this->view('admin.email-templates.edit', [
            'title'        => 'Edit: ' . $template['name'],
            'emailTemplate'  => $template,
            'placeholders' => $this->extractPlaceholders($template),
            'previewHtml'  => $this->render((string) $template['html_body']),
            'previewSubject' => $this->render((string) $template['subject']),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('email.manage');
        $id = (int) $request->input('id');
        $template = Database::selectOne('SELECT id FROM email_templates WHERE id = ?', [$id]);
        if ($template === null) {
            $this->abort(404);
        }

        $subject = trim((string) $request->input('subject'));
        $html = (string) $request->input('html_body');
        if ($subject === '' || trim($html) === '') {
            return $this->redirectWith('/admin/email-templates/edit?id=' . $id, 'error', 'Subject and HTML body are required.');
        }

        Database::query(
            'UPDATE email_templates SET name = ?, subject = ?, html_body = ?, text_body = ?, is_enabled = ?, updated_at = NOW() WHERE id = ?',
            [
                trim((string) $request->input('name')),
                $subject,
                $html,
                trim((string) $request->input('text_body')) ?: null,
                $request->input('is_enabled') ? 1 : 0,
                $id,
            ]
        );
        AuditLog::record('email.template_update', 'email_template', (string) $id);
        return $this->redirectWith('/admin/email-templates/edit?id=' . $id, 'success', 'Template saved.');
    }

    public function sendTest(Request $request): Response
    {
        $this->requirePermission('email.manage');
        $template = Database::selectOne('SELECT * FROM email_templates WHERE id = ?', [(int) $request->input('id')]);
        if ($template === null) {
            $this->abort(404);
        }

        $to = trim((string) $request->input('test_email')) ?: (string) (current_user()['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWith('/admin/email-templates/edit?id=' . (int) $template['id'], 'error', 'Enter a valid test email address.');
        }

        EmailQueue::queueRaw(
            $to,
            (string) (current_user()['name'] ?? 'Test'),
            '[TEST] ' . $this->render((string) $template['subject']),
            $this->render((string) $template['html_body']),
            $this->render((string) ($template['text_body'] ?? '')),
            (string) $template['template_key']
        );
        $res = Mailer::processQueue(5);
        AuditLog::record('email.template_test', 'email_template', (string) $template['id'], null, $to);

        if ($res['sent'] > 0) {
            return $this->redirectWith('/admin/email-templates/edit?id=' . (int) $template['id'], 'success', 'Test email sent to ' . $to . '. Check your inbox (and spam).');
        }

        $err = (string) Database::scalar(
            "SELECT last_error FROM email_queue WHERE recipient_email = ? ORDER BY id DESC LIMIT 1",
            [$to]
        );
        $msg = 'Test email failed to send to ' . $to . '.';
        if ($err !== '') {
            $msg .= ' Error: ' . $err;
        }
        $msg .= ' See Admin → System logs → email for the full SMTP transcript.';
        return $this->redirectWith('/admin/email-templates/edit?id=' . (int) $template['id'], 'error', $msg);
    }

    /**
     * Send a minimal test message immediately — the fastest way to verify SMTP
     * credentials and diagnose delivery without editing a template.
     */
    public function sendSmtpTest(Request $request): Response
    {
        $this->requirePermission('email.manage');

        $cfg = Mailer::config();
        if (trim((string) $cfg['host']) === '') {
            return $this->redirectWith('/admin/email-templates', 'error', 'SMTP is not configured. Set host, username and password in Admin → Settings (Outgoing email).');
        }

        $to = trim((string) $request->input('test_email')) ?: (string) (current_user()['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWith('/admin/email-templates', 'error', 'Enter a valid email address for the SMTP test.');
        }

        $html = '<p>This is a test message from VanAssist at ' . e(date('Y-m-d H:i:s T')) . '.</p>'
            . '<p>If you received this, outgoing SMTP from the website is working.</p>';
        EmailQueue::queueRaw($to, 'SMTP test', 'VanAssist SMTP test', $html, 'VanAssist SMTP test at ' . date('c'));
        $res = Mailer::processQueue(5);

        if ($res['sent'] > 0) {
            return $this->redirectWith('/admin/email-templates', 'success', 'SMTP test sent to ' . $to . '. Check that inbox (and spam). Sent mail may not appear in cPanel webmail — that is normal.');
        }

        $err = (string) Database::scalar(
            "SELECT last_error FROM email_queue WHERE recipient_email = ? ORDER BY id DESC LIMIT 1",
            [$to]
        );
        $msg = 'SMTP test failed for ' . $to . '.';
        if ($err !== '') {
            $msg .= ' Error: ' . $err;
        }
        $msg .= ' Open System logs → email for the step-by-step SMTP conversation.';
        return $this->redirectWith('/admin/email-templates', 'error', $msg);
    }

    private function render(string $body): string
    {
        foreach (self::SAMPLE as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        return $body;
    }

    /** @param array<string,mixed> $template @return array<int,string> */
    private function extractPlaceholders(array $template): array
    {
        $blob = (string) $template['subject'] . ' ' . (string) $template['html_body'] . ' ' . (string) ($template['text_body'] ?? '');
        preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $blob, $m);
        return array_values(array_unique($m[1]));
    }
}
