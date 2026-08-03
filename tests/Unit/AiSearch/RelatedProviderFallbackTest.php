<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\SearchOrchestrator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RelatedProviderFallbackTest extends TestCase
{
    public function testSpecialistMissCanRouteToClearlyLabelledRelatedHelp(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['refrigeration'], [], [], 'Emerald', false, 25,
            'normal', ['providers'], 0.9, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'relatedProviderFallbackIntent');
        $fallback = $method->invoke(new SearchOrchestrator(), $intent);

        self::assertInstanceOf(Intent::class, $fallback);
        self::assertSame(['general-caravan-repairs', 'auto-electrical-and-batteries'], $fallback->providerCategoryKeys);
        self::assertSame(50, $fallback->radiusKm);
        self::assertSame('Emerald', $fallback->locationText);
        self::assertSame('related_provider_fallback', $fallback->source);
    }

    public function testBroadSearchDoesNotLoopIntoAnotherFallback(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['general-caravan-repairs'], [], [], 'Roma', false, 25,
            'normal', ['providers'], 0.8, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'relatedProviderFallbackIntent');
        self::assertNull($method->invoke(new SearchOrchestrator(), $intent));
    }
}
