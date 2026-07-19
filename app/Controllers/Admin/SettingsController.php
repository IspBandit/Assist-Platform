<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\DemoSeeder;
use App\Services\SecretCipher;
use App\Services\Settings;
use Throwable;

/**
 * Site settings + launch tools: general/business/contact details, launch mode
 * and maintenance, first-party analytics, demo-data removal and a production
 * readiness checklist.
 */
final class SettingsController extends Controller
{
    private const LAUNCH_MODES = [
        'private'              => 'Private (hidden, team only)',
        'provider-onboarding'  => 'Provider onboarding',
        'local-pilot'          => 'Local pilot',
        'public'               => 'Public launch',
    ];

    /** Keys saved by the main settings form. */
    private const TEXT_KEYS = [
        'site_name', 'tagline', 'contact_email', 'contact_phone',
        'business_legal_name', 'business_structure', 'business_abn', 'business_address',
        'facebook_url', 'maintenance_message', 'free_launch_message',
        // Outgoing email (SMTP). Password is handled separately (write-only).
        'mail_host', 'mail_port', 'mail_encryption', 'mail_username',
        'mail_from_address', 'mail_from_name',
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('settings.manage');

        return $this->view('admin.settings.index', [
            'title'       => 'Settings',
            'settings'    => Settings::all(),
            'launchModes' => self::LAUNCH_MODES,
            'launchMode'  => Settings::launchMode(),
            'maintenance' => Settings::isMaintenanceMode(),
            'analyticsOn' => (string) Settings::get('analytics_enabled', '0') === '1',
            'demoCounts'  => $this->demoCounts(),
            'checklist'   => $this->checklist(),
            'isSuperAdmin' => \App\Auth\Auth::instance()->isSuperAdmin(),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('settings.manage');

        foreach (self::TEXT_KEYS as $key) {
            Settings::set($key, trim((string) $request->input($key, '')));
        }

        $launch = (string) $request->input('launch_mode', 'private');
        if (isset(self::LAUNCH_MODES[$launch])) {
            Settings::set('launch_mode', $launch);
        }
        Settings::set('maintenance_mode', $request->input('maintenance_mode') ? '1' : '0');
        Settings::set('analytics_enabled', $request->input('analytics_enabled') ? '1' : '0');

        // SMTP password is write-only: only overwrite when a new value is typed,
        // so the saved password is never echoed back into the form.
        $mailPassword = (string) $request->input('mail_password', '');
        if ($mailPassword !== '') {
            Settings::set('mail_password', SecretCipher::encrypt($mailPassword));
        }

        AuditLog::record('settings.update', 'site_settings', null, null, $launch);
        return $this->redirectWith('/admin/settings', 'success', 'Settings saved.');
    }

    public function removeDemo(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $counts = (new DemoSeeder())->remove();
        AuditLog::record('settings.remove_demo', 'demo_data', null, null, json_encode($counts) ?: null);
        $total = array_sum($counts);
        return $this->redirectWith('/admin/settings', 'success', 'Removed ' . $total . ' demo record(s).');
    }

    /** @return array<string,int> */
    private function demoCounts(): array
    {
        $counts = [];
        foreach (['providers', 'service_runs', 'service_requests', 'caravan_parks'] as $table) {
            $counts[$table] = $this->scalar("SELECT COUNT(*) FROM {$table} WHERE is_demo = 1");
        }
        return $counts;
    }

    /**
     * Production readiness checks. Each item: [label, passed(bool|null), hint].
     * null = informational / cannot be auto-verified.
     *
     * @return array<int,array{label:string,ok:?bool,hint:string}>
     */
    private function checklist(): array
    {
        $appUrl = (string) config('app.url', '');
        $items = [];

        $items[] = [
            'label' => 'Application key set',
            'ok'    => trim((string) config('app.key', '')) !== '',
            'hint'  => 'APP_KEY must be set in .env (the installer generates it).',
        ];
        $items[] = [
            'label' => 'Debug mode off',
            'ok'    => !(bool) config('app.debug', false),
            'hint'  => 'Set APP_DEBUG=false in production.',
        ];
        $items[] = [
            'label' => 'HTTPS site URL',
            'ok'    => str_starts_with($appUrl, 'https://'),
            'hint'  => 'APP_URL should use https and match the live subdomain.',
        ];
        $items[] = [
            'label' => 'SMTP email configured',
            'ok'    => trim((string) (Settings::get('mail_host', '') ?: config('mail.host', ''))) !== '',
            'hint'  => 'Set the SMTP host/username/password under "Email sending (SMTP)" above so queued email can send.',
        ];
        $items[] = [
            'label' => 'Demo data removed',
            'ok'    => array_sum($this->demoCounts()) === 0,
            'hint'  => 'Remove demonstration records before going public.',
        ];
        $items[] = [
            'label' => 'Super administrator exists',
            'ok'    => $this->scalar(
                "SELECT COUNT(*) FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id "
                . "INNER JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'super-administrator'"
            ) > 0,
            'hint'  => 'At least one super administrator account is required.',
        ];
        $items[] = [
            'label' => 'A database backup has run',
            'ok'    => $this->scalar("SELECT COUNT(*) FROM scheduled_tasks WHERE task_key = 'database_backup' AND last_status = 'success'") > 0,
            'hint'  => 'Run the database_backup cron task at least once.',
        ];
        $items[] = [
            'label' => 'Search indexing decision made',
            'ok'    => null,
            'hint'  => 'Indexing is ' . ((string) Settings::get('seo_allow_indexing', '0') === '1' ? 'ON' : 'OFF') . '. Turn it on (SEO settings) when ready for public launch.',
        ];
        $items[] = [
            'label' => 'Legal pages reviewed',
            'ok'    => null,
            'hint'  => 'Have a professional review the privacy, terms and disclaimer pages before launch.',
        ];

        return $items;
    }

    private function scalar(string $sql): int
    {
        try {
            return (int) Database::scalar($sql);
        } catch (Throwable) {
            return 0;
        }
    }
}
