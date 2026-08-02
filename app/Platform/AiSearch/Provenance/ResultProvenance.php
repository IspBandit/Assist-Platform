<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Provenance;

/**
 * Provenance labels for Assist AI result cards (ADR 0025).
 */
final class ResultProvenance
{
    public const ORIGIN_CANONICAL = 'canonical';
    public const ORIGIN_IMPORTED = 'imported';
    public const ORIGIN_EXTERNAL_LIVE = 'external_live';
    public const ORIGIN_STAGED = 'staged_candidate';

    /** @var array<string,string> */
    private const LABELS = [
        self::ORIGIN_CANONICAL => 'VanAssist listing',
        self::ORIGIN_IMPORTED => 'Imported dataset (reviewed)',
        self::ORIGIN_EXTERNAL_LIVE => 'External source — not yet verified',
        self::ORIGIN_STAGED => 'Pending review — not a verified listing',
    ];

    public static function label(string $origin): string
    {
        return self::LABELS[$origin] ?? 'Unverified source';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function annotate(
        array $row,
        string $origin,
        string $sourceKey,
        ?string $sourceRecordId = null,
        ?string $licence = null,
        ?string $attribution = null,
        ?float $confidence = null,
    ): array {
        $row['assist_origin'] = $origin;
        $row['assist_source'] = $sourceKey;
        $row['assist_source_record_id'] = $sourceRecordId;
        $row['assist_licence'] = $licence;
        $row['assist_attribution'] = $attribution;
        $row['assist_provenance_label'] = self::label($origin);
        $row['assist_pending_review'] = $origin === self::ORIGIN_STAGED || $origin === self::ORIGIN_EXTERNAL_LIVE;
        $row['assist_is_temporary'] = $origin === self::ORIGIN_EXTERNAL_LIVE;
        if ($confidence !== null) {
            $row['assist_confidence'] = max(0.0, min(1.0, $confidence));
        }
        return $row;
    }
}
