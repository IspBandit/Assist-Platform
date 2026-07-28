<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MotorsportCatalogue;
use App\Services\MotorsportSourceMonitor;
use PHPUnit\Framework\TestCase;

final class MotorsportCatalogueTest extends TestCase
{
    public function testEveryDisciplineIsUniqueAndResolvesToOneFamily(): void
    {
        $disciplines = MotorsportCatalogue::disciplines();
        self::assertGreaterThanOrEqual(55, count($disciplines));
        self::assertCount(count(array_unique(array_keys($disciplines))), $disciplines);
        foreach ($disciplines as $key => $name) {
            self::assertNotSame('', $name);
            self::assertNotSame('', MotorsportCatalogue::familyFor($key), $key);
        }
        self::assertSame('', MotorsportCatalogue::familyFor('not-a-real-discipline'));
    }

    public function testRequiredFamiliesAndSpecialistDisciplinesRemainExplicit(): void
    {
        self::assertSame(['circuit','rally-road','off-road','speed-drift','auto-test','drag','speedway','karting','motorcycle'], array_keys(MotorsportCatalogue::FAMILIES));
        $disciplines = MotorsportCatalogue::disciplines();
        foreach (['circuit-racing','rally','off-road-racing','drift','motorkhana','drag-racing-motorcycles','sprintcars','sprint-karting','motocross','motorcycle-trial','electric-motorcycle'] as $key) {
            self::assertArrayHasKey($key, $disciplines);
        }
    }

    public function testEveryJurisdictionAndRuleLayerIsNamed(): void
    {
        self::assertSame(['AUS','ACT','NSW','VIC','QLD','SA','WA','TAS','NT'], array_keys(MotorsportCatalogue::JURISDICTIONS));
        self::assertSame(['competition','technical','safety','licensing','state','event'], array_keys(MotorsportCatalogue::RULE_TYPES));
    }

    public function testSourceChangeClassificationFailsClosed(): void
    {
        self::assertSame('baseline', MotorsportSourceMonitor::classify('', hash('sha256', 'first')));
        self::assertSame('unchanged', MotorsportSourceMonitor::classify(hash('sha256', 'same'), hash('sha256', 'same')));
        self::assertSame('changed', MotorsportSourceMonitor::classify(hash('sha256', 'old'), hash('sha256', 'new')));
    }
}
