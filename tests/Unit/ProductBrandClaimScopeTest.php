<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductBrandClaimScopeTest extends TestCase
{
    public function testClaimTokensAreIssuedResolvedAndConsumedInsideCurrentBrand(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/ProviderClaimService.php'));

        self::assertStringContainsString('provider_id, brand_id, email', $service);
        self::assertStringContainsString('t.token_hash = ? AND t.brand_id = ?', $service);
        self::assertStringContainsString('WHERE id = ? AND brand_id = ?', $service);
        self::assertStringContainsString('pct.provider_id = p.id AND pct.brand_id = ?', $service);
    }

    public function testAdminClaimApiFiltersInvitesBySelectedBrand(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/Api/AdminApiClaimService.php'));

        self::assertStringContainsString("\$where = ['t.brand_id = ?']", $service);
        self::assertStringContainsString('[$id, AdminApiBrandScope::brandId()]', $service);
    }

    public function testMigrationBackfillsLegacyTokensBeforeRequiringBrand(): void
    {
        $migration = (string) file_get_contents(base_path('database/migrations/137_brand_scope_provider_claim_tokens.sql'));

        self::assertStringContainsString('used_at IS NULL THEN LEAST(expires_at, NOW())', $migration);
        self::assertStringContainsString('brand_id = 1', $migration);
        self::assertStringContainsString('MODIFY brand_id INT UNSIGNED NOT NULL', $migration);
    }
}
