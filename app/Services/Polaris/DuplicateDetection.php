<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Lightweight duplicate detection helpers for manufacturers/models.
 */
final class DuplicateDetection
{
    public static function normalisedName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\b(pty|ltd|limited|australia|co|company)\b/', '', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? $name;
        return $name;
    }

    public static function similarity(string $a, string $b): float
    {
        $left = self::normalisedName($a);
        $right = self::normalisedName($b);
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 100.0;
        }
        similar_text($left, $right, $percent);
        return round((float) $percent, 1);
    }

    /**
     * @param array<int,array{id:int|string,trading_name?:string,legal_name?:string,name?:string}> $existing
     * @return list<array{id:int|string,name:string,score:float}>
     */
    public static function findLikelyDuplicates(string $candidate, array $existing, float $threshold = 80.0): array
    {
        $matches = [];
        foreach ($existing as $row) {
            $name = (string) ($row['trading_name'] ?? $row['legal_name'] ?? $row['name'] ?? '');
            $score = self::similarity($candidate, $name);
            if ($score >= $threshold) {
                $matches[] = ['id' => $row['id'], 'name' => $name, 'score' => $score];
            }
        }
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return $matches;
    }
}
