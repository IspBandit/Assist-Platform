<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProviderServiceClassificationPolicy;
use PHPUnit\Framework\TestCase;

final class ProviderServiceClassificationPolicyTest extends TestCase
{
    public function testCompoundBusinessNameAcceptsAnySupportedMatchedService(): void
    {
        foreach ([
            ['Singhs Tyre & Petroleum', 'fuel-and-travel-stops'],
            ['Sparko Auto Parts & Tyres', 'tyres-and-wheels'],
            ['Enhance Service Station & Tyre Plus Mechanic', 'fuel-and-travel-stops'],
        ] as [$businessName, $serviceSlug]) {
            self::assertFalse(
                ProviderServiceClassificationPolicy::isUnsupportedSpecialistService($businessName, $serviceSlug),
                $businessName
            );
        }
    }

    public function testUnsupportedServiceForSpecialistNameStillFails(): void
    {
        foreach ([
            ['Emerald Tyrepower', 'general-mechanical-repairs'],
            ['Mundubbera Tyre Service', 'fuel-and-travel-stops'],
            ['KMART TYRE & AUTO ALTONA', 'fuel-and-travel-stops'],
            ['CASTLEMAINE TYRE SERVICE', 'fuel-and-travel-stops'],
        ] as [$businessName, $serviceSlug]) {
            self::assertTrue(
                ProviderServiceClassificationPolicy::isUnsupportedSpecialistService($businessName, $serviceSlug),
                $businessName
            );
        }
    }

    public function testBusinessWithoutSpecialistNameRuleIsNotRejected(): void
    {
        self::assertFalse(ProviderServiceClassificationPolicy::isUnsupportedSpecialistService(
            'Emerald Automotive',
            'general-mechanical-repairs'
        ));
    }
}
