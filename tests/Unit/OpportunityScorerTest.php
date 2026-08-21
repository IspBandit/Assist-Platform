<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Platform\DataIntelligence\OpportunityScorer;
use PHPUnit\Framework\TestCase;

final class OpportunityScorerTest extends TestCase
{
    public function testEmptyHighPopulationAreaIsHighPriority(): void
    {
        $score=OpportunityScorer::score(['providers'=>0,'verified'=>0,'population'=>50000,'demand'=>5,'zero_results'=>3]);
        $this->assertGreaterThanOrEqual(80,$score);
        $this->assertSame('critical',OpportunityScorer::priority($score));
    }
    public function testWellSuppliedVerifiedAreaScoresLower(): void
    {
        $score=OpportunityScorer::score(['providers'=>20,'verified'=>20,'population'=>20000,'demand'=>0,'zero_results'=>0]);
        $this->assertLessThan(35,$score);
        $this->assertSame('low',OpportunityScorer::priority($score));
    }
    public function testPopulationIsOptional(): void
    {
        $score=OpportunityScorer::score(['providers'=>1,'verified'=>0,'population'=>null,'demand'=>0,'zero_results'=>0]);
        $this->assertGreaterThan(0,$score);
        $this->assertLessThanOrEqual(100,$score);
    }
}
