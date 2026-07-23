<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DirectoryPresentation;
use PHPUnit\Framework\TestCase;

final class DirectoryPresentationTest extends TestCase
{
    public function testEachPublicBrandHasPurposeSpecificDirectoryCopy(): void
    {
        $vanAssist = DirectoryPresentation::copyFor('vanassist');
        $towSmart = DirectoryPresentation::copyFor('towsmart');
        $trailerWise = DirectoryPresentation::copyFor('trailerwise');

        self::assertStringContainsString('caravan', strtolower($vanAssist['heading']));
        self::assertStringContainsString('towing', strtolower($towSmart['eyebrow']));
        self::assertStringContainsString('trailer', strtolower($trailerWise['heading']));
        self::assertNotSame($vanAssist['intro'], $towSmart['intro']);
        self::assertNotSame($towSmart['intro'], $trailerWise['intro']);
    }

    public function testUnknownBrandUsesSafeGenericAssistCopy(): void
    {
        self::assertSame('Find caravan and RV help', DirectoryPresentation::copyFor('unknown')['heading']);
    }
}
