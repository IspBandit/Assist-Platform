<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Platform\DataIntelligence\MetricSourceInterface;
use App\Platform\DataIntelligence\SourceRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DataIntelligenceSourceRegistryTest extends TestCase
{
    public function testSourcesCanBeAddedWithoutChangingDashboardArchitecture(): void
    {
        $source=new class implements MetricSourceInterface {
            public function key(): string { return 'future_source'; }
            public function coverage(int $brandId,array $filters=[]): array { return [['brand_id'=>$brandId]]; }
        };
        $registry=new SourceRegistry();
        $registry->register($source);
        self::assertSame([['brand_id'=>7]],$registry->get('future_source')->coverage(7));
    }

    public function testUnknownSourceFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        (new SourceRegistry())->get('untrusted_source');
    }
}
