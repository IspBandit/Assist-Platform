<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Runs a named scheduled task with an exclusive lock so overlapping cron
 * invocations cannot run the same job twice. Records the outcome in the
 * scheduled_tasks table and writes a cron log.
 */
final class CronRunner
{
    /** @var array<string,callable> */
    private array $handlers;

    public function __construct()
    {
        $this->handlers = [
            'process_email_queue'   => static fn () => Mailer::processQueue(),
            'expire_sessions'       => fn () => $this->expireSessions(),
            'update_run_capacity'   => fn () => $this->updateRunCapacity(),
            'update_town_demand'    => fn () => $this->updateTownDemand(),
            'expire_requests'       => fn () => $this->expireRequests(),
            'clean_temp'            => fn () => $this->cleanTemp(),
            'clean_logs'            => fn () => $this->cleanLogs(),
            'database_backup'       => fn () => (new Backup())->run(),
            // Notifications & reminders (Phase 9).
            'process_notifications'    => fn () => $this->processNotifications(),
            'send_run_reminders'       => fn () => $this->sendRunReminders(),
            'provider_followups'       => fn () => $this->providerFollowups(),
            'document_expiry'          => fn () => $this->documentExpiry(),
            // Demand analytics (Phase 11). No-op unless the demand_analytics flag is on.
            'aggregate_daily_metrics'  => fn () => $this->aggregateDailyMetrics(),
            'customer_followups'       => fn () => $this->customerFollowups(),
            'analytics_retention'      => fn () => $this->analyticsRetention(),
            // Automated request -> provider matching (no-op unless auto_matching flag is on).
            'update_match_suggestions' => static fn () => (new AutoMatchService())->runBatch(),
            // Provider directory imports (resume from seed fingerprint; no-op when up to date).
            'import_osm'               => static fn () => (new ProviderImportRunner())->cronOsm(45.0),
            'import_locality'          => static fn () => (new ProviderImportRunner())->cronLocality(45.0),
        ];
    }

    public function run(string $task): int
    {
        if (!isset($this->handlers[$task])) {
            fwrite(STDERR, "Unknown cron task: {$task}\n");
            return 2;
        }

        $lockDir = base_path('storage/locks');
        if (!is_dir($lockDir) && !mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
            Logger::error("Unable to create cron lock directory for {$task}.", [], 'cron');
            return 1;
        }
        $lockFile = $lockDir . '/cron-' . preg_replace('/[^a-z0-9_]/', '', $task) . '.lock';
        $fp = fopen($lockFile, 'c');
        if ($fp === false || !flock($fp, LOCK_EX | LOCK_NB)) {
            Logger::warning("Cron task {$task} already running; skipped.", [], 'cron');
            return 0;
        }

        $start = microtime(true);
        $this->markRunning($task);

        try {
            $result = ($this->handlers[$task])();
            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->markResult($task, 'success', $this->summarise($result), $duration);
            Logger::info("Cron task {$task} completed.", ['result' => $result, 'ms' => $duration], 'cron');
            $exit = 0;
        } catch (Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->markResult($task, 'failed', substr($e->getMessage(), 0, 480), $duration);
            Logger::error("Cron task {$task} failed: " . $e->getMessage(), [], 'cron');
            $exit = 1;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lockFile);
        }

        return $exit;
    }

    // ----- Task implementations ------------------------------------------

    private function expireSessions(): array
    {
        $lifetime = (int) config('security.session.lifetime_minutes', 120) * 60;
        $deleted = 0;
        $dir = base_path('storage/sessions');
        foreach (glob($dir . '/sess_*') ?: [] as $file) {
            if (is_file($file) && (time() - filemtime($file)) > $lifetime) {
                @unlink($file);
                $deleted++;
            }
        }
        Database::query('DELETE FROM user_sessions WHERE last_activity < ?', [time() - $lifetime]);
        $rateLimits = RateLimiter::prune();
        return ['expired_session_files' => $deleted, 'expired_rate_limits' => $rateLimits];
    }

    private function updateRunCapacity(): array
    {
        Database::query(
            'UPDATE service_runs r SET bookings_count = ('
            . "SELECT COUNT(*) FROM service_run_bookings b WHERE b.run_id = r.id AND b.status IN ('joined','confirmed','completed')"
            . ')'
        );
        return ['updated' => 'run capacity'];
    }

    private function updateTownDemand(): array
    {
        // Town demand is computed on demand from open requests; this task is a
        // placeholder hook for caching totals in future. Returns current count.
        $open = (int) Database::scalar("SELECT COUNT(*) FROM service_requests WHERE status IN ('open','matching')");
        return ['open_requests' => $open];
    }

    private function expireRequests(): array
    {
        $count = Database::affecting(
            "UPDATE service_requests SET status = 'expired', updated_at = NOW() "
            . "WHERE status = 'awaiting_verification' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        return ['expired_requests' => $count];
    }

    private function cleanTemp(): array
    {
        $cleaned = 0;
        $dir = base_path('storage/cache');
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 86400) {
                @unlink($file);
                $cleaned++;
            }
        }
        return ['cleaned' => $cleaned];
    }

    private function cleanLogs(): array
    {
        $cleaned = 0;
        $dir = base_path('storage/logs');
        foreach (glob($dir . '/*.log') ?: [] as $file) {
            if (is_file($file) && filesize($file) > 10 * 1024 * 1024) {
                @file_put_contents($file, '');
                $cleaned++;
            }
        }
        return ['truncated_logs' => $cleaned];
    }

    /** Dispatch any scheduled broadcasts that have come due. */
    private function processNotifications(): array
    {
        $due = Database::select(
            "SELECT id FROM notifications WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW() ORDER BY scheduled_at ASC LIMIT 50"
        );
        $dispatched = 0;
        $recipients = 0;
        foreach ($due as $row) {
            $result = NotificationService::dispatch((int) $row['id']);
            $dispatched++;
            $recipients += $result['recipients'];
        }
        return ['dispatched' => $dispatched, 'recipients' => $recipients];
    }

    /** Email a reminder to customers booked on runs starting in N days. */
    private function sendRunReminders(): array
    {
        $daysBefore = (int) config('notifications.run_reminder_days_before', 2);
        $target = date('Y-m-d', strtotime("+{$daysBefore} days"));

        $rows = Database::select(
            "SELECT r.id AS run_id, r.title AS run_title, r.slug, "
            . "COALESCE(u.email, sr.contact_email) AS email, "
            . "COALESCE(u.name, sr.contact_name, 'there') AS name "
            . "FROM service_run_bookings b "
            . "INNER JOIN service_runs r ON r.id = b.run_id "
            . "LEFT JOIN customers c ON c.id = b.customer_id "
            . "LEFT JOIN users u ON u.id = c.user_id "
            . "LEFT JOIN service_requests sr ON sr.id = b.request_id "
            . "WHERE b.status IN ('joined','confirmed') AND r.deleted_at IS NULL "
            . "AND r.status IN ('confirmed','limited','fully_booked') AND DATE(r.start_date) = ?",
            [$target]
        );

        $sent = 0;
        foreach ($rows as $row) {
            $email = (string) ($row['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $queued = EmailQueue::queueTemplate('run_reminder', $email, (string) $row['name'], [
                'customer_name' => (string) $row['name'],
                'run_title'     => (string) $row['run_title'],
                'action_url'    => url('service-runs/' . $row['slug']),
            ]);
            if ($queued) {
                $sent++;
            }
        }
        return ['reminder_date' => $target, 'queued' => $sent];
    }

    /** Remind providers about verification documents expiring at set intervals. */
    private function documentExpiry(): array
    {
        $windows = [30, 14, 7];
        $dates = array_map(static fn (int $d) => date('Y-m-d', strtotime("+{$d} days")), $windows);
        $placeholders = implode(',', array_fill(0, count($dates), '?'));

        $rows = Database::select(
            "SELECT DISTINCT p.id, COALESCE(NULLIF(p.email,''), NULLIF(p.public_email,'')) AS email, p.business_name "
            . "FROM provider_licences l INNER JOIN providers p ON p.id = l.provider_id "
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL "
            . "AND l.verification_status = 'verified' AND l.expiry_date IN ({$placeholders})",
            $dates
        );

        $sent = 0;
        foreach ($rows as $row) {
            $email = (string) ($row['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $queued = EmailQueue::queueTemplate('document_expiry_reminder', $email, (string) $row['business_name'], [
                'provider_name' => (string) $row['business_name'],
                'action_url'    => url('provider/documents'),
            ]);
            if ($queued) {
                $sent++;
            }
        }
        return ['queued' => $sent];
    }

    /**
     * Surface provider follow-up work for the team: invitations about to expire
     * unaccepted, and applications awaiting review. Recorded for the dashboard;
     * re-issuing tokenised invitations stays a deliberate admin action.
     */
    private function providerFollowups(): array
    {
        $expiringInvites = (int) Database::scalar(
            "SELECT COUNT(*) FROM provider_invitations WHERE accepted_at IS NULL AND expires_at > NOW() AND expires_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)"
        );
        $staleApplications = (int) Database::scalar(
            "SELECT COUNT(*) FROM providers WHERE status = 'pending' AND deleted_at IS NULL AND created_at <= DATE_SUB(NOW(), INTERVAL 5 DAY)"
        );

        Database::query(
            'INSERT INTO system_health_logs (metric_key, metric_value, created_at) VALUES (?, ?, NOW())',
            ['provider_followups', json_encode(['expiring_invites' => $expiringInvites, 'stale_applications' => $staleApplications])]
        );

        $result = ['expiring_invites' => $expiringInvites, 'stale_applications' => $staleApplications];

        // Escalate silent auto-match invites to the next batch of providers.
        if (AutoMatchService::enabled()) {
            $result['escalation'] = (new AutoMatchService())->escalate();
        }

        return $result;
    }

    /**
     * Roll up the previous day's raw analytics into provider_daily_metrics and
     * demand_daily_metrics so dashboards never scan millions of event rows.
     * Idempotent: the target day's aggregate rows are rebuilt each run. No-op
     * unless the demand_analytics feature flag is on.
     */
    private function aggregateDailyMetrics(): array
    {
        if (!FeatureFlag::enabled('demand_analytics')) {
            return ['note' => 'demand_analytics disabled; skipped'];
        }

        $date = date('Y-m-d', strtotime('-1 day'));

        // ---- Provider metrics -------------------------------------------
        $providers = [];
        $touch = static function (array &$bag, int $pid): void {
            if (!isset($bag[$pid])) {
                $bag[$pid] = array_fill_keys([
                    'impressions', 'unique_impressions', 'profile_views', 'unique_profile_views',
                    'phone_clicks', 'email_clicks', 'website_clicks', 'directions_clicks',
                    'requests', 'responses', 'quotes', 'selections', 'bookings',
                    'completed_jobs', 'customer_confirmed_jobs', 'mutually_confirmed_jobs',
                    'cancellations', 'reviews', 'rating_total', 'response_time_total',
                ], 0);
            }
        };

        foreach (Database::select(
            'SELECT provider_id, COUNT(*) c, COUNT(DISTINCT search_id) u FROM provider_search_results '
            . 'WHERE DATE(created_at) = ? GROUP BY provider_id',
            [$date]
        ) as $r) {
            $pid = (int) $r['provider_id'];
            $touch($providers, $pid);
            $providers[$pid]['impressions'] += (int) $r['c'];
            $providers[$pid]['unique_impressions'] += (int) $r['u'];
        }

        $eventCol = [
            'provider_profile_viewed' => 'profile_views',
            'provider_responded'      => 'responses',
            'quote_received'          => 'quotes',
            'provider_selected'       => 'selections',
            'job_booked'              => 'bookings',
            'job_cancelled'           => 'cancellations',
            'review_submitted'        => 'reviews',
        ];
        foreach (Database::select(
            'SELECT provider_id, event_name, COUNT(*) c, COUNT(DISTINCT session_id) u FROM analytics_events '
            . 'WHERE DATE(created_at) = ? AND is_excluded = 0 AND provider_id IS NOT NULL GROUP BY provider_id, event_name',
            [$date]
        ) as $r) {
            $name = (string) $r['event_name'];
            if (!isset($eventCol[$name])) {
                continue;
            }
            $pid = (int) $r['provider_id'];
            $touch($providers, $pid);
            $providers[$pid][$eventCol[$name]] += (int) $r['c'];
            if ($name === 'provider_profile_viewed') {
                $providers[$pid]['unique_profile_views'] += (int) $r['u'];
            }
        }

        $actionCol = [
            'phone' => 'phone_clicks', 'email' => 'email_clicks',
            'website' => 'website_clicks', 'directions' => 'directions_clicks',
            'assistance_request' => 'requests',
        ];
        foreach (Database::select(
            'SELECT provider_id, action_type, COUNT(*) c FROM provider_contact_actions '
            . 'WHERE DATE(created_at) = ? AND is_excluded = 0 GROUP BY provider_id, action_type',
            [$date]
        ) as $r) {
            $type = (string) $r['action_type'];
            if (!isset($actionCol[$type])) {
                continue;
            }
            $pid = (int) $r['provider_id'];
            $touch($providers, $pid);
            $providers[$pid][$actionCol[$type]] += (int) $r['c'];
        }

        foreach (Database::select(
            "SELECT provider_id, COUNT(*) completed, "
            . "SUM(customer_confirmed = 1) cc, "
            . "SUM(customer_confirmed = 1 AND provider_confirmed = 1) mc "
            . "FROM service_outcomes WHERE status = 'completed' AND is_excluded = 0 AND DATE(completed_at) = ? GROUP BY provider_id",
            [$date]
        ) as $r) {
            $pid = (int) $r['provider_id'];
            $touch($providers, $pid);
            $providers[$pid]['completed_jobs'] += (int) $r['completed'];
            $providers[$pid]['customer_confirmed_jobs'] += (int) $r['cc'];
            $providers[$pid]['mutually_confirmed_jobs'] += (int) $r['mc'];
        }

        Database::query('DELETE FROM provider_daily_metrics WHERE metric_date = ?', [$date]);
        foreach ($providers as $pid => $v) {
            Database::query(
                'INSERT INTO provider_daily_metrics (metric_date, provider_id, impressions, unique_impressions, '
                . 'profile_views, unique_profile_views, phone_clicks, email_clicks, website_clicks, directions_clicks, '
                . 'requests, responses, quotes, selections, bookings, completed_jobs, customer_confirmed_jobs, '
                . 'mutually_confirmed_jobs, cancellations, reviews, rating_total, response_time_total, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $date, $pid, $v['impressions'], $v['unique_impressions'], $v['profile_views'],
                    $v['unique_profile_views'], $v['phone_clicks'], $v['email_clicks'], $v['website_clicks'],
                    $v['directions_clicks'], $v['requests'], $v['responses'], $v['quotes'], $v['selections'],
                    $v['bookings'], $v['completed_jobs'], $v['customer_confirmed_jobs'], $v['mutually_confirmed_jobs'],
                    $v['cancellations'], $v['reviews'], $v['rating_total'], $v['response_time_total'],
                ]
            );
        }

        // ---- Demand metrics (location x category) -----------------------
        Database::query('DELETE FROM demand_daily_metrics WHERE metric_date = ?', [$date]);
        $rows = Database::select(
            "SELECT town_id, region_id, category_id, COUNT(*) searches, COUNT(DISTINCT session_id) sessions, "
            . "SUM(led_to_request = 1) requests, SUM(result_count = 0) no_results, SUM(urgency = 'urgent') urgent "
            . "FROM provider_searches WHERE DATE(created_at) = ? AND is_excluded = 0 GROUP BY town_id, region_id, category_id",
            [$date]
        );
        foreach ($rows as $r) {
            $confirmed = (int) Database::scalar(
                "SELECT COUNT(*) FROM service_outcomes WHERE status = 'completed' AND is_excluded = 0 "
                . "AND DATE(completed_at) = ? AND (town_id <=> ?) AND (category_id <=> ?)",
                [$date, $r['town_id'], $r['category_id']]
            );
            Database::query(
                'INSERT INTO demand_daily_metrics (metric_date, town_id, region_id, category_id, searches, '
                . 'unique_sessions, requests, no_result_searches, urgent_searches, provider_contacts, confirmed_jobs, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())',
                [
                    $date, $r['town_id'], $r['region_id'], $r['category_id'], (int) $r['searches'],
                    (int) $r['sessions'], (int) $r['requests'], (int) $r['no_results'], (int) $r['urgent'], $confirmed,
                ]
            );
        }

        return ['date' => $date, 'providers' => count($providers), 'demand_rows' => count($rows)];
    }

    /**
     * Process due customer outcome follow-ups. Delivery wiring (email templates,
     * tokenised response links) lands with the Phase 11 follow-up service; this
     * registered handler safely reports the due backlog until then so the cron
     * entry can be created now without sending anything.
     */
    private function customerFollowups(): array
    {
        if (!FeatureFlag::enabled('demand_analytics')) {
            return ['note' => 'demand_analytics disabled; skipped'];
        }
        return \App\Services\Demand\FollowupService::run();
    }

    /**
     * Purge raw analytics past the configured retention window. Aggregated
     * daily metrics (the long-term source for dashboards) are retained.
     */
    private function analyticsRetention(): array
    {
        if (!FeatureFlag::enabled('demand_analytics')) {
            return ['note' => 'demand_analytics disabled; skipped'];
        }
        $eventDays = max(30, (int) Settings::get('analytics_retention_event_days', '365'));
        $sessionDays = max(30, (int) Settings::get('analytics_retention_session_days', '540'));

        $events = Database::affecting(
            "DELETE FROM analytics_events WHERE created_at < DATE_SUB(NOW(), INTERVAL {$eventDays} DAY)"
        );
        $sessions = Database::affecting(
            'DELETE FROM tracking_sessions WHERE user_id IS NULL AND customer_id IS NULL '
            . "AND last_seen_at < DATE_SUB(NOW(), INTERVAL {$sessionDays} DAY)"
        );

        return ['events_purged' => $events, 'sessions_purged' => $sessions];
    }

    private function markRunning(string $task): void
    {
        Database::query(
            'INSERT INTO scheduled_tasks (task_key, last_status, last_run_at) VALUES (?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE last_status = VALUES(last_status), last_run_at = NOW()',
            [$task, 'running']
        );
    }

    private function markResult(string $task, string $status, string $message, int $durationMs): void
    {
        Database::query(
            'UPDATE scheduled_tasks SET last_status = ?, last_message = ?, last_duration_ms = ?, last_run_at = NOW() WHERE task_key = ?',
            [$status, $message, $durationMs, $task]
        );
    }

    private function summarise(mixed $result): string
    {
        return is_array($result) ? substr(json_encode($result) ?: '', 0, 480) : (string) $result;
    }
}
