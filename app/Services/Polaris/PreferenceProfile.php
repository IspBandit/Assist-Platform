<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Buyer preference profile for guided matching.
 * Priority levels: essential | strong | nice | avoid | ignore
 *
 * @phpstan-type Priority 'essential'|'strong'|'nice'|'avoid'|'ignore'
 */
final class PreferenceProfile
{
    public const SCORE_VERSION = 'polaris-match-1';

    /**
     * @param array<string,mixed> $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            adults: max(0, (int) ($input['adults'] ?? 2)),
            children: max(0, (int) ($input['children'] ?? 0)),
            maxBudgetAudCents: self::optionalPositiveInt($input['max_budget_aud'] ?? null) !== null
                ? self::optionalPositiveInt($input['max_budget_aud']) * 100
                : self::optionalPositiveInt($input['max_budget_aud_cents'] ?? null),
            minSleeps: self::optionalPositiveInt($input['min_sleeps'] ?? null),
            maxAtmKg: self::optionalPositiveInt($input['max_atm_kg'] ?? null),
            maxLengthM: isset($input['max_length_m']) && is_numeric($input['max_length_m'])
                ? (float) $input['max_length_m']
                : null,
            categories: self::stringList($input['categories'] ?? []),
            requireBathroom: (bool) ($input['require_bathroom'] ?? false),
            offGridNights: max(0, (int) ($input['off_grid_nights'] ?? 0)),
            preferSolarW: self::optionalPositiveInt($input['prefer_solar_w'] ?? null),
            preferFreshWaterL: self::optionalPositiveInt($input['prefer_fresh_water_l'] ?? null),
            priorities: [
                'towability' => self::priority(
                    $input['priority_towability']
                    ?? (is_array($input['priorities'] ?? null) ? ($input['priorities']['towability'] ?? null) : null)
                    ?? 'strong'
                ),
                'price' => self::priority(
                    $input['priority_price']
                    ?? (is_array($input['priorities'] ?? null) ? ($input['priorities']['price'] ?? null) : null)
                    ?? 'strong'
                ),
                'off_grid' => self::priority(
                    $input['priority_off_grid']
                    ?? (is_array($input['priorities'] ?? null) ? ($input['priorities']['off_grid'] ?? null) : null)
                    ?? 'nice'
                ),
                'comfort' => self::priority(
                    $input['priority_comfort']
                    ?? (is_array($input['priorities'] ?? null) ? ($input['priorities']['comfort'] ?? null) : null)
                    ?? 'nice'
                ),
                'payload' => self::priority(
                    $input['priority_payload']
                    ?? (is_array($input['priorities'] ?? null) ? ($input['priorities']['payload'] ?? null) : null)
                    ?? 'strong'
                ),
            ],
        );
    }

    /**
     * @param list<string> $categories
     * @param array<string,string> $priorities
     */
    public function __construct(
        public readonly int $adults,
        public readonly int $children,
        public readonly ?int $maxBudgetAudCents,
        public readonly ?int $minSleeps,
        public readonly ?int $maxAtmKg,
        public readonly ?float $maxLengthM,
        public readonly array $categories,
        public readonly bool $requireBathroom,
        public readonly int $offGridNights,
        public readonly ?int $preferSolarW,
        public readonly ?int $preferFreshWaterL,
        public readonly array $priorities,
    ) {
    }

    public function requiredSleeps(): int
    {
        if ($this->minSleeps !== null) {
            return $this->minSleeps;
        }

        return max(1, $this->adults + $this->children);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'adults' => $this->adults,
            'children' => $this->children,
            'max_budget_aud_cents' => $this->maxBudgetAudCents,
            'min_sleeps' => $this->minSleeps,
            'max_atm_kg' => $this->maxAtmKg,
            'max_length_m' => $this->maxLengthM,
            'categories' => $this->categories,
            'require_bathroom' => $this->requireBathroom,
            'off_grid_nights' => $this->offGridNights,
            'prefer_solar_w' => $this->preferSolarW,
            'prefer_fresh_water_l' => $this->preferFreshWaterL,
            'priorities' => $this->priorities,
            'score_version' => self::SCORE_VERSION,
        ];
    }

    private static function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $s = trim((string) $item);
            if ($s !== '' && isset(CatalogueService::categoryLabels()[$s])) {
                $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }

    private static function priority(mixed $value): string
    {
        $v = (string) $value;
        return in_array($v, ['essential', 'strong', 'nice', 'avoid', 'ignore'], true) ? $v : 'nice';
    }
}
