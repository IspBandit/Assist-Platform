<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\Provider;
use App\Models\Town;
use Throwable;

/**
 * Claim-first provider onboarding: search-before-create, duplicate hold (VAN-010).
 */
final class ClaimFirstOnboardingService
{
    public static function enabled(): bool
    {
        return (bool) Config::get('onboarding.claim_first_enabled', true);
    }

    public static function duplicateHoldThreshold(): int
    {
        return (int) Config::get('onboarding.duplicate_hold_threshold', 70);
    }

    public static function normalizeBusinessName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9\s&]/', '', $name) ?? $name;
        $name = preg_replace('/\b(pty|ltd|limited|pl|co)\b/', '', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    /** @param array<string,mixed> $existing */
    /** @param array<string,mixed> $submission */
    public static function duplicateScore(array $existing, array $submission): int
    {
        $score = 0;
        $nameA = self::normalizeBusinessName((string) ($existing['business_name'] ?? ''));
        $nameB = self::normalizeBusinessName((string) ($submission['business_name'] ?? ''));
        if ($nameA !== '' && $nameB !== '' && $nameA === $nameB) {
            $score += 45;
        } elseif ($nameA !== '' && $nameB !== '' && (str_contains($nameA, $nameB) || str_contains($nameB, $nameA))) {
            $score += 25;
        }

        $phoneA = self::normalizePhone((string) ($existing['phone'] ?? ''));
        $phoneB = self::normalizePhone((string) ($submission['phone'] ?? ''));
        if ($phoneA !== '' && $phoneB !== '' && $phoneA === $phoneB) {
            $score += 35;
        }

        $webA = self::normalizeWebsite((string) ($existing['website'] ?? ''));
        $webB = self::normalizeWebsite((string) ($submission['website'] ?? ''));
        if ($webA !== '' && $webB !== '' && $webA === $webB) {
            $score += 30;
        }

        $townA = (int) ($existing['base_town_id'] ?? 0);
        $townB = (int) ($submission['base_town_id'] ?? 0);
        if ($townA > 0 && $townB > 0 && $townA === $townB && $score > 0) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * @param list<array<string,mixed>> $candidates
     * @param array<string,mixed> $submission
     * @return array{likely:bool,score:int,provider_id:int,reasons:list<string>}|null
     */
    public static function bestDuplicateMatch(array $candidates, array $submission): ?array
    {
        $best = null;
        $threshold = self::duplicateHoldThreshold();

        foreach ($candidates as $candidate) {
            $score = self::duplicateScore($candidate, $submission);
            if ($score < $threshold) {
                continue;
            }

            if ($best === null || $score > $best['score']) {
                $best = [
                    'likely' => true,
                    'score' => $score,
                    'provider_id' => (int) ($candidate['id'] ?? $candidate['id_a'] ?? 0),
                    'reasons' => self::duplicateReasons($candidate, $submission, $score),
                ];
            }
        }

        return $best;
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $submission
     * @return list<string>
     */
    public static function duplicateReasons(array $existing, array $submission, int $score): array
    {
        $reasons = [];
        $nameA = self::normalizeBusinessName((string) ($existing['business_name'] ?? $existing['name_a'] ?? ''));
        $nameB = self::normalizeBusinessName((string) ($submission['business_name'] ?? ''));
        if ($nameA !== '' && $nameB !== '' && ($nameA === $nameB || str_contains($nameA, $nameB) || str_contains($nameB, $nameA))) {
            $reasons[] = 'business_name';
        }

        $phoneA = self::normalizePhone((string) ($existing['phone'] ?? $existing['phone_a'] ?? ''));
        $phoneB = self::normalizePhone((string) ($submission['phone'] ?? ''));
        if ($phoneA !== '' && $phoneB !== '' && $phoneA === $phoneB) {
            $reasons[] = 'phone';
        }

        $webA = self::normalizeWebsite((string) ($existing['website'] ?? ''));
        $webB = self::normalizeWebsite((string) ($submission['website'] ?? ''));
        if ($webA !== '' && $webB !== '' && $webA === $webB) {
            $reasons[] = 'website';
        }

        if ($score >= self::duplicateHoldThreshold() && $reasons === []) {
            $reasons[] = 'location_and_name';
        }

        return $reasons;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function searchMatches(string $businessName, ?int $townId, ?string $townName, int $limit = 8): array
    {
        if (!Database::tableExists('providers')) {
            return [];
        }

        $businessName = trim($businessName);
        if ($businessName === '') {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        $like = '%' . $businessName . '%';
        $where[] = '(p.business_name LIKE ? OR p.business_name LIKE ?)';
        $normalized = self::normalizeBusinessName($businessName);
        $params[] = $like;
        $params[] = $normalized !== '' ? '%' . $normalized . '%' : $like;

        if ($townId !== null && $townId > 0) {
            $where[] = 'p.base_town_id = ?';
            $params[] = $townId;
        } elseif ($townName !== null && trim($townName) !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM towns tw WHERE tw.id = p.base_town_id AND tw.name LIKE ?)';
            $params[] = '%' . trim($townName) . '%';
        }

        $rows = Database::select(
            'SELECT p.id, p.business_name, p.slug, p.phone, p.website, p.base_town_id, p.is_unclaimed, p.status, '
            . 't.name AS town_name, s.abbreviation AS state_abbr '
            . 'FROM providers p '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'ORDER BY p.is_unclaimed DESC, p.business_name ASC LIMIT ' . $limit,
            $params
        );

        if ($rows !== []) {
            return $rows;
        }

        // Fall back to duplicateSuspects filtered in PHP when direct search is empty.
        $suspects = ProviderClaimService::duplicateSuspects(100);
        $out = [];
        foreach ($suspects as $row) {
            foreach (['a', 'b'] as $side) {
                $candidate = [
                    'id' => (int) ($row['id_' . $side] ?? 0),
                    'business_name' => (string) ($row['name_' . $side] ?? ''),
                    'slug' => (string) ($row['slug_' . $side] ?? ''),
                    'phone' => (string) ($row['phone_' . $side] ?? ''),
                    'website' => '',
                    'base_town_id' => null,
                    'is_unclaimed' => (int) ($row['unclaimed_' . $side] ?? 0),
                    'status' => 'active',
                    'town_name' => '',
                    'state_abbr' => '',
                ];
                if ($candidate['id'] < 1) {
                    continue;
                }
                $score = self::duplicateScore($candidate, [
                    'business_name' => $businessName,
                    'base_town_id' => $townId,
                ]);
                if ($score >= 25) {
                    $out[$candidate['id']] = $candidate;
                }
            }
        }

        return array_slice(array_values($out), 0, $limit);
    }

    /**
     * @param array<string,mixed> $submission
     * @return array{outcome:string,duplicate:array<string,mixed>|null,provider_id:int|null}
     */
    public function evaluateSubmission(array $submission, bool $confirmedNone): array
    {
        if (!$confirmedNone) {
            return ['outcome' => 'needs_match_review', 'duplicate' => null, 'provider_id' => null];
        }

        $candidates = $this->loadDuplicateCandidates($submission);
        $duplicate = self::bestDuplicateMatch($candidates, $submission);
        if ($duplicate !== null) {
            return ['outcome' => 'duplicate_hold', 'duplicate' => $duplicate, 'provider_id' => null];
        }

        return ['outcome' => 'prospect', 'duplicate' => null, 'provider_id' => null];
    }

    /**
     * @param array<string,mixed> $submission
     * @return array{provider_id:int,held:bool}
     */
    public function createDuplicateHold(array $submission, array $duplicate): array
    {
        if (!Database::tableExists('providers')) {
            return ['provider_id' => 0, 'held' => false];
        }

        $business = trim((string) ($submission['business_name'] ?? ''));
        if ($business === '') {
            return ['provider_id' => 0, 'held' => false];
        }

        $now = date('Y-m-d H:i:s');
        $townId = isset($submission['base_town_id']) ? (int) $submission['base_town_id'] : null;
        if (($townId === null || $townId < 1) && !empty($submission['town'])) {
            $matches = Town::searchActive((string) $submission['town']);
            $townId = isset($matches[0]['id']) ? (int) $matches[0]['id'] : null;
        }

        $providerId = Provider::create([
            'business_name' => $business,
            'slug' => $this->uniqueSlug($business),
            'contact_name' => trim((string) ($submission['contact_name'] ?? '')) ?: null,
            'email' => trim((string) ($submission['email'] ?? '')) ?: null,
            'phone' => trim((string) ($submission['phone'] ?? '')) ?: null,
            'base_town_id' => $townId,
            'service_model' => (string) ($submission['service_model'] ?? 'unknown'),
            'status' => 'pending',
            'is_unclaimed' => 0,
            'marketing_opt_in' => !empty($submission['marketing_opt_in']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (Database::tableExists('provider_brand_listings')) {
            Database::query(
                'INSERT INTO provider_brand_listings (brand_id, provider_id, slug, display_name, status, search_visible, created_at, updated_at) '
                . "VALUES (?, ?, ?, ?, 'pending', 0, ?, ?)",
                [
                    current_brand()->databaseId(),
                    $providerId,
                    $this->uniqueBrandSlug($business, $providerId),
                    $business,
                    $now,
                    $now,
                ]
            );
        }

        $note = 'Claim-first onboarding duplicate hold (score ' . (int) ($duplicate['score'] ?? 0) . '). '
            . 'Matched provider #' . (int) ($duplicate['provider_id'] ?? 0) . '. '
            . 'Reasons: ' . implode(', ', (array) ($duplicate['reasons'] ?? [])) . '. '
            . 'Listing withheld from publication pending administrator review.';

        if (Database::tableExists('provider_internal_notes')) {
            Database::query(
                'INSERT INTO provider_internal_notes (provider_id, admin_id, note, created_at) VALUES (?, NULL, ?, NOW())',
                [$providerId, $note]
            );
        }

        if (Database::tableExists('listing_corrections')) {
            Database::query(
                'INSERT INTO listing_corrections (entity_type, entity_id, submitter_name, submitter_email, field_name, proposed_value, current_value, status, reason, created_at, updated_at) '
                . "VALUES ('provider', ?, ?, ?, 'duplicate_hold', ?, ?, 'pending', ?, ?, ?)",
                [
                    $providerId,
                    trim((string) ($submission['contact_name'] ?? 'Public submitter')) ?: 'Public submitter',
                    trim((string) ($submission['email'] ?? '')) ?: 'unknown@vanassist.local',
                    'Possible duplicate of provider #' . (int) ($duplicate['provider_id'] ?? 0),
                    'New onboarding submission',
                    'Automated claim-first duplicate hold',
                    $now,
                    $now,
                ]
            );
        }

        return ['provider_id' => $providerId, 'held' => true];
    }

    /** @param array<string,mixed> $submission */
    /** @return list<array<string,mixed>> */
    private function loadDuplicateCandidates(array $submission): array
    {
        $business = trim((string) ($submission['business_name'] ?? ''));
        $townId = isset($submission['base_town_id']) ? (int) $submission['base_town_id'] : null;
        $matches = $this->searchMatches($business, $townId, (string) ($submission['town'] ?? ''), 20);

        $phone = trim((string) ($submission['phone'] ?? ''));
        if ($phone !== '' && Database::tableExists('providers')) {
            $phoneRow = Database::selectOne(
                'SELECT id, business_name, slug, phone, website, base_town_id, is_unclaimed, status FROM providers WHERE deleted_at IS NULL AND phone = ? LIMIT 1',
                [$phone]
            );
            if ($phoneRow !== null) {
                $matches[(int) $phoneRow['id']] = $phoneRow;
            }
        }

        return array_values($matches);
    }

    private function uniqueSlug(string $source): string
    {
        $base = str_slug($source) ?: 'provider';
        $slug = $base;
        $n = 1;
        while ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    private function uniqueBrandSlug(string $source, int $providerId): string
    {
        $brandId = current_brand()->databaseId();
        $base = str_slug($source) ?: 'provider';
        $slug = $base;
        $n = 1;
        while ((int) Database::scalar(
            'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id = ? AND slug = ? AND provider_id != ?',
            [$brandId, $slug, $providerId]
        ) > 0) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    private static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private static function normalizeWebsite(string $website): string
    {
        $website = strtolower(trim($website));
        $website = preg_replace('#^https?://#', '', $website) ?? $website;
        $website = preg_replace('#^www\.#', '', $website) ?? $website;

        return rtrim($website, '/');
    }
}
