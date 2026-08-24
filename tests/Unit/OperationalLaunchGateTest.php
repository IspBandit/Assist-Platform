<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OperationalLaunchGateTest extends TestCase
{
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
}
