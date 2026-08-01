<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Routing\SearchRouter;
use PHPUnit\Framework\TestCase;

final class SearchRouterTest extends TestCase
{
    private SearchRouter $router;

    protected function setUp(): void
    {
        $this->router = new SearchRouter();
    }

    public function testOnlyExecutableAdaptersAreReturned(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_MIXED,
            'provider_category_keys' => ['dump-points'],
            'stay_type_keys' => ['caravan_park'],
            'facility_type_keys' => [],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['providers', 'stays', 'traveller_facilities', 'datasets'],
            'confidence' => 0.8,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);

        self::assertSame(['providers', 'stays'], $this->router->adaptersFor($intent));
    }

    public function testProviderCategoriesFallbackWhenAdapterKeysEmpty(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['tyres-and-wheels'],
            'stay_type_keys' => [],
            'facility_type_keys' => [],
            'location_text' => 'Emerald',
            'use_current_location' => false,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => [],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);

        self::assertSame(['providers'], $this->router->adaptersFor($intent));
    }

    public function testStayTypesFallbackWhenAdapterKeysEmpty(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_STAY,
            'provider_category_keys' => [],
            'stay_type_keys' => ['caravan_park'],
            'facility_type_keys' => [],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => [],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);

        self::assertSame(['stays'], $this->router->adaptersFor($intent));
    }

    public function testNonExecutableOnlyIntentReturnsEmpty(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => [],
            'stay_type_keys' => [],
            'facility_type_keys' => ['public_toilet'],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['traveller_facilities'],
            'confidence' => 0.85,
            'clarification_required' => true,
            'clarification_reason' => 'Not yet available.',
        ]);

        self::assertSame([], $this->router->adaptersFor($intent));
    }
}
