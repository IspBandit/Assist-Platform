<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $templates */
/** @var int $pendingCount */
/** @var int $failedCount */
/** @var int $sentCount */
/** @var bool $mailConfigured */
/** @var string $mailHost */
/** @var array<int,array<string,mixed>> $recentFailures */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>

<div class="card">
    <h1 style="margin:0 0 .75rem">Email delivery</h1>
    <details style="margin:0 0 1rem">
        <summary style="cursor:pointer;font-weight:600">Where do emails live? (VanAssist vs GoDaddy cPanel)</summary>
        <ul class="muted" style="margin:.5rem 0 0;padding-left:1.2rem;font-size:.9rem;line-height:1.55">
            <li><strong>VanAssist</strong> only <em>sends</em> mail via your GoDaddy mailbox. Templates, the queue and send logs live here — not in cPanel.</li>
            <li><strong>GoDaddy webmail</strong> shows mail <em>received</em> by <code>vanassist@condrendigital.com.au</code>. Outbound mail from the website often does <strong>not</strong> appear in Sent — that is normal.</li>
            <li><strong>To read mail in cPanel:</strong> Email Accounts → <em>Check Email</em>. Log in with the <strong>full address</strong> and the <strong>email account password</strong> (not your cPanel or VanAssist admin password).</li>
            <li>If SMTP auth fails, reset the mailbox password in cPanel, then enter the same password in <a href="<?= e(url('admin/settings')) ?>">Admin → Settings → Outgoing email</a>.</li>
        </ul>
    </details>
    <?php if (!$mailConfigured): ?>
        <p style="margin:0;padding:.75rem;border-left:4px solid #c0392b;background:#fdf3f2">
            <strong>SMTP is not configured.</strong> No emails can be delivered until you set the mail host, username and password in
            <a href="<?= e(url('admin/settings')) ?>">Admin → Settings</a> (Outgoing email).
        </p>
    <?php else: ?>
        <p class="muted" style="margin:0 0 .5rem">Outgoing server: <code><?= $this->e($mailHost) ?></code></p>
        <p style="margin:0 0 1rem">
            <span class="badge badge-neutral">Waiting: <?= (int) $pendingCount ?></span>
            <span class="badge <?= $failedCount > 0 ? 'badge-neutral' : 'badge-neutral' ?>" style="<?= $failedCount > 0 ? 'background:#fdecea;color:#c0392b' : '' ?>">Failed: <?= (int) $failedCount ?></span>
            <span class="badge badge-confirmed">Sent: <?= (int) $sentCount ?></span>
        </p>
        <div class="btn-row" style="margin:0;gap:.6rem">
            <form method="post" action="<?= e(url('admin/email-templates/process-queue')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Send queued emails now</button>
            </form>
            <a class="btn btn-ghost" href="<?= e(url('admin/logs?channel=email')) ?>">View email log</a>
        </div>
        <p class="muted" style="margin:.5rem 0 0;font-size:.85rem">Sends any waiting and previously failed emails immediately (retrying failures). Normally a scheduled cron job does this automatically. The <a href="<?= e(url('admin/logs?channel=email')) ?>">email log</a> shows the full step-by-step SMTP conversation for each attempt.</p>

        <form method="post" action="<?= e(url('admin/email-templates/smtp-test')) ?>" class="btn-row" style="align-items:end;margin-top:1rem;gap:.6rem;flex-wrap:wrap">
            <?= csrf_field() ?>
            <div class="form-group" style="margin:0;min-width:220px">
                <label for="smtp_test_email">SMTP test — send to</label>
                <input type="email" id="smtp_test_email" name="test_email" value="<?= e_attr((string) (current_user()['email'] ?? '')) ?>" placeholder="your@email.com" required>
            </div>
            <button type="submit" class="btn btn-secondary">Send SMTP test now</button>
        </form>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">Sends one plain test message immediately and reports success or the exact SMTP error.</p>

        <?php if ($recentFailures !== []): ?>
            <h2 style="margin:1.25rem 0 .5rem;font-size:1.05rem">Recent failures</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>To</th><th>Subject</th><th>Error</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentFailures as $f): ?>
                        <tr>
                            <td><?= $this->e((string) $f['recipient_email']) ?></td>
                            <td><?= $this->e((string) $f['subject']) ?></td>
                            <td style="color:#c0392b;font-size:.85rem"><?= $this->e((string) ($f['last_error'] ?? '')) ?></td>
                            <td class="muted" style="white-space:nowrap"><?= $this->e((string) ($f['last_attempt_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h1 style="margin:0 0 1rem">Email templates</h1>
    <p class="muted">Transactional emails sent automatically by the platform. Use <code>{{placeholder}}</code> tokens; they are filled in when each email is sent.</p>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Key</th><th>Subject</th><th>Enabled</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($templates as $t): ?>
                <tr>
                    <td><strong><?= $this->e((string) $t['name']) ?></strong></td>
                    <td><code><?= $this->e((string) $t['template_key']) ?></code></td>
                    <td><?= $this->e((string) $t['subject']) ?></td>
                    <td><?= $t['is_enabled'] ? 'Yes' : '<span class="muted">No</span>' ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/email-templates/edit?id=' . (int) $t['id'])) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($templates === []): ?><tr><td colspan="5" class="muted">No templates.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
