<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TravellerQuestionMatrixTest extends TestCase
{
    /** @return array<string,array{string,string,string,string}> */
    public static function commonQuestions(): array
    {
        return [
            'free stay' => ['need somewhere to stay free near Emerald', Intent::TYPE_STAY, 'stay', 'free_camp'],
            'stay tonight' => ['where can I stay tonight in Roma', Intent::TYPE_STAY, 'stay', ''],
            'powered site' => ['need a powered site near Gympie', Intent::TYPE_STAY, 'stay', 'caravan_park'],
            'cheap camping' => ['cheap camp around Dubbo', Intent::TYPE_STAY, 'stay', 'free_camp'],
            'showground' => ['showground camping near Longreach', Intent::TYPE_STAY, 'stay', 'showground'],
            'station stay' => ['station stay around Winton', Intent::TYPE_STAY, 'stay', 'station_stay'],
            'national park' => ['national park camping near Cairns', Intent::TYPE_STAY, 'stay', 'national_park'],
            'public toilet casual' => ['where is the nearest bathroom', Intent::TYPE_FACILITY, 'facility', 'public_toilet'],
            'dunny' => ['is there a dunny near Batehaven', Intent::TYPE_FACILITY, 'facility', 'public_toilet'],
            'water tanks' => ['where can I fill my water tanks in Emerald', Intent::TYPE_FACILITY, 'facility', 'drinking_water'],
            'shower' => ['where can I shower near Roma', Intent::TYPE_FACILITY, 'facility', 'public_shower'],
            'laundry' => ['laundromat near me', Intent::TYPE_FACILITY, 'facility', 'laundry'],
            'visitor centre' => ['visitor centre in Charleville', Intent::TYPE_FACILITY, 'facility', 'visitor_information'],
            'doctor' => ['need a doctor in Roma', Intent::TYPE_FACILITY, 'facility', 'medical_centre'],
            'chemist' => ['chemist near me', Intent::TYPE_FACILITY, 'facility', 'pharmacy'],
            'boat ramp' => ['boat ramp around Gladstone', Intent::TYPE_FACILITY, 'facility', 'boat_ramp'],
            'sparky' => ['need a caravan sparky near Mackay', Intent::TYPE_PROVIDER, 'provider', 'auto-electrical-and-batteries'],
            'no power' => ['there is no power in caravan near Rockhampton', Intent::TYPE_PROVIDER, 'provider', 'auto-electrical-and-batteries'],
            'flat tyre' => ['flat tyre on my van near Gympie', Intent::TYPE_PROVIDER, 'provider', 'tyres-and-wheels'],
            'brakes' => ['my caravan brakes are squealing near Emerald', Intent::TYPE_PROVIDER, 'provider', 'brakes-and-bearings'],
            'hot hub' => ['hot wheel hub on caravan near Roma', Intent::TYPE_PROVIDER, 'provider', 'brakes-and-bearings'],
            'warm fridge' => ['caravan fridge is warm in Bundaberg', Intent::TYPE_PROVIDER, 'provider', 'refrigeration'],
            'aircon' => ['air con is not blowing cold near Cairns', Intent::TYPE_PROVIDER, 'provider', 'air-conditioning'],
            'gas smell' => ['I smell gas in my caravan near Dubbo', Intent::TYPE_PROVIDER, 'provider', 'gas-appliance-servicing'],
            'water leak' => ['water leaking under the sink in my caravan near Roma', Intent::TYPE_PROVIDER, 'provider', 'plumbing-and-water-leaks'],
            'awning' => ['my awning will not close near Mackay', Intent::TYPE_PROVIDER, 'provider', 'awning-repairs'],
            'roof leak' => ['rain coming through caravan roof near Gladstone', Intent::TYPE_PROVIDER, 'provider', 'roof-leaks'],
            'bearing' => ['bad wheel bearing on my caravan near Gympie', Intent::TYPE_PROVIDER, 'provider', 'brakes-and-bearings'],
            'towing' => ['need a tow truck near Emerald', Intent::TYPE_PROVIDER, 'provider', 'towing-and-vehicle-recovery'],
            'locked out' => ['locked out of my caravan in Roma', Intent::TYPE_PROVIDER, 'provider', 'locksmith-and-security'],
        ];
    }

    #[DataProvider('commonQuestions')]
    public function testCommonTravellerWordingRoutesReliably(
        string $question,
        string $expectedType,
        string $keyGroup,
        string $expectedKey,
    ): void {
        $intent = (new IntentRuleEngine())->interpret($question);

        self::assertSame($expectedType, $intent->intentType, $question);
        if ($expectedType !== Intent::TYPE_FACILITY) {
            self::assertFalse($intent->clarificationRequired, $question);
        }
        self::assertGreaterThanOrEqual(0.55, $intent->confidence, $question);

        $keys = match ($keyGroup) {
            'provider' => $intent->providerCategoryKeys,
            'stay' => $intent->stayTypeKeys,
            'facility' => $intent->facilityTypeKeys,
        };
        if ($expectedKey !== '') {
            self::assertContains($expectedKey, $keys, $question);
        }
    }
}
