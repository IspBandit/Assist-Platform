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

    public function testCqDiggingsClermontReleaseOverlayIsCompleteAndReadOnly(): void
    {
        $compose = (string) file_get_contents(base_path('infrastructure/binarylane/docker-compose.yml'));
        $overlay = base_path('infrastructure/cqdiggings-overlay');
        $release = (string) file_get_contents($overlay . '/RELEASE.md');
        $files = [
            'clermont-blair-athol.html',
            'clermont-gold-investigation.css',
            'clermont-gold-investigation.html',
            'clermont-gold-investigation.js',
            'data/clermont-field-validation-points.geojson',
            'data/clermont-legal-gold-prospectivity.geojson',
            'data/clermont-prospectivity-watercourses.geojson',
            'index.html',
            'map-20260814.js',
            'map.html',
            'old-diggings-map-regional.js',
            'old-diggings-map.html',
            'service-worker.js',
            'site-index.html',
            'sitemap.xml',
        ];

        self::assertStringContainsString('d3f4f5ea76c00ecea5ce6159abe1fa79e8ece3a0', $release);
        foreach ($files as $file) {
            self::assertFileExists($overlay . '/' . $file);
            self::assertSame(
                2,
                substr_count(
                    $compose,
                    './current/infrastructure/cqdiggings-overlay/' . $file
                    . ':/var/www/cqdiggings/' . $file . ':ro'
                ),
                $file . ' must be mounted read-only in Caddy and PHP.'
            );
        }

        self::assertStringContainsString('cqdiggings-field-v55', (string) file_get_contents($overlay . '/service-worker.js'));
        self::assertStringContainsString('Where could a prospector legally look for gold near Clermont?', (string) file_get_contents($overlay . '/clermont-gold-investigation.html'));
        self::assertSame(24, $this->geoJsonFeatureCount($overlay . '/data/clermont-legal-gold-prospectivity.geojson'));
        self::assertSame(150, $this->geoJsonFeatureCount($overlay . '/data/clermont-prospectivity-watercourses.geojson'));
        self::assertSame(15, $this->geoJsonFeatureCount($overlay . '/data/clermont-field-validation-points.geojson'));
    }

    private function geoJsonFeatureCount(string $path): int
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('FeatureCollection', $decoded['type'] ?? null);
        self::assertIsArray($decoded['features'] ?? null);

        return count($decoded['features']);
    }
}
