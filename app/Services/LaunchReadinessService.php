<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Town;
use Throwable;

/** Evidence-backed launch gates. Missing evidence is never treated as a pass. */
final class LaunchReadinessService
{
    /** @return array{status:string,groups:array<string,array{label:string,status:string,checks:array<int,array{label:string,status:string,detail:string}>}>} */
    public static function inspect(): array
    {
        $groups = [
            'data_trust' => self::dataTrust(),
            'search_reliability' => self::searchReliability(),
            'compliant_outreach' => self::compliantOutreach(),
            'operational_proof' => self::operationalProof(),
        ];

        return ['status' => self::groupStatus(array_column($groups, 'status')), 'groups' => $groups];
    }

    /** @return array{label:string,status:string,checks:array<int,array{label:string,status:string,detail:string}>} */
    private static function dataTrust(): array
    {
        $unsafeVisible = self::scalar(
            'SELECT COUNT(DISTINCT l.id) FROM provider_source_records psr '
            . 'JOIN providers p ON p.id=psr.provider_id JOIN provider_brand_listings l ON l.provider_id=p.id '
            . "WHERE p.is_unclaimed=1 AND psr.needs_review=1 AND l.status='active' AND l.search_visible=1 "
            . 'AND NOT EXISTS (SELECT 1 FROM provider_source_records good WHERE good.provider_id=p.id '
            . 'AND good.publishable=1 AND good.needs_review=0)'
        );
        $reviewQueue = self::scalar('SELECT COUNT(*) FROM provider_source_records WHERE needs_review=1');
        $unclaimed = self::scalar("SELECT COUNT(*) FROM providers WHERE status='active' AND is_unclaimed=1 AND deleted_at IS NULL");
        $verified = self::scalar("SELECT COUNT(*) FROM providers WHERE status='active' AND is_verified=1 AND deleted_at IS NULL");
        $locationConflicts = self::scalar(
            'SELECT COUNT(DISTINCT p.id) FROM providers p JOIN towns t ON t.id=p.base_town_id '
            . 'JOIN provider_brand_listings l ON l.provider_id=p.id '
            . "WHERE p.is_unclaimed=1 AND p.status='active' AND l.status='active' AND l.search_visible=1 "
            . 'AND p.deleted_at IS NULL AND l.deleted_at IS NULL '
            . 'AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL '
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL '
            . 'AND (6371 * ACOS(LEAST(1,GREATEST(-1, '
            . 'COS(RADIANS(p.latitude)) '
            . '* COS(RADIANS(t.latitude)) '
            . '* COS(RADIANS(t.longitude)-RADIANS(p.longitude)) '
            . '+ SIN(RADIANS(p.latitude)) '
            . '* SIN(RADIANS(t.latitude)))))) > 150'
        );

        $checks = [
            self::check('Unsafe review-only listings exposed', $unsafeVisible === 0 ? 'pass' : 'fail', $unsafeVisible === null ? 'evidence unavailable' : $unsafeVisible . ' visible'),
            self::check('Provider coordinates agree with displayed towns', $locationConflicts === 0 ? 'pass' : 'fail', $locationConflicts === null ? 'evidence unavailable' : $locationConflicts . ' unresolved public conflicts'),
            self::check('Source records held for review', $reviewQueue === null ? 'fail' : 'warning', $reviewQueue === null ? 'evidence unavailable' : $reviewQueue . ' quarantined/review records'),
            self::check('Public-source listings are labelled', $unclaimed !== null && $verified !== null ? 'pass' : 'fail', $unclaimed === null || $verified === null ? 'evidence unavailable' : $unclaimed . ' active unclaimed listings; ' . $verified . ' verified providers'),
        ];
        return self::group('Data trust', $checks);
    }

    /** @return array{label:string,status:string,checks:array<int,array{label:string,status:string,detail:string}>} */
    private static function searchReliability(): array
    {
        $fixtures = ['Emerald QLD', 'Emerald QLD 4720', '4720', 'Emu Park QLD', 'Brisbane Queensland'];
        $resolved = 0;
        foreach ($fixtures as $fixture) {
            if (Town::searchActive($fixture, 1) !== []) {
                $resolved++;
            }
        }
        $geocoded = self::scalar(
            "SELECT COUNT(*) FROM towns WHERE is_active=1 AND coordinate_confidence IN ('authoritative','statistical') "
            . 'AND latitude IS NOT NULL AND longitude IS NOT NULL'
        );
        $checks = [
            self::check('Representative town, state and postcode searches', $resolved === count($fixtures) ? 'pass' : 'fail', $resolved . '/' . count($fixtures) . ' resolved'),
            self::check(
                'Distance-capable Australian localities',
                $geocoded === null || $geocoded === 0 ? 'fail' : ($geocoded >= 13000 ? 'pass' : 'warning'),
                $geocoded === null ? 'evidence unavailable' : $geocoded . ' geocoded active towns; 13,000 is the national coverage target'
            ),
        ];
        return self::group('Search reliability', $checks);
    }

    /** @return array{label:string,status:string,checks:array<int,array{label:string,status:string,detail:string}>} */
    private static function compliantOutreach(): array
    {
        $graph = GraphMailHealth::inspect((array) config('mail'));
        $sentProbes = self::scalar(
            "SELECT COUNT(*) FROM email_queue WHERE template_key IN "
            . "('vanassist_dedicated_mailbox_probe_20260728','towsmart_dedicated_mailbox_probe_20260728','trailerwise_dedicated_mailbox_probe_20260728') "
            . "AND status='sent'"
        );
        $failed = self::scalar("SELECT COUNT(*) FROM email_queue WHERE status='failed'");
        $eligible = self::scalar(
            "SELECT COUNT(*) FROM providers WHERE status='active' AND deleted_at IS NULL AND marketing_opt_in=1 "
            . "AND marketing_consented_at IS NOT NULL AND marketing_consent_source IN "
            . "('express_written','express_phone','express_web','inferred_role_relevant') "
            . "AND NULLIF(TRIM(marketing_consent_evidence),'') IS NOT NULL"
        );
        $checks = [
            self::check('Microsoft Graph sender health', $graph['status'] === 'healthy' ? 'pass' : 'fail', (string) $graph['status']),
            self::check('Three application-path mailbox probes', $sentProbes === 3 ? 'pass' : 'fail', $sentProbes === null ? 'evidence unavailable' : $sentProbes . '/3 sent'),
            self::check('Failed mail requiring attention', $failed === null ? 'fail' : ($failed === 0 ? 'pass' : 'warning'), $failed === null ? 'evidence unavailable' : $failed . ' failed queue rows'),
            self::check('Consent-eligible provider audience', $eligible === null ? 'fail' : 'warning', $eligible === null ? 'evidence unavailable' : $eligible . ' providers; public addresses alone are excluded'),
        ];
        return self::group('Compliant outreach', $checks);
    }

    /** @return array{label:string,status:string,checks:array<int,array{label:string,status:string,detail:string}>} */
    private static function operationalProof(): array
    {
        $dirtyMigrations = self::scalar("SELECT COUNT(*) FROM migrations WHERE status<>'succeeded'");
        $localBackupTask = self::row("SELECT last_status,last_run_at,last_message FROM scheduled_tasks WHERE task_key='database_backup'");
        $localBackup = self::backupEvidence($localBackupTask, 36);
        $offsite = self::statusEvidence('offsite-backup.status.json', 36);
        $restore = self::statusEvidence('offsite-restore-drill.status.json', 24 * 8);
        $release = trim((string) config('app.release', ''));
        $turnstileEnabled = (bool) config('security.turnstile.enabled', false);
        $turnstileKeysReady = trim((string) config('security.turnstile.site_key', '')) !== ''
            && trim((string) config('security.turnstile.secret_key', '')) !== '';
        $checks = [
            self::check('Migration integrity', $dirtyMigrations === 0 ? 'pass' : 'fail', $dirtyMigrations === null ? 'evidence unavailable' : $dirtyMigrations . ' incomplete migrations'),
            self::check('Local scheduled database backup', $localBackup['status'], $localBackup['detail']),
            self::check('Encrypted independent off-site backup', $offsite['status'], $offsite['detail']),
            self::check('Independent restore rehearsal', $restore['status'], $restore['detail']),
            self::check('Traceable immutable release', $release !== '' ? 'pass' : 'fail', $release !== '' ? $release : 'release identifier missing'),
            self::check(
                'Public form bot challenge',
                $turnstileEnabled && $turnstileKeysReady ? 'pass' : 'warning',
                $turnstileEnabled && $turnstileKeysReady ? 'server-verified Turnstile enabled' : 'rate limits and honeypots active; Turnstile is not enabled'
            ),
        ];
        return self::group('Operational proof', $checks);
    }

    /** @return array{status:string,detail:string} */
    private static function statusEvidence(string $name, int $maxAgeHours): array
    {
        $path = base_path('storage/ops/' . $name);
        if (!is_file($path)) {
            return ['status' => 'fail', 'detail' => 'no success evidence file'];
        }
        $value = json_decode((string) file_get_contents($path), true);
        $timestamp = is_array($value) ? strtotime((string) ($value['completed_at'] ?? '')) : false;
        if (!is_array($value) || ($value['status'] ?? null) !== 'success' || $timestamp === false) {
            return ['status' => 'fail', 'detail' => 'latest evidence is invalid or failed'];
        }
        $ageHours = (int) floor((time() - $timestamp) / 3600);
        return $ageHours <= $maxAgeHours
            ? ['status' => 'pass', 'detail' => 'passed ' . $ageHours . ' hour(s) ago']
            : ['status' => 'fail', 'detail' => 'last pass is ' . $ageHours . ' hours old'];
    }

    /** @param array<int,array{label:string,status:string,detail:string}> $checks @return array{label:string,status:string,checks:array<int,array{label:string,status:string,detail:string}>} */
    private static function group(string $label, array $checks): array
    {
        return ['label' => $label, 'status' => self::groupStatus(array_column($checks, 'status')), 'checks' => $checks];
    }

    /** @param array<int,string> $statuses */
    private static function groupStatus(array $statuses): string
    {
        return in_array('fail', $statuses, true) ? 'fail' : (in_array('warning', $statuses, true) ? 'warning' : 'pass');
    }

    /** @return array{label:string,status:string,detail:string} */
    private static function check(string $label, string $status, string $detail): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }

    private static function scalar(string $sql): ?int
    {
        try {
            return (int) Database::scalar($sql);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed>|null */
    private static function row(string $sql): ?array
    {
        try {
            return Database::selectOne($sql);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed>|null $task @return array{status:string,detail:string} */
    private static function backupEvidence(?array $task, int $maxAgeHours): array
    {
        if ($task === null) {
            return ['status' => 'fail', 'detail' => 'evidence unavailable'];
        }
        $status = (string) ($task['last_status'] ?? 'unknown');
        $ranAt = trim((string) ($task['last_run_at'] ?? ''));
        $message = trim((string) ($task['last_message'] ?? ''));
        if ($status === 'running' && $ranAt !== '' && ($started = strtotime($ranAt)) !== false && $started < time() - 3600) {
            return ['status' => 'fail', 'detail' => 'task has been stuck running since ' . $ranAt];
        }
        if ($status !== 'success') {
            return ['status' => 'fail', 'detail' => 'latest task status is ' . $status . ($message !== '' ? ': ' . mb_substr($message, 0, 180) : '')];
        }
        $timestamp = $ranAt !== '' ? strtotime($ranAt) : false;
        if ($timestamp === false) {
            return ['status' => 'fail', 'detail' => 'successful task has no valid completion time'];
        }
        $ageHours = (int) floor((time() - $timestamp) / 3600);
        if ($ageHours > $maxAgeHours) {
            return ['status' => 'fail', 'detail' => 'last successful local backup is ' . $ageHours . ' hours old'];
        }
        $result = json_decode($message, true);
        $name = is_array($result) ? basename((string) ($result['file'] ?? '')) : '';
        if ($name === '' || $name !== (string) ($result['file'] ?? '')) {
            return ['status' => 'fail', 'detail' => 'successful task has no safe backup filename evidence'];
        }
        $path = base_path('storage/backups/' . $name);
        $manifest = $path . '.sha256';
        if (!is_file($path) || filesize($path) === 0 || !is_file($manifest)) {
            return ['status' => 'fail', 'detail' => 'recorded backup or checksum manifest is missing'];
        }
        $expected = strtok(trim((string) file_get_contents($manifest)), " \t");
        $actual = hash_file('sha256', $path);
        if (!is_string($expected) || !is_string($actual) || $expected === '' || !hash_equals($expected, $actual)) {
            return ['status' => 'fail', 'detail' => 'recorded backup checksum does not verify'];
        }
        return ['status' => 'pass', 'detail' => $name . ' verified; completed ' . $ageHours . ' hour(s) ago'];
    }
}
