<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Read helpers for the Polaris new-RV catalogue.
 * Tow vehicle data remains TowSmart-owned; VanAssist providers are not queried here.
 */
final class CatalogueService
{
    public const PRICE_FRESH_DAYS = 180;

    /** @return array<string,string> */
    public static function categoryLabels(): array
    {
        return [
            'caravan' => 'Caravan',
            'hybrid_caravan' => 'Hybrid caravan',
            'camper_trailer' => 'Camper trailer',
            'motorhome' => 'Motorhome',
            'campervan' => 'Campervan',
            'slide_on' => 'Slide-on',
            'other' => 'Other',
        ];
    }

    /** @return array<string,string> */
    public static function sortOptions(): array
    {
        return [
            'name' => 'Name',
            'price_asc' => 'Price low to high',
            'price_desc' => 'Price high to low',
            'tare_asc' => 'Lightest tare',
            'payload_desc' => 'Highest payload',
            'length_asc' => 'Shortest',
            'length_desc' => 'Longest',
            'newest' => 'Newest model year',
            'verified' => 'Most recently verified',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return self::categoryLabels()[$category] ?? ucwords(str_replace('_', ' ', $category));
    }

    public static function payloadKg(?int $tareKg, ?int $atmKg): ?int
    {
        if ($tareKg === null || $atmKg === null || $atmKg < $tareKg) {
            return null;
        }

        return $atmKg - $tareKg;
    }

    public static function formatPrice(?string $status, ?int $cents): string
    {
        $status = $status ?? 'unknown';
        if ($status === 'contact_dealer') {
            return 'Contact dealer';
        }
        if ($status === 'unknown' || $cents === null) {
            return 'Price unavailable';
        }
        $amount = '$' . number_format($cents / 100, 0);
        return match ($status) {
            'from' => 'From ' . $amount,
            'rrp' => 'RRP ' . $amount,
            'indicative' => 'Indicative ' . $amount,
            default => $amount,
        };
    }

    /**
     * @return array{fresh:bool,stale:bool,label:?string,warning:?string}
     */
    public static function priceFreshness(?string $effectiveOn, ?string $expiresOn, ?string $today = null): array
    {
        $todayTs = strtotime($today ?? 'today') ?: time();
        if ($expiresOn !== null && $expiresOn !== '') {
            $exp = strtotime($expiresOn);
            if ($exp !== false && $exp < $todayTs) {
                return [
                    'fresh' => false,
                    'stale' => true,
                    'label' => 'Price expired',
                    'warning' => 'Published price has passed its expiry date — treat as historical only.',
                ];
            }
        }
        if ($effectiveOn === null || $effectiveOn === '') {
            return [
                'fresh' => false,
                'stale' => false,
                'label' => null,
                'warning' => 'Price effective date is unknown.',
            ];
        }
        $eff = strtotime($effectiveOn);
        if ($eff === false) {
            return ['fresh' => false, 'stale' => false, 'label' => null, 'warning' => 'Price date could not be parsed.'];
        }
        $ageDays = (int) floor(($todayTs - $eff) / 86400);
        if ($ageDays > self::PRICE_FRESH_DAYS) {
            return [
                'fresh' => false,
                'stale' => true,
                'label' => 'Price may be stale',
                'warning' => 'Price is older than ' . self::PRICE_FRESH_DAYS . ' days — confirm with the manufacturer or dealer.',
            ];
        }

        return ['fresh' => true, 'stale' => false, 'label' => 'Price within review window', 'warning' => null];
    }

    /**
     * @param array<string,mixed> $variant
     * @return array{payload_kg:?int,price_label:string,demo:bool,price_freshness:array{fresh:bool,stale:bool,label:?string,warning:?string}}
     */
    public static function enrichVariant(array $variant, bool $modelIsDemo = false): array
    {
        $tare = isset($variant['tare_kg']) ? (int) $variant['tare_kg'] : null;
        $atm = isset($variant['atm_kg']) ? (int) $variant['atm_kg'] : null;
        $freshness = self::priceFreshness(
            isset($variant['price_effective_on']) ? (string) $variant['price_effective_on'] : null,
            isset($variant['price_expires_on']) ? (string) $variant['price_expires_on'] : null
        );

        return [
            'payload_kg' => self::payloadKg($tare, $atm),
            'price_label' => self::formatPrice(
                isset($variant['price_status']) ? (string) $variant['price_status'] : null,
                isset($variant['price_aud_cents']) ? (int) $variant['price_aud_cents'] : null
            ),
            'demo' => $modelIsDemo,
            'price_freshness' => $freshness,
        ];
    }

    /**
     * Choose published model year: requested year if valid, else current, else newest.
     *
     * @param list<array<string,mixed>> $years
     * @return array{year: ?array<string,mixed>, requested_invalid: bool}
     */
    public static function resolveModelYear(array $years, ?int $requestedYear): array
    {
        if ($years === []) {
            return ['year' => null, 'requested_invalid' => $requestedYear !== null && $requestedYear > 0];
        }

        $byYear = [];
        foreach ($years as $row) {
            $y = (int) ($row['model_year'] ?? 0);
            if ($y > 0) {
                $byYear[$y] = $row;
            }
        }

        if ($requestedYear !== null && $requestedYear > 0) {
            if (isset($byYear[$requestedYear])) {
                return ['year' => $byYear[$requestedYear], 'requested_invalid' => false];
            }
            // Fall back to default — do not invent unpublished years.
            return ['year' => self::defaultModelYear($years), 'requested_invalid' => true];
        }

        return ['year' => self::defaultModelYear($years), 'requested_invalid' => false];
    }

    /**
     * @param list<array<string,mixed>> $years
     * @return array<string,mixed>|null
     */
    public static function defaultModelYear(array $years): ?array
    {
        if ($years === []) {
            return null;
        }
        foreach ($years as $row) {
            if ((string) ($row['production_status'] ?? '') === 'current') {
                return $row;
            }
        }
        usort(
            $years,
            static fn (array $a, array $b): int => ((int) ($b['model_year'] ?? 0)) <=> ((int) ($a['model_year'] ?? 0))
        );
        return $years[0] ?? null;
    }

    /**
     * @param array<string,mixed> $source
     * @return array{label:string,authority:string,retrieved:?string}
     */
    public static function provenanceChip(array $source): array
    {
        $type = (string) ($source['source_type'] ?? 'other');
        $label = match ($type) {
            'manufacturer_submission' => 'Manufacturer-supplied',
            'brochure' => 'Public brochure',
            'dealer_submission' => 'Dealer submission',
            'community_correction' => 'Community-confirmed',
            'manual_research' => 'Manual research',
            'public_webpage' => 'Public webpage',
            'csv_import' => 'Structured import',
            default => 'Source on file',
        };

        return [
            'label' => $label,
            'authority' => (string) ($source['authority'] ?? 'public'),
            'retrieved' => isset($source['retrieved_at']) ? (string) $source['retrieved_at'] : null,
        ];
    }
}
