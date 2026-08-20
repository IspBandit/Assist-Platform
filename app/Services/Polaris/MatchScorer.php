<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Deterministic, explainable RV match scoring.
 * Missing data never scores as a favourable match — uncertainty is penalised.
 */
final class MatchScorer
{
    private const WEIGHTS = [
        'essential' => 0,
        'strong' => 3.0,
        'nice' => 1.5,
        'avoid' => -2.0,
        'ignore' => 0.0,
    ];

    /**
     * @param array<string,mixed> $model Card-shaped model with variant aggregates
     * @return array{
     *   eligible:bool,
     *   overall:float,
     *   band:string,
     *   passed:list<string>,
     *   failed:list<string>,
     *   missing:list<string>,
     *   reasons:list<string>,
     *   compromises:list<string>,
     *   category_scores:array<string,float>,
     *   score_version:string
     * }
     */
    public function score(array $model, PreferenceProfile $profile): array
    {
        $passed = [];
        $failed = [];
        $missing = [];
        $reasons = [];
        $compromises = [];
        $categoryScores = [
            'sleeping' => 50.0,
            'budget' => 50.0,
            'weight' => 50.0,
            'off_grid' => 50.0,
            'data_confidence' => 50.0,
        ];

        $sleeps = isset($model['sleeps']) ? (int) $model['sleeps'] : null;
        $atm = isset($model['atm_kg']) ? (int) $model['atm_kg'] : null;
        $tare = isset($model['tare_kg']) ? (int) $model['tare_kg'] : null;
        $length = isset($model['body_length_m']) ? (float) $model['body_length_m'] : null;
        $priceCents = isset($model['price_aud_cents']) ? (int) $model['price_aud_cents'] : null;
        $priceStatus = (string) ($model['price_status'] ?? 'unknown');
        $solar = isset($model['solar_w']) ? (int) $model['solar_w'] : null;
        $fresh = isset($model['fresh_water_l']) ? (int) $model['fresh_water_l'] : null;
        $bathroom = trim((string) ($model['bathroom_type'] ?? ''));
        $category = (string) ($model['category'] ?? '');
        $verified = in_array((string) ($model['verification_status'] ?? ''), ['verified'], true);

        $requiredSleeps = $profile->requiredSleeps();
        if ($sleeps === null) {
            $missing[] = 'Sleeping capacity is not published for this model.';
            $categoryScores['sleeping'] = 35.0;
        } elseif ($sleeps < $requiredSleeps) {
            $failed[] = "Needs to sleep at least {$requiredSleeps}; this layout lists {$sleeps}.";
            $categoryScores['sleeping'] = 10.0;
        } else {
            $passed[] = "Sleeps {$sleeps} (required {$requiredSleeps}).";
            $categoryScores['sleeping'] = min(100.0, 70.0 + (($sleeps - $requiredSleeps) * 5.0));
            $reasons[] = 'Sleeping capacity meets your household size.';
        }

        if ($profile->categories !== [] && $category !== '' && !in_array($category, $profile->categories, true)) {
            $failed[] = 'Category is outside your selected RV types.';
        } elseif ($profile->categories !== [] && $category !== '') {
            $passed[] = 'Matches a preferred RV category.';
            $reasons[] = 'Category aligns with your shortlist.';
        }

        if ($profile->maxAtmKg !== null) {
            if ($atm === null) {
                $missing[] = 'ATM is unknown — cannot confirm tow-weight ceiling.';
                $categoryScores['weight'] = 30.0;
            } elseif ($atm > $profile->maxAtmKg) {
                $failed[] = "ATM {$atm} kg exceeds your maximum {$profile->maxAtmKg} kg.";
                $categoryScores['weight'] = 5.0;
            } else {
                $passed[] = "ATM {$atm} kg is within your {$profile->maxAtmKg} kg ceiling.";
                $categoryScores['weight'] = 85.0;
                $reasons[] = 'Headline ATM fits your stated tow limit.';
            }
        } elseif ($atm === null) {
            $missing[] = 'ATM not published.';
            $categoryScores['weight'] = 40.0;
        } else {
            $categoryScores['weight'] = 65.0;
        }

        if ($profile->maxLengthM !== null) {
            if ($length === null) {
                $missing[] = 'Body length is unknown.';
            } elseif ($length > $profile->maxLengthM) {
                $failed[] = 'Body length exceeds your maximum.';
            } else {
                $passed[] = 'Length is within your limit.';
            }
        }

        if ($profile->maxBudgetAudCents !== null) {
            if ($priceCents === null || in_array($priceStatus, ['unknown', 'contact_dealer'], true)) {
                $missing[] = 'Price is unavailable — budget fit cannot be confirmed.';
                $categoryScores['budget'] = 28.0;
                $compromises[] = 'Budget comparison is incomplete without a published price.';
            } elseif ($priceCents > $profile->maxBudgetAudCents) {
                $failed[] = 'Published price is above your maximum budget.';
                $categoryScores['budget'] = 8.0;
            } else {
                $ratio = $priceCents / $profile->maxBudgetAudCents;
                $categoryScores['budget'] = max(55.0, 100.0 - ($ratio * 40.0));
                $passed[] = 'Published price is within your maximum budget.';
                $reasons[] = 'Price fits your stated budget ceiling.';
            }
        }

        if ($profile->requireBathroom) {
            if ($bathroom === '' || strcasecmp($bathroom, 'None') === 0) {
                $failed[] = 'An onboard bathroom was marked essential.';
            } else {
                $passed[] = "Bathroom type: {$bathroom}.";
                $reasons[] = 'Includes an onboard bathroom.';
            }
        }

        $offGridScore = 45.0;
        if ($profile->offGridNights >= 5) {
            if ($solar === null && $fresh === null) {
                $missing[] = 'Solar and water capacities are unknown for off-grid assessment.';
                $offGridScore = 25.0;
            } else {
                $offGridScore = 55.0;
                if ($solar !== null && $solar >= 400) {
                    $offGridScore += 15.0;
                    $reasons[] = 'Solar capacity supports longer free-camping stays.';
                } elseif ($solar !== null) {
                    $compromises[] = 'Solar is modest for week-long off-grid use.';
                } else {
                    $missing[] = 'Solar capacity not published.';
                }
                if ($fresh !== null && $fresh >= 150) {
                    $offGridScore += 15.0;
                } elseif ($fresh !== null) {
                    $compromises[] = 'Fresh water may be tight for longer off-grid stays.';
                }
            }
        } elseif ($solar !== null || $fresh !== null) {
            $offGridScore = 60.0;
        }
        $categoryScores['off_grid'] = min(100.0, $offGridScore);

        $confidence = 40.0;
        if ($verified) {
            $confidence += 25.0;
            $reasons[] = 'Manufacturer or model data is marked verified.';
        }
        if ($atm !== null && $tare !== null) {
            $confidence += 15.0;
        } else {
            $missing[] = 'Weight pair incomplete — payload cannot be calculated confidently.';
            $confidence -= 10.0;
        }
        if ($priceCents !== null && !in_array($priceStatus, ['unknown', 'contact_dealer'], true)) {
            $confidence += 10.0;
        }
        if ($missing !== []) {
            $confidence -= min(25.0, count($missing) * 4.0);
        }
        $categoryScores['data_confidence'] = max(5.0, min(100.0, $confidence));

        $eligible = $failed === [];
        $overall = $this->weightedOverall($categoryScores, $profile, $eligible, count($missing));

        $band = 'insufficient_data';
        if ($eligible && count($missing) <= 1 && $overall >= 75.0) {
            $band = 'known_match';
        } elseif ($eligible && $overall >= 55.0) {
            $band = 'likely_match';
        } elseif (!$eligible) {
            $band = 'not_eligible';
        }

        if (!$eligible) {
            $compromises[] = 'One or more essential constraints failed.';
        }

        return [
            'eligible' => $eligible,
            'overall' => round($overall, 1),
            'band' => $band,
            'passed' => $passed,
            'failed' => $failed,
            'missing' => $missing,
            'reasons' => $reasons,
            'compromises' => $compromises,
            'category_scores' => array_map(static fn (float $v): float => round($v, 1), $categoryScores),
            'score_version' => PreferenceProfile::SCORE_VERSION,
        ];
    }

    /**
     * @param array<string,float> $categoryScores
     */
    private function weightedOverall(array $categoryScores, PreferenceProfile $profile, bool $eligible, int $missingCount): float
    {
        if (!$eligible) {
            return max(5.0, min(40.0, ($categoryScores['data_confidence'] ?? 30.0) * 0.4));
        }

        $map = [
            'towability' => 'weight',
            'price' => 'budget',
            'off_grid' => 'off_grid',
            'comfort' => 'sleeping',
            'payload' => 'weight',
        ];

        $sum = 0.0;
        $weight = 0.0;
        foreach ($map as $priorityKey => $scoreKey) {
            $level = $profile->priorities[$priorityKey] ?? 'nice';
            $w = self::WEIGHTS[$level] ?? 1.0;
            if ($w <= 0) {
                continue;
            }
            $sum += ($categoryScores[$scoreKey] ?? 50.0) * $w;
            $weight += $w;
        }
        $sum += ($categoryScores['data_confidence'] ?? 50.0) * 2.0;
        $weight += 2.0;

        $base = $weight > 0 ? $sum / $weight : 50.0;
        $uncertaintyPenalty = min(20.0, $missingCount * 3.5);

        return max(0.0, min(100.0, $base - $uncertaintyPenalty));
    }
}
