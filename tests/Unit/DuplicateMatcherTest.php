<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\DataSources\DuplicateMatcher;
use PHPUnit\Framework\TestCase;

final class DuplicateMatcherTest extends TestCase
{
    public function testSimilarNameAndExactPhoneProduceSeventyPointMatch(): void
    {
        $match = (new DuplicateMatcher())->score(
            ['business_name' => 'Outback Caravan Repairs', 'phone' => '07 4000 1234'],
            ['business_name' => 'Outback Caravan Repair', 'phone' => '0740001234']
        );

        self::assertSame(70, $match['score']);
        self::assertSame(['similar business name', 'same phone'], $match['reasons']);
    }

    public function testNameSimilarityAloneDoesNotReachMergeThreshold(): void
    {
        $match = (new DuplicateMatcher())->score(
            ['business_name' => 'Outback Caravan Repairs'],
            ['business_name' => 'Outback Caravan Repair']
        );

        self::assertSame(35, $match['score']);
    }

    public function testWebsiteMatchIgnoresConventionalWwwPrefix(): void
    {
        $match = (new DuplicateMatcher())->score(
            ['business_name' => 'Regional RV Service', 'website' => 'https://www.regional-rv.test/services'],
            ['business_name' => 'Regional RV Services', 'website' => 'https://regional-rv.test']
        );

        self::assertSame(70, $match['score']);
        self::assertContains('same website', $match['reasons']);
    }
}
