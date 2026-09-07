<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperationalLaunchGateTest extends TestCase
{
    public function testProductionScheduleCoversEveryRecurringPlatformTask(): void
    {
        $cron = $this->source('infrastructure/binarylane/ops/assist-platform.cron');

        foreach ([
            'process_email_queue',
            'process_notifications',
            'process_provider_import_queue',
            'expire_sessions',
            'update_run_capacity',
            'update_match_suggestions',
            'expire_requests',
            'update_town_demand',
            'customer_followups',
            'database_backup',
            'clean_temp',
            'clean_logs',
            'analytics_retention',
            'ai_retention',
            'aggregate_daily_metrics',
            'regulatory_alerts',
            'vanassist_daily_performance_email',
            'send_run_reminders',
            'provider_followups',
            'document_expiry',
        ] as $task) {
            self::assertSame(1, preg_match('/^[^#\n]*assist-cron ' . preg_quote($task, '/') . '(?:\s|$)/m', $cron), $task);
        }
    }

    public function testProductionReleaseBindsEveryHealthEndpointToTheExactCommit(): void
    {
        $workflow = $this->source('.github/workflows/production-release.yml');
        $kernel = $this->source('app/Core/Kernel.php');

        self::assertStringContainsString('for endpoint in healthz readyz', $workflow);
        self::assertStringContainsString('.release == $release', $workflow);
        self::assertStringContainsString('"$GITHUB_SHA"', $workflow);
        self::assertGreaterThanOrEqual(2, substr_count($kernel, "\$payload['release'] = \$release"));
    }

    public function testScheduledBackupUsesNativeClientAndVerifiableEvidence(): void
    {
        $docker = (string) file_get_contents(base_path('infrastructure/binarylane/Dockerfile'));
        $backup = (string) file_get_contents(base_path('app/Services/Backup.php'));
        $gate = (string) file_get_contents(base_path('app/Services/LaunchReadinessService.php'));

        self::assertStringContainsString('mariadb-client', $docker);
        self::assertStringContainsString('mariadb-dump --defaults-extra-file=', $backup);
        self::assertStringContainsString("hash_file('sha256', \$file)", $backup);
        self::assertStringContainsString("\$file . '.sha256'", $backup);
        self::assertStringContainsString('self::backupEvidence($localBackupTask, 36)', $gate);
        self::assertStringContainsString("hash_equals(\$expected, \$actual)", $gate);
        self::assertStringContainsString('task has been stuck running since', $gate);
    }

    public function testOffsiteRecoverySetContainsApplicationDatabaseMediaAndProtectedConfiguration(): void
    {
        $backup = $this->source('infrastructure/binarylane/ops/assist-offsite-backup.sh');
        $restore = $this->source('infrastructure/binarylane/ops/assist-offsite-restore-drill.sh');

        foreach (['backups/database', 'shared/storage/private', 'shared/uploads-public', 'config/app.env', 'config/infra.env', 'releases/$release'] as $required) {
            self::assertStringContainsString($required, $backup);
        }
        self::assertStringContainsString('release-$release', $backup);
        self::assertStringContainsString('backup-set release metadata', strtolower($restore));
        self::assertStringContainsString('vendor/autoload.php', $restore);
        self::assertStringContainsString('protected application or infrastructure configuration was not restored', strtolower($restore));
        self::assertStringContainsString('private or public media directories were not restored', strtolower($restore));
        self::assertStringContainsString('restored_tables', $restore);
    }

    public function testCoordinateGateChecksPublishedProviderCoordinates(): void
    {
        $gate = (string) file_get_contents(base_path('app/Services/LaunchReadinessService.php'));

        self::assertStringContainsString('COS(RADIANS(p.latitude))', $gate);
        self::assertStringContainsString('RADIANS(p.longitude)', $gate);
        self::assertStringContainsString('* SIN(RADIANS(t.latitude)))))) > 150', $gate);
        self::assertStringNotContainsString("JSON_EXTRACT(psr.payload_json,'$.lat')", $gate);
    }

    public function testImmutableReleaseRefreshesBootstrapManagedRuntimeFiles(): void
    {
        $release = (string) file_get_contents(base_path('scripts/release-remote.sh'));

        self::assertStringContainsString('runtime_source="$target/infrastructure/binarylane"', $release);
        self::assertStringContainsString('runtime-rollback-$release', $release);
        self::assertStringContainsString('"$runtime_source/Dockerfile" "$root/runtime/Dockerfile"', $release);
        self::assertStringContainsString('"$runtime_source/docker-compose.yml" "$root/docker-compose.yml"', $release);
        self::assertStringContainsString('find "$root/runtime/ops" -maxdepth 1 -type f -name \'*.sh\' -delete', $release);
        self::assertStringContainsString('find "$runtime_source/ops"', $release);
    }

    public function testCqDiggingsDetectorReportsUsePrivatePersistentStorage(): void
    {
        $compose = (string) file_get_contents(base_path('infrastructure/binarylane/docker-compose.yml'));
        $caddy = (string) file_get_contents(base_path('infrastructure/binarylane/Caddyfile'));
        $release = (string) file_get_contents(base_path('scripts/release-remote.sh'));

        self::assertStringContainsString('/opt/cqdiggings/shared/analytics/_detector-settings:/var/www/cqdiggings/analytics/_detector-settings', $compose);
        self::assertStringContainsString('/opt/cqdiggings/shared/analytics/_detector-setting-uploads:/var/www/cqdiggings/analytics/_detector-setting-uploads', $compose);
        self::assertStringContainsString('/opt/cqdiggings/shared/data:/srv/cqdiggings-public/data:ro', $compose);
        self::assertStringContainsString('/opt/cqdiggings/shared/assets:/srv/cqdiggings-public/assets:ro', $compose);
        self::assertStringContainsString('handle /data/community-detector-settings.json', $caddy);
        self::assertStringContainsString('root * /srv/cqdiggings-public', $caddy);
        self::assertStringContainsString('/analytics/_detector-settings/*', $caddy);
        self::assertStringContainsString('/analytics/_detector-setting-uploads/*', $caddy);
        self::assertStringContainsString('install -d -o 82 -g 82 -m 0750', $release);
        self::assertStringContainsString('install -d -o 82 -g 82 -m 0755', $release);
        self::assertStringContainsString('chmod 0644 "$cq_root/shared/data/community-detector-settings.json"', $release);
        self::assertStringContainsString('community-detector-settings.json', $release);
    }

    public function testCqDiggingsProductFilesAreOwnedByItsRelease(): void
    {
        $compose = (string) file_get_contents(base_path('infrastructure/binarylane/docker-compose.yml'));
        $caddy = (string) file_get_contents(base_path('infrastructure/binarylane/Caddyfile'));

        self::assertStringContainsString('/opt/cqdiggings/current:/var/www/cqdiggings:ro', $compose);
        self::assertStringNotContainsString('/srv/cqdiggings-overlay', $compose);
        self::assertStringNotContainsString('/srv/cqdiggings-overlay', $caddy);
        self::assertStringNotContainsString('@releaseOverlay', $caddy);
        self::assertStringContainsString('root * /var/www/cqdiggings', $caddy);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        self::assertIsString($source);

        return $source;
    }

    private function geoJsonFeatureCount(string $path): int
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('FeatureCollection', $decoded['type'] ?? null);
        self::assertIsArray($decoded['features'] ?? null);

        return count($decoded['features']);
    }

    private function jsonListCount(string $path, string $key): int
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded[$key] ?? null);

        return count($decoded[$key]);
    }
}
