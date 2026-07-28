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
            'SELECT COUNT(DISTINCT psr.id) FROM provider_source_records psr '
            . 'JOIN providers p ON p.id=psr.provider_id JOIN towns t ON t.id=p.base_town_id '
            . 'JOIN provider_brand_listings l ON l.provider_id=p.id '
            . "WHERE p.is_unclaimed=1 AND p.status='active' AND l.status='active' AND l.search_visible=1 "
            . 'AND psr.publishable=1 AND psr.needs_review=0 '
            . "AND JSON_TYPE(JSON_EXTRACT(psr.payload_json,'$.lat')) IN ('INTEGER','DOUBLE') "
            . "AND JSON_TYPE(JSON_EXTRACT(psr.payload_json,'$.lng')) IN ('INTEGER','DOUBLE') "
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL '
            . 'AND (6371 * ACOS(LEAST(1,GREATEST(-1, '
            . "COS(RADIANS(CAST(JSON_UNQUOTE(JSON_EXTRACT(psr.payload_json,'$.lat')) AS DECIMAL(10,6)))) "
            . '* COS(RADIANS(t.latitude)) '
            . "* COS(RADIANS(t.longitude)-RADIANS(CAST(JSON_UNQUOTE(JSON_EXTRACT(psr.payload_json,'$.lng')) AS DECIMAL(10,6)))) "
            . "+ SIN(RADIANS(CAST(JSON_UNQUOTE(JSON_EXTRACT(psr.payload_json,'$.lat')) AS DECIMAL(10,6)))) "
            . '* SIN(RADIANS(t.latitude))))) > 150'
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
        $localBackupCount = self::scalar("SELECT COUNT(*) FROM scheduled_tasks WHERE task_key='database_backup' AND last_status='success'");
        $localBackup = $localBackupCount !== null && $localBackupCount > 0;
        $offsite = self::statusEvidence('offsite-backup.status.json', 36);
        $restore = self::statusEvidence('offsite-restore-drill.status.json', 24 * 8);
        $release = trim((string) config('app.release', ''));
        $checks = [
            self::check('Migration integrity', $dirtyMigrations === 0 ? 'pass' : 'fail', $dirtyMigrations === null ? 'evidence unavailable' : $dirtyMigrations . ' incomplete migrations'),
            self::check('Local scheduled database backup', $localBackup ? 'pass' : 'fail', $localBackupCount === null ? 'evidence unavailable' : ($localBackup ? 'successful task recorded' : 'no successful task recorded')),
            self::check('Encrypted independent off-site backup', $offsite['status'], $offsite['detail']),
            self::check('Independent restore rehearsal', $restore['status'], $restore['detail']),
            self::check('Traceable immutable release', $release !== '' ? 'pass' : 'fail', $release !== '' ? $release : 'release identifier missing'),
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
}
