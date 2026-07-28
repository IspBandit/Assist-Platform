<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ComplianceGuide;
use PHPUnit\Framework\TestCase;

final class ComplianceGuideTest extends TestCase
{
    public function testSelectionsAreStrictAndMapToOfficialDocumentKinds(): void
    {
        self::assertNull(ComplianceGuide::selections('AUS', 'car', 'modify'));
        self::assertNull(ComplianceGuide::selections('QLD', 'spaceship', 'modify'));
        self::assertSame('modifications', ComplianceGuide::selections('QLD', 'car', 'modify')['kind'] ?? null);
        self::assertSame('modifications', ComplianceGuide::selections('QLD', '4wd', 'modify')['kind'] ?? null);
        self::assertSame('roadworthiness', ComplianceGuide::selections('NSW', 'motorcycle', 'inspect')['kind'] ?? null);
        self::assertSame('towing', ComplianceGuide::selections('VIC', 'trailer', 'travel')['kind'] ?? null);
    }

    public function testEveryGuideEndsWithEvidenceAndSeparatedProviderAdvice(): void
    {
        foreach (array_keys(ComplianceGuide::INTENTIONS) as $intention) {
            $steps = ComplianceGuide::steps($intention);
            self::assertCount(4, $steps);
            self::assertStringContainsString('official source', mb_strtolower($steps[0]['title']));
            self::assertStringContainsString('evidence', mb_strtolower($steps[2]['title']));
            self::assertStringContainsString('separate', mb_strtolower($steps[3]['body']));
        }
        self::assertStringContainsString('not legal', ComplianceGuide::limitation());
    }
}
