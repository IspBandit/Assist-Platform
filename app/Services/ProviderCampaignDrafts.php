<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Prepares idempotent, review-only VanAssist provider category campaigns. */
final class ProviderCampaignDrafts
{
    public static function prepareForBrand(int $brandId): int
    {
        if ($brandId !== 1) {
            return 0;
        }
        $brandName = trim((string) Database::scalar('SELECT name FROM brands WHERE id=?', [$brandId])) ?: 'VanAssist';

        if (!self::supportsBrandCategories()) {
            return 0;
        }
        $categories = Database::select(
            'SELECT id,name,category_key FROM brand_provider_categories WHERE brand_id=? AND is_active=1 ORDER BY sort_order,name',
            [$brandId]
        );

        $created = 0;
        foreach ($categories as $category) {
            $copy = ProviderCampaignCopy::forCategory((string) $category['name'], (string) $category['category_key']);
            $created += Database::affecting(
                "INSERT INTO notifications (brand_id,title,body,channel,campaign_type,audience_type,provider_brand_category_id,status,delivery_stage,recipient_count,scheduled_at,created_by,created_at,updated_at) "
                . "SELECT ?,?,?,'email','provider_marketing','provider_category',?,'draft','draft',0,NULL,NULL,NOW(),NOW() "
                . "WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE brand_id=? AND campaign_type='provider_marketing' AND audience_type='provider_category' AND provider_brand_category_id=?)",
                [$brandId, $copy['subject'], $copy['body'], (int) $category['id'], $brandId, (int) $category['id']]
            );
            $created += Database::affecting(
                "INSERT INTO notifications (brand_id,title,body,channel,campaign_type,audience_type,provider_brand_category_id,status,delivery_stage,recipient_count,scheduled_at,created_by,created_at,updated_at) "
                . "SELECT ?,?,?,'email','directory_accuracy','provider_category',?,'draft','draft',0,NULL,NULL,NOW(),NOW() "
                . "WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE brand_id=? AND campaign_type='directory_accuracy' AND audience_type='provider_category' AND provider_brand_category_id=?)",
                [$brandId, DirectoryAccuracyNotice::subject($brandName), DirectoryAccuracyNotice::previewBody($brandName), (int)$category['id'], $brandId, (int)$category['id']]
            );
        }

        $created += Database::affecting(
            "INSERT INTO notifications (brand_id,title,body,channel,campaign_type,audience_type,status,delivery_stage,recipient_count,scheduled_at,created_by,created_at,updated_at) "
            . "SELECT ?,?,?,'email','directory_accuracy','providers','draft','draft',0,NULL,NULL,NOW(),NOW() "
            . "WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE brand_id=? AND campaign_type='directory_accuracy' AND audience_type='providers')",
            [$brandId, DirectoryAccuracyNotice::subject($brandName), DirectoryAccuracyNotice::previewBody($brandName), $brandId]
        );
        return $created;
    }

    private static function supportsBrandCategories(): bool
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='provider_brand_category_id'"
        ) === 1;
    }
}
