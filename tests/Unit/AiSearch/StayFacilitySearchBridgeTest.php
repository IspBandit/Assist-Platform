<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\StayFacilitySearchBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StayFacilitySearchBridgeTest extends TestCase
{
    /** @return iterable<string,array{array<string,string>,bool}> */
    public static function availabilityCases(): iterable
    {
        yield 'dump point yes' => [['facility_type' => 'dump_point', 'facility_status' => 'yes'], true];
        yield 'conditional facility' => [['facility_type' => 'dump_point', 'facility_status' => 'conditional'], true];
        yield 'confirmed absent' => [['facility_type' => 'dump_point', 'facility_status' => 'no'], false];
        yield 'unknown' => [['facility_type' => 'dump_point', 'facility_status' => 'unknown'], false];
        yield 'untreated water remains useful' => [[
            'facility_type' => 'water', 'facility_status' => 'conditional', 'facility_value' => 'untreated',
        ], true];
        yield 'vague water claim is excluded' => [[
            'facility_type' => 'water', 'facility_status' => 'yes', 'facility_value' => '',
        ], false];
    }

    #[DataProvider('availabilityCases')]
    public function testOnlyUsefulAvailableEvidenceIsExposed(array $claim, bool $expected): void
    {
        self::assertSame($expected, StayFacilitySearchBridge::isPubliclyAvailable($claim));
    }
}
