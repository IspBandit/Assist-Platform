<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Validates the committed VanAssist source registry contract (DATA-012 / VAN-001).
 */
final class VanAssistSourceRegistryTest extends TestCase
{
    public function testRegistryExistsWithRequiredFields(): void
    {
        $path = dirname(__DIR__, 2) . '/data/sources/vanassist/registry/sources.json';
        self::assertFileExists($path);
        $raw = file_get_contents($path);
        self::assertNotFalse($raw);
        $data = json_decode($raw, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('summary', $data);
        self::assertArrayHasKey('sources', $data);
        self::assertNotEmpty($data['sources']);

        $required = [
            'source_id', 'source_name', 'publisher', 'jurisdiction', 'landing_page',
            'licence', 'attribution', 'reuse_status', 'category', 'import_status',
        ];
        $reuseAllowed = [
            'GREEN', 'AMBER_PERMISSION_REQUIRED', 'YELLOW_SPECIAL_LICENCE',
            'UNKNOWN_LICENCE', 'SKIP', 'PERMISSION_GRANTED',
        ];
        $ids = [];
        foreach ($data['sources'] as $source) {
            self::assertIsArray($source);
            foreach ($required as $field) {
                self::assertArrayHasKey($field, $source, "Missing {$field} on " . ($source['source_id'] ?? '?'));
                self::assertNotSame('', trim((string) $source[$field]));
            }
            self::assertContains($source['reuse_status'], $reuseAllowed);
            $id = (string) $source['source_id'];
            self::assertArrayNotHasKey($id, $ids, "Duplicate source_id {$id}");
            $ids[$id] = true;
            if (!empty($source['local_path']) && !empty($source['sha256'])) {
                self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $source['sha256']);
            }
        }

        self::assertArrayHasKey('au_national_public_toilet_map', $ids);
        self::assertArrayHasKey('qld_roadside_amenities', $ids);
        self::assertArrayHasKey('nsw_rest_areas', $ids);
        self::assertArrayHasKey('cpaq_explore_qld_2026', $ids);
        self::assertArrayHasKey('portal_osm_australia', $ids);

        foreach ($data['sources'] as $source) {
            if ($source['source_id'] === 'portal_osm_australia') {
                self::assertSame('YELLOW_SPECIAL_LICENCE', $source['reuse_status']);
            }
            if ($source['source_id'] === 'au_national_formal_rest_areas') {
                self::assertSame('UNKNOWN_LICENCE', $source['reuse_status']);
                self::assertSame('archived_not_imported', $source['import_status']);
            }
            if ($source['source_id'] === 'cpaq_explore_qld_2026') {
                self::assertSame('PERMISSION_GRANTED', $source['reuse_status']);
            }
        }
    }

    public function testHumanRegisterDocumentExists(): void
    {
        $path = dirname(__DIR__, 2) . '/docs/data/VANASSIST_DATA_SOURCE_REGISTER.md';
        self::assertFileExists($path);
        $body = (string) file_get_contents($path);
        self::assertStringContainsString('VanAssist data source register', $body);
        self::assertStringContainsString('Permission queue', $body);
        self::assertStringContainsString('au_national_public_toilet_map', $body);
    }
}
