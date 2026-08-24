<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Config;
use App\Core\Database;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Services\EmailQueue;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Queues one privacy-safe, plain-English VanAssist website report each day. */
final class VanAssistDailyPerformanceReport
{
    private const BRAND_ID = 1;
    private const RECIPIENT = 'support@vanassist.com.au';

    /** @return array<string,mixed> */
    public function queuePreviousDay(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone((string) Config::get('app.timezone', 'Australia/Brisbane'));
        $now = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
        $reportDate = $now->modify('-1 day')->format('Y-m-d');
        $templateKey = 'vanassist_daily_performance_' . str_replace('-', '', $reportDate);

        $existing = Database::selectOne(
            'SELECT id,status FROM email_queue WHERE brand_id=? AND template_key=? ORDER BY id DESC LIMIT 1',
            [self::BRAND_ID, $templateKey]
        );
        if ($existing !== null) {
            return [
                'status' => 'already_queued',
                'report_date' => $reportDate,
                'queue_id' => (int) $existing['id'],
                'queue_status' => (string) $existing['status'],
            ];
        }

        $report = WebsiteInsightsService::report(self::BRAND_ID, $reportDate, $reportDate);
        $previousDate = $now->modify('-2 days')->format('Y-m-d');
        $report['comparison_summary'] = WebsiteInsightsService::report(self::BRAND_ID, $previousDate, $previousDate)['summary'] ?? [];
        $message = self::render($reportDate, $report);
        $brand = self::vanAssistBrand();
        $prior = BrandContext::hasCurrent() ? BrandContext::current() : null;

        try {
            BrandContext::set($brand);
            $queueId = EmailQueue::queueRawId(
                self::RECIPIENT,
                'VanAssist support',
                $message['subject'],
                $message['html'],
                $message['text'],
                $templateKey
            );
        } finally {
            if ($prior !== null) {
                BrandContext::set($prior);
            } else {
                BrandContext::clear();
            }
        }

        if ($queueId === null) {
            throw new RuntimeException('VanAssist daily performance email could not be queued');
        }

        return ['status' => 'queued', 'report_date' => $reportDate, 'queue_id' => $queueId];
    }

    /**
     * @param array<string,mixed> $report
     * @return array{subject:string,html:string,text:string}
     */
    public static function render(string $reportDate, array $report): array
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $visitors = (int) ($summary['visitors'] ?? 0);
        $views = (int) ($summary['page_views'] ?? 0);
        $searches = (int) ($summary['searches'] ?? 0);
        $noResults = (int) ($summary['no_results'] ?? 0);
        $contacts = (int) ($summary['contact_actions'] ?? 0);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $reportDate);
        if ($date === false) {
            throw new RuntimeException('Daily performance report date must use YYYY-MM-DD');
        }
        $displayDate = $date->format('j M Y');
        $pagesPerVisit = $summary['pages_per_visitor'] ?? null;
        $successRate = $summary['search_success_rate'] ?? null;

        $plainEnglish = match (true) {
            $visitors === 0 => 'No public visitor activity was recorded. If this is unexpected, check the analytics task and live tracking health.',
            $visitors < 10 => 'Traffic was light. The detail below is useful, but avoid drawing strong conclusions from a small audience.',
            $searches > 0 && $noResults > 0 => 'People used VanAssist search, but some searches returned no result. Review the service and location gaps below.',
            $contacts > 0 => 'Visitors found providers and took contact actions. The provider-interest section shows where that attention went.',
            default => 'Visitors used VanAssist, but no provider contact action was recorded for the day.',
        };

        $metrics = [
            'Approximate visits' => $visitors,
            'New visitors' => (int) ($summary['new_visitors'] ?? 0),
            'Returning visitors' => (int) ($summary['returning_visitors'] ?? 0),
            'Visitors active on multiple days' => (int) ($summary['multi_day_visitors'] ?? 0),
            'Pages opened' => $views,
            'Pages per visit' => $pagesPerVisit === null ? '—' : number_format((float) $pagesPerVisit, 1),
            'Provider searches' => $searches,
            'Searches with no result' => $noResults,
            'Exact category misses' => (int) ($summary['exact_misses'] ?? 0),
            'Searches rescued with alternatives' => (int) ($summary['rescued_searches'] ?? 0),
            'Search success rate' => $successRate === null ? '—' : number_format((float) $successRate, 1) . '%',
            'Ask VanAssist searches' => (int) ($summary['ask_searches'] ?? 0),
            'Ask searches with no result' => (int) ($summary['ask_no_results'] ?? 0),
            'Stay searches' => (int) ($summary['stay_searches'] ?? 0),
            'Stay searches with no result' => (int) ($summary['stay_no_results'] ?? 0),
            'Provider profiles opened' => (int) ($summary['profile_views'] ?? 0),
            'Contact actions' => $contacts,
            'Confirmed provider uses' => (int) ($summary['confirmed_uses'] ?? 0),
        ];

        $comparison = is_array($report['comparison_summary'] ?? null) ? $report['comparison_summary'] : [];
        $comparisonText = self::comparisonText($summary, $comparison);

        $sections = [
            ['Most popular pages', $report['pages'] ?? [], 'Page', 'Views', 'Visits'],
            ['Where visitors came from', $report['sources'] ?? [], 'Source', 'Visits', 'Views'],
            ['Devices used', $report['devices'] ?? [], 'Device', 'Visits', 'Views'],
            ['Services people searched for', $report['services'] ?? [], 'Service', 'Searches', 'No result'],
            ['Locations people searched', $report['locations'] ?? [], 'Location', 'Searches', 'No result'],
            ['Search gaps needing coverage', self::coverageGapRows($report['coverage_gaps'] ?? []), 'Service and location', 'Exact misses', 'Rescued'],
            ['What visitors clicked', $report['actions'] ?? [], 'Action', 'Actions', 'Visitors'],
            ['Providers attracting interest', self::providerRows($report['providers'] ?? []), 'Provider', 'Contacts', 'Profile views'],
        ];

        $metricHtml = '';
        $metricText = '';
        foreach ($metrics as $label => $value) {
            $metricHtml .= '<tr><th align="left" style="padding:7px 12px;border-bottom:1px solid #e5e7eb">' . self::escape($label) . '</th>'
                . '<td align="right" style="padding:7px 12px;border-bottom:1px solid #e5e7eb">' . self::escape((string) $value) . '</td></tr>';
            $metricText .= $label . ': ' . $value . "\n";
        }

        $detailHtml = '';
        $detailText = '';
        foreach ($sections as [$heading, $rows, $labelHeading, $totalHeading, $secondaryHeading]) {
            if (!is_array($rows) || $rows === []) {
                continue;
            }
            $rows = array_slice($rows, 0, 10);
            $detailHtml .= '<h2 style="font-size:18px;margin:24px 0 8px">' . self::escape($heading) . '</h2>'
                . self::table($rows, $labelHeading, $totalHeading, $secondaryHeading);
            $detailText .= "\n" . $heading . "\n" . str_repeat('-', strlen($heading)) . "\n";
            foreach ($rows as $row) {
                $detailText .= (string) ($row['label'] ?? 'Unknown') . ': ' . (int) ($row['total'] ?? 0)
                    . ' / ' . (int) ($row['secondary'] ?? 0) . "\n";
            }
        }

        $subject = 'VanAssist daily website performance — ' . $displayDate;
        $privacy = 'These are aggregate, first-party VanAssist figures. Staff, known bots, synthetic checks and same-brand requests that did not retain the session cookie are excluded; visits are approximate.';
        $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#17202a;line-height:1.45;margin:0;padding:20px">'
            . '<main style="max-width:680px;margin:0 auto"><h1 style="font-size:24px;margin:0 0 6px">VanAssist daily website performance</h1>'
            . '<p style="color:#52606d;margin:0 0 20px">' . self::escape($displayDate) . '</p>'
            . '<div style="background:#eef8f7;border-left:4px solid #0f6e6e;padding:12px 14px;margin-bottom:18px"><strong>In plain English</strong><br>'
            . self::escape($plainEnglish) . '<br>' . self::escape($comparisonText) . '</div><table role="presentation" style="width:100%;border-collapse:collapse">' . $metricHtml . '</table>'
            . $detailHtml . '<p style="font-size:12px;color:#64748b;margin-top:28px">' . self::escape($privacy) . '</p></main></body></html>';
        $text = "VanAssist daily website performance\n{$displayDate}\n\nIn plain English\n{$plainEnglish}\n{$comparisonText}\n\n{$metricText}{$detailText}\n{$privacy}\n";

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $previous */
    private static function comparisonText(array $current, array $previous): string
    {
        if ($previous === []) {
            return 'No prior-day comparison was available.';
        }
        $parts = [];
        foreach (['visitors' => 'visits', 'page_views' => 'page views', 'searches' => 'provider searches', 'contact_actions' => 'contact actions'] as $key => $label) {
            $now = (int) ($current[$key] ?? 0);
            $before = (int) ($previous[$key] ?? 0);
            if ($before === 0) {
                $parts[] = $label . ': ' . $now . ' (prior day 0)';
                continue;
            }
            $change = round((($now - $before) / $before) * 100, 1);
            $parts[] = $label . ': ' . ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
        }
        return 'Compared with the prior day — ' . implode('; ', $parts) . '.';
    }

    /** @param mixed $rows @return array<int,array{label:string,total:int,secondary:int}> */
    private static function providerRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        return array_map(static fn (array $row): array => [
            'label' => (string) ($row['label'] ?? 'Unknown provider'),
            'total' => (int) ($row['contacts'] ?? 0),
            'secondary' => (int) ($row['profile_views'] ?? 0),
        ], $rows);
    }

    /** @param mixed $rows @return array<int,array{label:string,total:int,secondary:int}> */
    private static function coverageGapRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        return array_map(static function (array $row): array {
            $location = trim((string) ($row['location_name'] ?? 'Location not supplied'));
            $state = trim((string) ($row['state_abbr'] ?? ''));
            if ($state !== '') {
                $location .= ', ' . $state;
            }
            return [
                'label' => (string) ($row['service_name'] ?? 'Any service') . ' — ' . $location,
                'total' => (int) ($row['searches'] ?? 0),
                'secondary' => (int) ($row['rescued_searches'] ?? 0),
            ];
        }, $rows);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function table(array $rows, string $labelHeading, string $totalHeading, string $secondaryHeading): string
    {
        $html = '<table role="presentation" style="width:100%;border-collapse:collapse"><tr>'
            . '<th align="left" style="padding:7px 8px;border-bottom:2px solid #cbd5e1">' . self::escape($labelHeading) . '</th>'
            . '<th align="right" style="padding:7px 8px;border-bottom:2px solid #cbd5e1">' . self::escape($totalHeading) . '</th>'
            . '<th align="right" style="padding:7px 8px;border-bottom:2px solid #cbd5e1">' . self::escape($secondaryHeading) . '</th></tr>';
        foreach ($rows as $row) {
            $html .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . self::escape((string) ($row['label'] ?? 'Unknown')) . '</td>'
                . '<td align="right" style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . (int) ($row['total'] ?? 0) . '</td>'
                . '<td align="right" style="padding:6px 8px;border-bottom:1px solid #e5e7eb">' . (int) ($row['secondary'] ?? 0) . '</td></tr>';
        }
        return $html . '</table>';
    }

    private static function vanAssistBrand(): Brand
    {
        $config = Config::get('brands.registry.vanassist');
        if (!is_array($config)) {
            throw new RuntimeException('VanAssist brand configuration is unavailable');
        }
        $brand = Brand::fromArray('vanassist', $config);
        if ($brand->databaseId() !== self::BRAND_ID) {
            throw new RuntimeException('VanAssist database brand ID does not match the report scope');
        }
        return $brand;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
