<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Services\FeatureFlag;
use Throwable;

/**
 * Honest cost estimate for Polaris import paths (AUD cents).
 * Deterministic extractors are free; paid AI remains flag-gated and estimated only.
 */
final class ExtractionCostEstimator
{
    public const AI_TOKENS_PER_PAGE_ESTIMATE = 2500;
    public const AI_AUD_CENTS_PER_1K_TOKENS = 2; // illustrative placeholder; not a live tariff

    /**
     * @return array{mode:string,aud_cents:int,label:string,tokens_estimate:?int,ai_enabled:bool}
     */
    public static function forMode(string $mode, int $pagesOrRows = 1, ?bool $aiEnabled = null): array
    {
        $aiOn = $aiEnabled ?? self::isAiImportEnabled();
        $pagesOrRows = max(1, $pagesOrRows);

        if ($mode === 'ai_brochure') {
            $tokens = self::AI_TOKENS_PER_PAGE_ESTIMATE * $pagesOrRows;
            $cents = (int) ceil(($tokens / 1000) * self::AI_AUD_CENTS_PER_1K_TOKENS);
            return [
                'mode' => 'ai_brochure',
                'aud_cents' => $aiOn ? $cents : 0,
                'label' => $aiOn
                    ? ('Estimated ~AUD ' . number_format($cents / 100, 2) . ' (illustrative; not billed here)')
                    : 'Paid AI import is OFF (`polaris_ai_import`). Deterministic extract remains free.',
                'tokens_estimate' => $tokens,
                'ai_enabled' => $aiOn,
            ];
        }

        return [
            'mode' => $mode,
            'aud_cents' => 0,
            'label' => 'AUD 0.00 — deterministic draft extraction (no AI tokens).',
            'tokens_estimate' => null,
            'ai_enabled' => $aiOn,
        ];
    }

    private static function isAiImportEnabled(): bool
    {
        try {
            return FeatureFlag::enabled('polaris_ai_import', false);
        } catch (Throwable) {
            return false;
        }
    }
}
