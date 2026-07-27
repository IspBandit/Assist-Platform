<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RegulatorySourceMonitor;
use PHPUnit\Framework\TestCase;

final class RegulatorySourceMonitorTest extends TestCase
{
    public function testFirstObservationCreatesBaseline(): void
    {
        self::assertSame('baseline', RegulatorySourceMonitor::classify('', hash('sha256', 'official document')));
    }

    public function testUnchangedOfficialBytesRemainCurrent(): void
    {
        $hash = hash('sha256', 'official document');
        self::assertSame('unchanged', RegulatorySourceMonitor::classify($hash, $hash));
    }

    public function testChangedOfficialBytesRequireReview(): void
    {
        self::assertSame('changed', RegulatorySourceMonitor::classify(
            hash('sha256', 'version one'),
            hash('sha256', 'version two')
        ));
    }
}
