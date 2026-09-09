<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class NationalQuestionMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, [TravellerFacilitiesFeature::FLAG => true]);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
        parent::tearDown();
    }

    /**
     * @return array<string,array{string,string,string,string,string}>
     */
    public static function questions(): array
    {
        $locations = [
            'Sydney NSW', 'Melbourne VIC', 'Brisbane QLD', 'Perth WA',
            'Adelaide SA', 'Hobart TAS', 'Darwin NT', 'Canberra ACT',
            'Batehaven NSW', 'Dubbo NSW', 'Broken Hill NSW',
            'Ballarat VIC', 'Mildura VIC', 'Mallacoota VIC',
            'Emerald QLD', 'Roma QLD', 'Longreach QLD', 'Birdsville QLD', 'Cairns QLD',
            'Port Augusta SA', 'Coober Pedy SA', 'Mount Gambier SA',
            'Albany WA', 'Broome WA', 'Kununurra WA', 'Kalgoorlie WA',
            'Launceston TAS', 'Strahan TAS', 'Queenstown TAS',
            'Alice Springs NT', 'Katherine NT', 'Tennant Creek NT',
        ];
        $templates = [
            'repair' => ['mobile caravan repair near %s', Intent::TYPE_PROVIDER, 'provider', 'general-caravan-repairs'],
            'toilet' => ['public toilet near %s', Intent::TYPE_FACILITY, 'facility', 'public_toilet'],
            'stay' => ['free camp near %s', Intent::TYPE_STAY, 'stay', 'free_camp'],
            'lpg' => ['LPG refill within 50 km of %s', Intent::TYPE_PROVIDER, 'provider', 'lpg-refills-and-bottle-exchange'],
        ];

        $cases = [];
        foreach ($locations as $location) {
            foreach ($templates as $name => [$template, $type, $group, $key]) {
                $cases[$location . ' ' . $name] = [sprintf($template, $location), $type, $group, $key, $location];
            }
        }
        return $cases;
    }

    #[DataProvider('questions')]
    public function testNationalQuestionIntentAndQualifiedLocation(
        string $question,
        string $expectedType,
        string $group,
        string $expectedKey,
        string $expectedLocation,
    ): void {
        $intent = (new IntentRuleEngine())->interpret($question);

        self::assertSame($expectedType, $intent->intentType, $question);
        self::assertNotNull($intent->locationText, $question);
        self::assertMatchesRegularExpression(
            '/^(.+)\s+(NSW|VIC|QLD|WA|SA|TAS|NT|ACT)$/i',
            $expectedLocation,
            'Matrix location must be state-qualified'
        );
        preg_match('/^(.+)\s+(NSW|VIC|QLD|WA|SA|TAS|NT|ACT)$/i', $expectedLocation, $parts);
        self::assertStringContainsStringIgnoringCase($parts[1], (string) $intent->locationText, $question);
        self::assertMatchesRegularExpression(
            '/\b' . preg_quote($parts[2], '/') . '\b/i',
            (string) $intent->locationText,
            $question
        );
        $keys = match ($group) {
            'provider' => $intent->providerCategoryKeys,
            'facility' => $intent->facilityTypeKeys,
            'stay' => $intent->stayTypeKeys,
        };
        self::assertContains($expectedKey, $keys, $question);
        if (str_contains($question, '50 km')) {
            self::assertSame(50, $intent->radiusKm, $question);
        }
    }
}
