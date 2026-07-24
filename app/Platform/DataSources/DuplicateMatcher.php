<?php
declare(strict_types=1);

namespace App\Platform\DataSources;

final class DuplicateMatcher
{
    /** @return array{score:int,reasons:array<int,string>} */
    public function score(array $candidate, array $provider): array
    {
        $score = 0; $reasons = [];
        $normal = static fn (string $v): string => preg_replace('/[^a-z0-9]+/', '', strtolower($v)) ?? '';
        $nameA = $normal((string) ($candidate['business_name'] ?? ''));
        $nameB = $normal((string) ($provider['business_name'] ?? ''));
        if ($nameA !== '' && $nameA === $nameB) { $score += 55; $reasons[] = 'same normalised name'; }
        elseif ($nameA !== '' && $nameB !== '') {
            similar_text($nameA, $nameB, $pct);
            if ($pct >= 85) { $score += 35; $reasons[] = 'similar business name'; }
        }
        $phoneA = $normal((string) ($candidate['phone'] ?? ''));
        $phoneB = $normal((string) ($provider['phone'] ?? ''));
        if ($phoneA !== '' && $phoneA === $phoneB) { $score += 35; $reasons[] = 'same phone'; }
        $host = static function (string $url): string { return strtolower((string) parse_url($url, PHP_URL_HOST)); };
        if ($host((string) ($candidate['website'] ?? '')) !== '' && $host((string) ($candidate['website'] ?? '')) === $host((string) ($provider['website'] ?? ''))) { $score += 35; $reasons[] = 'same website'; }
        return ['score' => min(100, $score), 'reasons' => $reasons];
    }
}
