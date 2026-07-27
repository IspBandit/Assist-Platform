<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CampaignMetrics
{
    /** @param array<int,int> $campaignIds */
    public static function impressions(array $campaignIds): void
    {
        foreach (array_values(array_unique(array_filter($campaignIds, static fn (int $id): bool => $id > 0))) as $id) {
            Database::query(
                'INSERT INTO advertising_campaign_daily_metrics (campaign_id,metric_date,impressions,clicks,conversions,spend_cents,updated_at) '
                . 'VALUES (?,CURRENT_DATE,1,0,0,0,NOW()) ON DUPLICATE KEY UPDATE impressions=impressions+1,updated_at=NOW()',
                [$id]
            );
        }
    }

    public static function click(int $campaignId, int $priceCents): void
    {
        Database::query(
            'INSERT INTO advertising_campaign_daily_metrics (campaign_id,metric_date,impressions,clicks,conversions,spend_cents,updated_at) '
            . 'VALUES (?,CURRENT_DATE,0,1,0,?,NOW()) ON DUPLICATE KEY UPDATE clicks=clicks+1,spend_cents=spend_cents+VALUES(spend_cents),updated_at=NOW()',
            [$campaignId, max(0, $priceCents)]
        );
    }

    public static function conversion(int $campaignId): void
    {
        Database::query(
            'INSERT INTO advertising_campaign_daily_metrics (campaign_id,metric_date,impressions,clicks,conversions,spend_cents,updated_at) '
            . 'VALUES (?,CURRENT_DATE,0,0,1,0,NOW()) ON DUPLICATE KEY UPDATE conversions=conversions+1,updated_at=NOW()',
            [$campaignId]
        );
    }
}
