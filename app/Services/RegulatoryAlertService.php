<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;

final class RegulatoryAlertService
{
    /** @return array{matched:int,queued:int,suppressed:int} */
    public function queueReviewedChanges(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $rows = Database::select(
            "SELECT s.id AS subscription_id,s.brand_id,s.jurisdiction_code,s.vehicle_class,s.document_kind,u.email,u.name,"
            . "d.id AS document_id,d.title,d.source_url,d.change_detected_at "
            . "FROM regulatory_alert_subscriptions s INNER JOIN users u ON u.id=s.user_id "
            . "INNER JOIN regulatory_documents d ON d.jurisdiction_code=s.jurisdiction_code AND d.publication_status='review' "
            . "AND d.change_detected_at IS NOT NULL "
            . "INNER JOIN regulatory_document_brands db ON db.document_id=d.id AND db.brand_id=s.brand_id "
            . "LEFT JOIN regulatory_alert_deliveries ad ON ad.subscription_id=s.id AND ad.document_id=d.id "
            . "WHERE s.status='active' AND s.email_enabled=1 AND ad.id IS NULL "
            . "AND (s.document_kind='' OR s.document_kind=d.document_kind) "
            . "AND (s.vehicle_class='' OR JSON_CONTAINS(d.vehicle_classes_json,JSON_QUOTE(s.vehicle_class))) "
            . "ORDER BY d.change_detected_at,s.id LIMIT " . $limit
        );
        $summary = ['matched' => count($rows), 'queued' => 0, 'suppressed' => 0];
        $registry = BrandRegistry::fromArray((array) config('brands.registry', []));
        $hadBrand = BrandContext::hasCurrent();
        $previousBrand = $hadBrand ? BrandContext::current() : null;

        try {
            foreach ($rows as $row) {
                $email = (string) $row['email'];
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->delivery((int) $row['subscription_id'], (int) $row['document_id'], 'suppressed', 'No valid account email');
                    $summary['suppressed']++;
                    continue;
                }
                $brand = $registry->forDatabaseId((int) $row['brand_id']);
                if ($brand === null) {
                    $this->delivery((int) $row['subscription_id'], (int) $row['document_id'], 'suppressed', 'Unknown brand');
                    $summary['suppressed']++;
                    continue;
                }
                BrandContext::set($brand);
                $title = htmlspecialchars((string) $row['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $source = htmlspecialchars((string) $row['source_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $centre = htmlspecialchars($brand->url() . '/account/compliance', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                EmailQueue::queueRaw(
                    $email,
                    (string) $row['name'],
                    'Official source changed — review before relying on it',
                    '<p>Hi ' . htmlspecialchars((string) $row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                    . '<p>An official source in your saved compliance scope has changed:</p><p><strong>' . $title . '</strong></p>'
                    . '<p>Our previous summary has been removed from public results while the change is reviewed. Do not rely on an older copy for approval or legal compliance.</p>'
                    . '<p><a href="' . $source . '">Open the issuing authority source</a></p><p><a href="' . $centre . '">Manage your alerts</a></p>',
                    "An official source changed: {$row['title']}\nReview the issuing authority source: {$row['source_url']}\nManage alerts: {$brand->url()}/account/compliance",
                    'regulatory_source_change'
                );
                $this->delivery((int) $row['subscription_id'], (int) $row['document_id'], 'queued', null);
                $summary['queued']++;
            }
        } finally {
            if ($previousBrand !== null) {
                BrandContext::set($previousBrand);
            } elseif (!$hadBrand) {
                BrandContext::clear();
            }
        }

        return $summary;
    }

    private function delivery(int $subscriptionId, int $documentId, string $status, ?string $reason): void
    {
        Database::insert(
            'INSERT INTO regulatory_alert_deliveries (subscription_id,document_id,status,reason,queued_at,created_at) VALUES (?,?,?,?,IF(?=\'queued\',NOW(),NULL),NOW())',
            [$subscriptionId, $documentId, $status, $reason, $status]
        );
    }
}
