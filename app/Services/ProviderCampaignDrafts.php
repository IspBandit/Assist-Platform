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

        $categories = Database::select(
            "SELECT DISTINCT sc.id,sc.name,sc.slug FROM service_categories sc "
            . "INNER JOIN provider_services ps ON ps.category_id=sc.id "
            . "INNER JOIN providers p ON p.id=ps.provider_id AND p.status='active' AND p.deleted_at IS NULL "
            . "INNER JOIN provider_brand_listings bl ON bl.provider_id=p.id AND bl.brand_id=? AND bl.status='active' AND bl.deleted_at IS NULL "
            . "WHERE sc.is_active=1 AND COALESCE(NULLIF(TRIM(p.email),''),NULLIF(TRIM(p.public_email),'')) IS NOT NULL "
            . "AND NOT EXISTS (SELECT 1 FROM notifications n WHERE n.brand_id=? AND n.campaign_type='provider_marketing' AND n.audience_type='provider_category' AND n.category_id=sc.id) "
            . 'ORDER BY sc.name',
            [$brandId, $brandId]
        );

        $created = 0;
        foreach ($categories as $category) {
            $copy = ProviderCampaignCopy::forCategory((string) $category['name'], (string) $category['slug']);
            $created += Database::affecting(
                "INSERT INTO notifications (brand_id,title,body,channel,campaign_type,audience_type,category_id,status,delivery_stage,recipient_count,scheduled_at,created_by,created_at,updated_at) "
                . "SELECT ?,?,?,'email','provider_marketing','provider_category',?,'draft','draft',0,NULL,NULL,NOW(),NOW() "
                . "WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE brand_id=? AND campaign_type='provider_marketing' AND audience_type='provider_category' AND category_id=?)",
                [$brandId, $copy['subject'], $copy['body'], (int) $category['id'], $brandId, (int) $category['id']]
            );
            $created += Database::affecting(
                "INSERT INTO notifications (brand_id,title,body,channel,campaign_type,audience_type,category_id,status,delivery_stage,recipient_count,scheduled_at,created_by,created_at,updated_at) "
                . "SELECT ?,?,?,'email','directory_accuracy','provider_category',?,'draft','draft',0,NULL,NULL,NOW(),NOW() "
                . "WHERE NOT EXISTS (SELECT 1 FROM notifications WHERE brand_id=? AND campaign_type='directory_accuracy' AND audience_type='provider_category' AND category_id=?)",
                [$brandId, DirectoryAccuracyNotice::subject(), DirectoryAccuracyNotice::previewBody(), (int)$category['id'], $brandId, (int)$category['id']]
            );
        }
        return $created;
    }
}
