<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use Throwable;

/**
 * Manufacturer-scoped catalogue completeness for the portal data-quality page.
 * Honest gap listing — not a release Quality Gate verdict.
 */
final class ManufacturerDataQualityService
{
    /**
     * @return array{
     *   model_count: int,
     *   variant_count: int,
     *   complete_variants: int,
     *   coverage_percent: int,
     *   models: list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     publication_status: string,
     *     verification_status: string,
     *     gaps: list<string>,
     *     variants: list<array{id:int,name:string,gaps:list<string>,complete:bool}>
     *   }>
     * }
     */
    public function reportForManufacturer(int $brandId, int $manufacturerId): array
    {
        $empty = [
            'model_count' => 0,
            'variant_count' => 0,
            'complete_variants' => 0,
            'coverage_percent' => 0,
            'models' => [],
        ];
        if ($brandId < 1 || $manufacturerId < 1) {
            return $empty;
        }

        try {
            $models = Database::select(
                'SELECT id, name, slug, description, publication_status, verification_status, production_status
                 FROM polaris_rv_models
                 WHERE manufacturer_id = ? AND brand_id = ? AND deleted_at IS NULL
                 ORDER BY name ASC',
                [$manufacturerId, $brandId]
            );
            $variants = Database::select(
                'SELECT id, model_id, name, sleeps, body_length_m, tare_kg, atm_kg,
                        price_status, price_aud_cents, price_effective_on, publication_status
                 FROM polaris_rv_variants
                 WHERE model_id IN (
                     SELECT id FROM polaris_rv_models
                     WHERE manufacturer_id = ? AND brand_id = ? AND deleted_at IS NULL
                 )
                 AND deleted_at IS NULL
                 ORDER BY name ASC',
                [$manufacturerId, $brandId]
            );
        } catch (Throwable) {
            return $empty;
        }

        return self::shapeReport($models, $variants);
    }

    /**
     * Pure shaping for unit tests without MariaDB.
     *
     * @param list<array<string,mixed>> $models
     * @param list<array<string,mixed>> $variants
     * @return array{
     *   model_count: int,
     *   variant_count: int,
     *   complete_variants: int,
     *   coverage_percent: int,
     *   models: list<array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     publication_status: string,
     *     verification_status: string,
     *     gaps: list<string>,
     *     variants: list<array{id:int,name:string,gaps:list<string>,complete:bool}>
     *   }>
     * }
     */
    public static function shapeReport(array $models, array $variants): array
    {
        $byModel = [];
        foreach ($variants as $variant) {
            $modelId = (int) ($variant['model_id'] ?? 0);
            $byModel[$modelId][] = $variant;
        }

        $outModels = [];
        $variantCount = 0;
        $completeVariants = 0;

        foreach ($models as $model) {
            $modelId = (int) ($model['id'] ?? 0);
            $modelGaps = self::assessModel($model);
            $variantRows = [];
            foreach ($byModel[$modelId] ?? [] as $variant) {
                $assessment = self::assessVariant($variant);
                $variantCount++;
                if ($assessment['complete']) {
                    $completeVariants++;
                }
                $variantRows[] = [
                    'id' => (int) ($variant['id'] ?? 0),
                    'name' => (string) ($variant['name'] ?? ''),
                    'gaps' => $assessment['gaps'],
                    'complete' => $assessment['complete'],
                ];
            }
            if (($byModel[$modelId] ?? []) === []) {
                $modelGaps[] = 'No variants';
            }
            $outModels[] = [
                'id' => $modelId,
                'name' => (string) ($model['name'] ?? ''),
                'slug' => (string) ($model['slug'] ?? ''),
                'publication_status' => (string) ($model['publication_status'] ?? ''),
                'verification_status' => (string) ($model['verification_status'] ?? ''),
                'gaps' => $modelGaps,
                'variants' => $variantRows,
            ];
        }

        $coverage = $variantCount > 0
            ? (int) round(($completeVariants / $variantCount) * 100)
            : 0;

        return [
            'model_count' => count($outModels),
            'variant_count' => $variantCount,
            'complete_variants' => $completeVariants,
            'coverage_percent' => $coverage,
            'models' => $outModels,
        ];
    }

    /**
     * @param array<string,mixed> $model
     * @return list<string>
     */
    public static function assessModel(array $model): array
    {
        $gaps = [];
        if (trim((string) ($model['description'] ?? '')) === '') {
            $gaps[] = 'Missing description';
        }
        if ((string) ($model['publication_status'] ?? '') !== 'published') {
            $gaps[] = 'Not published';
        }
        if ((string) ($model['verification_status'] ?? '') === 'pending') {
            $gaps[] = 'Pending verification';
        }
        return $gaps;
    }

    /**
     * @param array<string,mixed> $variant
     * @return array{complete: bool, gaps: list<string>}
     */
    public static function assessVariant(array $variant): array
    {
        $gaps = [];
        if (!isset($variant['sleeps']) || (int) $variant['sleeps'] < 1) {
            $gaps[] = 'Missing sleeps / berths';
        }
        if (!isset($variant['body_length_m']) || !is_numeric($variant['body_length_m']) || (float) $variant['body_length_m'] <= 0) {
            $gaps[] = 'Missing body length';
        }
        if (!isset($variant['tare_kg']) || (int) $variant['tare_kg'] < 1) {
            $gaps[] = 'Missing tare';
        }
        if (!isset($variant['atm_kg']) || (int) $variant['atm_kg'] < 1) {
            $gaps[] = 'Missing ATM';
        }
        $priceStatus = (string) ($variant['price_status'] ?? 'unknown');
        $hasPrice = in_array($priceStatus, ['from', 'rrp', 'indicative'], true)
            && isset($variant['price_aud_cents'])
            && (int) $variant['price_aud_cents'] > 0;
        if (!$hasPrice && $priceStatus !== 'contact_dealer') {
            $gaps[] = 'Missing price guidance';
        } elseif ($hasPrice && trim((string) ($variant['price_effective_on'] ?? '')) === '') {
            $gaps[] = 'Price missing effective date';
        }
        return [
            'complete' => $gaps === [],
            'gaps' => $gaps,
        ];
    }
}
