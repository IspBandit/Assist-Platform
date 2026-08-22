<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\PublicResultWindow;
use PHPUnit\Framework\TestCase;

final class PublicResultWindowTest extends TestCase
{
    public function testDefaultsToTwentyAndOnlyExpandsToForty(): void
    {
        self::assertSame(20, PublicResultWindow::requested(null));
        self::assertSame(20, PublicResultWindow::requested('20'));
        self::assertSame(40, PublicResultWindow::requested('21'));
        self::assertSame(40, PublicResultWindow::requested('500'));
    }

    public function testBoundsTotalRowsInDeclaredGroupOrder(): void
    {
        $groups = [
            'providers' => array_fill(0, 15, ['type' => 'provider']),
            'facilities' => array_fill(0, 12, ['type' => 'facility']),
            'stays' => array_fill(0, 8, ['type' => 'stay']),
        ];

        $result = (new PublicResultWindow())->apply($groups, 20);

        self::assertSame(35, $result['total']);
        self::assertTrue($result['has_more']);
        self::assertCount(15, $result['groups']['providers']);
        self::assertCount(5, $result['groups']['facilities']);
        self::assertCount(0, $result['groups']['stays']);
    }
}
