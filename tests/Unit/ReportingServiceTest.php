<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Demand\ReportingService;
use PHPUnit\Framework\TestCase;

final class ReportingServiceTest extends TestCase
{
    public function testRateSuppressesLowSamples(): void
    {
        // Fewer than 5 in the denominator → hidden (null), per the spec's
        // "do not display misleading percentages where the sample is too low".
        $this->assertNull(ReportingService::rate(1, 4));
        $this->assertNull(ReportingService::rate(0, 0));
    }

    public function testRateComputesPercentage(): void
    {
        $this->assertSame(30.0, ReportingService::rate(3, 10));
        $this->assertSame(100.0, ReportingService::rate(5, 5));
        $this->assertSame(0.0, ReportingService::rate(0, 10));
    }

    public function testFinancialYearBoundaries(): void
    {
        [$start, $end] = ReportingService::financialYear(0);
        $this->assertStringEndsWith('-07-01', $start, 'AU FY starts 1 July');
        $this->assertStringEndsWith('-06-30', $end, 'AU FY ends 30 June');
        $this->assertSame((int) substr($start, 0, 4) + 1, (int) substr($end, 0, 4));
    }

    public function testPreviousFinancialYearIsOneYearEarlier(): void
    {
        [$curStart] = ReportingService::financialYear(0);
        [$prevStart] = ReportingService::financialYear(-1);
        $this->assertSame((int) substr($curStart, 0, 4) - 1, (int) substr($prevStart, 0, 4));
    }

    public function testResolveRangeSevenDaysSpansSixDays(): void
    {
        [$from, $to, $label] = ReportingService::resolveRange('7d');
        $diff = (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;
        $this->assertSame(6, $diff);
        $this->assertSame('Last 7 days', $label);
    }

    public function testResolveRangeDefaultsToThirtyDays(): void
    {
        [, , $label] = ReportingService::resolveRange('not-a-real-range');
        $this->assertSame('Last 30 days', $label);
    }

    public function testResolveRangeCustomUsesSuppliedDates(): void
    {
        [$from, $to] = ReportingService::resolveRange('custom', '2026-01-01', '2026-01-31');
        $this->assertSame('2026-01-01', $from);
        $this->assertSame('2026-01-31', $to);
    }

    public function testResolveRangeCustomRejectsInvalidDates(): void
    {
        // Garbage dates must not propagate into SQL — they fall back to a window.
        [$from, $to] = ReportingService::resolveRange('custom', 'nope', 'also-nope');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $from);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $to);
    }
}
