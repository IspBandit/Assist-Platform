<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Services\Search\LocationDisambiguation;
use PHPUnit\Framework\TestCase;

final class LocationDisambiguationTest extends TestCase
{
    public function testChoicesBuildLabels(): void
    {
        $choices = LocationDisambiguation::choices([
            ['name' => 'Emerald', 'state_abbr' => 'QLD', 'slug' => 'emerald'],
            ['name' => 'Emerald', 'state_abbr' => 'VIC', 'slug' => 'emerald-vic'],
        ]);

        self::assertSame('Emerald, QLD', $choices[0]['label']);
        self::assertSame('Emerald, VIC', $choices[1]['label']);
    }

    public function testRewriteQueryReplacesNearPlace(): void
    {
        self::assertSame(
            'LPG refill near Emerald QLD',
            LocationDisambiguation::rewriteQuery('LPG refill near Emerald', 'Emerald', 'QLD')
        );
        self::assertSame(
            'mobile mechanic near Longreach QLD',
            LocationDisambiguation::rewriteQuery('mobile mechanic near Longreach', 'Longreach', 'QLD')
        );
    }
}
