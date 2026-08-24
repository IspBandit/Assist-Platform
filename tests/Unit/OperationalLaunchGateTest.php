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
        self::assertStringNotContainsString("JSON_EXTRACT(psr.payload_json,'$.lat')", $gate);
    }
}
