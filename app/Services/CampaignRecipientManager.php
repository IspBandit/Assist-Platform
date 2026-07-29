<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use RuntimeException;

/**
 * Admin review controls for provider campaigns. Every provider with a usable
 * email can be reviewed, but only documented consent can make them sendable.
 * Campaign exclusions never override global unsubscribe/bounce suppressions.
 */
final class CampaignRecipientManager
{
    public const CONSENT_BASES = [
        'express_written' => 'Express written consent',
        'express_phone' => 'Express consent by phone',
        'express_web' => 'Express consent through a web form',
        'inferred_role_relevant' => 'Inferred consent — directly relevant business role',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function candidates(array $notification, string $search = '', int $limit = 250): array
    {
        self::assertProviderCampaign($notification);
        [$joins, $params] = self::scope((int) $notification['brand_id'], self::categoryId($notification), self::brandCategoryId($notification));
        $where = self::emailWhere();
        $searchParams = [];
        $search = trim($search);
        if ($search !== '') {
            $where .= " AND (p.business_name LIKE ? OR COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,'')) LIKE ?)";
            $needle = '%' . mb_substr($search, 0, 100) . '%';
            $searchParams[] = $needle;
            $searchParams[] = $needle;
        }
        $params[] = (int) $notification['id'];
        $params = array_merge($params, $searchParams);
        $limit = max(1, min(500, $limit));

        $suppressionScopes = self::isDirectoryAccuracy($notification)
            ? "'directory_accuracy','all'"
            : "'marketing','all'";
        $rows = Database::select(
            "SELECT DISTINCT p.id AS provider_id,p.business_name,LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email,"
            . "p.marketing_opt_in,p.marketing_consented_at,p.marketing_consent_source,p.marketing_consent_evidence,"
            . "p.is_unclaimed,p.public_phone,p.website,t.name AS town_name,st.abbreviation AS state_abbr,"
            . "COALESCE(NULLIF(TRIM(p.source_url),''),(SELECT NULLIF(TRIM(psr.source_url),'') FROM provider_source_records psr WHERE psr.provider_id=p.id AND NULLIF(TRIM(psr.source_url),'') IS NOT NULL ORDER BY psr.confidence DESC,psr.id LIMIT 1)) AS source_evidence,"
            . "COALESCE((SELECT GROUP_CONCAT(DISTINCT bpc.name ORDER BY bpc.name SEPARATOR ', ') FROM provider_brand_listings pbls JOIN provider_brand_category_assignments pbcax ON pbcax.listing_id=pbls.id JOIN brand_provider_categories bpc ON bpc.id=pbcax.category_id WHERE pbls.provider_id=p.id AND pbls.brand_id=" . (int)$notification['brand_id'] . "),"
            . "(SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.name SEPARATOR ', ') FROM provider_services psvc JOIN service_categories sc ON sc.id=psvc.category_id WHERE psvc.provider_id=p.id)) AS services,"
            . "npe.reason AS exclusion_reason,"
            . "(SELECT GROUP_CONCAT(DISTINCT es.reason ORDER BY es.reason SEPARATOR ', ') FROM email_suppressions es "
            . "WHERE LOWER(es.email)=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AND es.scope IN ({$suppressionScopes})) AS suppression_reason "
            . "FROM providers p{$joins} LEFT JOIN towns t ON t.id=p.base_town_id LEFT JOIN states st ON st.id=t.state_id "
            . "LEFT JOIN notification_provider_exclusions npe ON npe.provider_id=p.id AND npe.notification_id=? "
            . "WHERE p.status='active' AND p.deleted_at IS NULL{$where} ORDER BY p.business_name,p.id LIMIT {$limit}",
            $params
        );
        foreach ($rows as &$row) {
            $row['valid_email'] = filter_var((string)$row['email'], FILTER_VALIDATE_EMAIL) !== false;
            $row['status'] = self::status($row, $notification);
            $row['has_documented_consent'] = self::hasDocumentedConsent($row);
            $row['has_directory_evidence'] = self::hasDirectoryEvidence($row);
        }
        unset($row);
        return $rows;
    }

    /** @return array{with_email:int,eligible:int,held:int,excluded:int,suppressed:int} */
    public static function summary(array $notification): array
    {
        self::assertProviderCampaign($notification);
        if (self::isDirectoryAccuracy($notification)) {
            return self::directorySummary($notification);
        }
        return self::marketingSummary($notification);
    }

    public static function exclude(array $notification, int $providerId, string $reason, ?int $userId): void
    {
        $provider = self::assertCandidate($notification, $providerId);
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Removed by an administrator for this campaign.';
        }
        $notificationId = (int) $notification['id'];
        $email = strtolower(trim((string) $provider['email']));
        Database::beginTransaction();
        try {
            Database::query(
                'INSERT INTO notification_provider_exclusions (notification_id,provider_id,reason,excluded_by,created_at) VALUES (?,?,?,?,NOW()) '
                . 'ON DUPLICATE KEY UPDATE reason=VALUES(reason),excluded_by=VALUES(excluded_by),updated_at=NOW()',
                [$notificationId, $providerId, mb_substr($reason, 0, 500), $userId]
            );
            $queued = Database::select(
                "SELECT queue_id FROM notification_recipients WHERE notification_id=? AND LOWER(email)=? AND status='queued' AND queue_id IS NOT NULL",
                [$notificationId, $email]
            );
            foreach ($queued as $row) {
                Database::query("UPDATE email_queue SET status='cancelled' WHERE id=? AND status='pending'", [(int) $row['queue_id']]);
            }
            Database::query(
                "UPDATE notification_recipients SET status='suppressed' WHERE notification_id=? AND LOWER(email)=? AND status='queued'",
                [$notificationId, $email]
            );
            Database::commit();
        } catch (\Throwable $error) {
            Database::rollBack();
            throw $error;
        }
    }

    public static function restore(array $notification, int $providerId): void
    {
        $provider = self::assertCandidate($notification, $providerId);
        if (self::isDirectoryAccuracy($notification) && !self::hasDirectoryEvidence($provider)) {
            throw new RuntimeException('A public source URL is required before this factual directory notice can be sent.');
        }
        if (!self::isDirectoryAccuracy($notification) && !self::hasDocumentedConsent($provider)) {
            throw new RuntimeException('Record a valid consent basis and evidence before adding this provider.');
        }
        self::assertNotSuppressed((string) $provider['email'], self::isDirectoryAccuracy($notification) ? 'directory_accuracy' : 'marketing');
        $notificationId = (int) $notification['id'];
        Database::beginTransaction();
        try {
            Database::query('DELETE FROM notification_provider_exclusions WHERE notification_id=? AND provider_id=?', [$notificationId, $providerId]);
            self::clearCancelledRecipient($notificationId, (string) $provider['email']);
            Database::commit();
        } catch (\Throwable $error) {
            Database::rollBack();
            throw $error;
        }
    }

    public static function recordConsentAndInclude(array $notification, int $providerId, string $basis, string $evidence, string $consentedAt): void
    {
        if (self::isDirectoryAccuracy($notification)) {
            throw new RuntimeException('Marketing consent cannot be edited from a factual directory-notice campaign.');
        }
        $provider = self::assertCandidate($notification, $providerId);
        self::assertNotSuppressed((string) $provider['email'], 'marketing');
        if (!isset(self::CONSENT_BASES[$basis])) {
            throw new RuntimeException('Choose a valid consent basis.');
        }
        $evidence = trim($evidence);
        if (mb_strlen($evidence) < 10) {
            throw new RuntimeException('Add enough evidence to show when, where and how consent was obtained.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $consentedAt);
        if ($date === false || $date->format('Y-m-d') !== $consentedAt || $date > new DateTimeImmutable('today')) {
            throw new RuntimeException('Enter a valid consent date that is not in the future.');
        }
        Database::beginTransaction();
        try {
            Database::query(
                'UPDATE providers SET marketing_opt_in=1,marketing_consented_at=?,marketing_consent_source=?,marketing_consent_evidence=?,updated_at=NOW() WHERE id=?',
                [$date->format('Y-m-d 00:00:00'), $basis, mb_substr($evidence, 0, 500), $providerId]
            );
            Database::query('DELETE FROM notification_provider_exclusions WHERE notification_id=? AND provider_id=?', [(int) $notification['id'], $providerId]);
            self::clearCancelledRecipient((int) $notification['id'], (string) $provider['email']);
            Database::commit();
        } catch (\Throwable $error) {
            Database::rollBack();
            throw $error;
        }
    }

    /** @param array<int,array<string,mixed>> $recipients @return array<int,array<string,mixed>> */
    public static function filter(int $notificationId, array $recipients): array
    {
        $rows = Database::select(
            "SELECT npe.provider_id,LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email "
            . 'FROM notification_provider_exclusions npe INNER JOIN providers p ON p.id=npe.provider_id WHERE npe.notification_id=?',
            [$notificationId]
        );
        $excluded = array_fill_keys(array_map(static fn (array $row): int => (int) $row['provider_id'], $rows), true);
        $excludedEmails = array_fill_keys(array_filter(array_map(static fn (array $row): string => strtolower(trim((string) $row['email'])), $rows)), true);
        $suppressedRows = Database::select("SELECT LOWER(email) AS email FROM email_suppressions WHERE scope IN ('marketing','all')");
        $suppressedEmails = array_fill_keys(array_filter(array_map(static fn (array $row): string => strtolower(trim((string) $row['email'])), $suppressedRows)), true);
        return array_values(array_filter($recipients, static function (array $recipient) use ($excluded, $excludedEmails, $suppressedEmails): bool {
            $providerId = isset($recipient['provider_id']) ? (int) $recipient['provider_id'] : 0;
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            return ($providerId === 0 || !isset($excluded[$providerId]))
                && !isset($excludedEmails[$email])
                && !isset($suppressedEmails[$email]);
        }));
    }

    /** @param array<string,mixed> $notification @return array<int,array<string,mixed>> */
    public static function eligibleRecipients(array $notification): array
    {
        self::assertProviderCampaign($notification);
        if (!self::isDirectoryAccuracy($notification) && self::brandCategoryId($notification) === null) {
            $rows = BroadcastAudience::resolve(
                (string) $notification['audience_type'],
                $notification['town_id'] !== null ? (int) $notification['town_id'] : null,
                $notification['region_id'] !== null ? (int) $notification['region_id'] : null,
                self::categoryId($notification),
            );
            return self::filter((int) $notification['id'], $rows);
        }

        if (!self::isDirectoryAccuracy($notification)) {
            return self::eligibleMarketingRecipients($notification);
        }

        [$joins, $params] = self::scope((int) $notification['brand_id'], self::categoryId($notification), self::brandCategoryId($notification));
        $params[] = (int) $notification['id'];
        $rows = Database::select(
            "SELECT DISTINCT p.id AS provider_id,p.business_name,LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email,"
            . "p.public_phone,p.website,t.name AS town_name,st.abbreviation AS state_abbr,"
            . "COALESCE(NULLIF(TRIM(p.source_url),''),(SELECT NULLIF(TRIM(psr.source_url),'') FROM provider_source_records psr WHERE psr.provider_id=p.id AND NULLIF(TRIM(psr.source_url),'') IS NOT NULL ORDER BY psr.confidence DESC,psr.id LIMIT 1)) AS source_evidence,"
            . "COALESCE((SELECT GROUP_CONCAT(DISTINCT bpc.name ORDER BY bpc.name SEPARATOR ', ') FROM provider_brand_listings pbls JOIN provider_brand_category_assignments pbcax ON pbcax.listing_id=pbls.id JOIN brand_provider_categories bpc ON bpc.id=pbcax.category_id WHERE pbls.provider_id=p.id AND pbls.brand_id=" . (int)$notification['brand_id'] . "),"
            . "(SELECT GROUP_CONCAT(DISTINCT sc.name ORDER BY sc.name SEPARATOR ', ') FROM provider_services psvc JOIN service_categories sc ON sc.id=psvc.category_id WHERE psvc.provider_id=p.id)) AS services "
            . "FROM providers p{$joins} LEFT JOIN towns t ON t.id=p.base_town_id LEFT JOIN states st ON st.id=t.state_id "
            . "WHERE p.status='active' AND p.deleted_at IS NULL AND p.is_unclaimed=1" . self::emailWhere()
            . " AND (NULLIF(TRIM(p.source_url),'') IS NOT NULL OR EXISTS (SELECT 1 FROM provider_source_records psr2 WHERE psr2.provider_id=p.id AND NULLIF(TRIM(psr2.source_url),'') IS NOT NULL))"
            . " AND NOT EXISTS (SELECT 1 FROM notification_provider_exclusions npe INNER JOIN providers ep ON ep.id=npe.provider_id "
            . "WHERE npe.notification_id=? AND LOWER(COALESCE(NULLIF(ep.email,''),NULLIF(ep.public_email,'')))=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))))"
            . " AND NOT EXISTS (SELECT 1 FROM email_suppressions es WHERE LOWER(es.email)=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AND es.scope IN ('directory_accuracy','all'))"
            . ' ORDER BY p.business_name,p.id',
            $params
        );
        $seen = [];
        $eligible = [];
        foreach ($rows as $row) {
            $email = strtolower(trim((string) $row['email']));
            if ($email === '' || filter_var($email,FILTER_VALIDATE_EMAIL) === false || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $eligible[] = [
                'user_id' => null,
                'provider_id' => (int) $row['provider_id'],
                'email' => $email,
                'name' => (string) $row['business_name'],
                'business_name' => (string) $row['business_name'],
                'town_name' => (string) ($row['town_name'] ?? ''),
                'state_abbr' => (string) ($row['state_abbr'] ?? ''),
                'services' => (string) ($row['services'] ?? ''),
                'public_phone' => (string) ($row['public_phone'] ?? ''),
                'website' => (string) ($row['website'] ?? ''),
                'source_evidence' => (string) ($row['source_evidence'] ?? ''),
            ];
        }
        return $eligible;
    }

    /** @param array<string,mixed> $notification @return array<int,array<string,mixed>> */
    private static function eligibleMarketingRecipients(array $notification): array
    {
        [$joins,$params] = self::scope((int)$notification['brand_id'],self::categoryId($notification),self::brandCategoryId($notification));
        $params[] = (int)$notification['id'];
        $rows = Database::select(
            "SELECT DISTINCT p.id AS provider_id,p.business_name AS name,LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email,"
            . "p.marketing_consent_source,CONCAT(DATE_FORMAT(p.marketing_consented_at,'%Y-%m-%d'),': ',p.marketing_consent_source,': ',p.marketing_consent_evidence) AS compliance_evidence "
            . "FROM providers p{$joins} WHERE p.status='active' AND p.deleted_at IS NULL "
            . "AND p.marketing_opt_in=1 AND p.marketing_consented_at IS NOT NULL "
            . "AND p.marketing_consent_source IN ('express_written','express_phone','express_web','inferred_role_relevant') "
            . "AND NULLIF(TRIM(p.marketing_consent_evidence),'') IS NOT NULL" . self::emailWhere()
            . " AND NOT EXISTS (SELECT 1 FROM notification_provider_exclusions npe INNER JOIN providers ep ON ep.id=npe.provider_id WHERE npe.notification_id=? AND LOWER(COALESCE(NULLIF(ep.email,''),NULLIF(ep.public_email,'')))=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))))"
            . " AND NOT EXISTS (SELECT 1 FROM email_suppressions es WHERE LOWER(es.email)=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AND es.scope IN ('marketing','all')) ORDER BY p.business_name,p.id",
            $params
        );
        $seen=[];$eligible=[];
        foreach($rows as $row){
            $email=strtolower(trim((string)$row['email']));
            if($email===''||filter_var($email,FILTER_VALIDATE_EMAIL)===false||isset($seen[$email])) continue;
            $seen[$email]=true;
            $eligible[]=['user_id'=>null,'provider_id'=>(int)$row['provider_id'],'email'=>$email,'name'=>(string)$row['name'],'compliance_evidence'=>(string)$row['compliance_evidence']];
        }
        return $eligible;
    }

    /** @return array<string,mixed> */
    private static function assertCandidate(array $notification, int $providerId): array
    {
        self::assertProviderCampaign($notification);
        [$joins, $params] = self::scope((int) $notification['brand_id'], self::categoryId($notification), self::brandCategoryId($notification));
        $params[] = $providerId;
        $provider = Database::selectOne(
            "SELECT DISTINCT p.*,LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email,"
            . "COALESCE(NULLIF(TRIM(p.source_url),''),(SELECT NULLIF(TRIM(psr.source_url),'') FROM provider_source_records psr WHERE psr.provider_id=p.id AND NULLIF(TRIM(psr.source_url),'') IS NOT NULL ORDER BY psr.confidence DESC,psr.id LIMIT 1)) AS source_evidence FROM providers p{$joins} "
            . "WHERE p.status='active' AND p.deleted_at IS NULL" . self::emailWhere() . ' AND p.id=?',
            $params
        );
        if ($provider === null) {
            throw new RuntimeException('That provider is not part of this campaign audience.');
        }
        return $provider;
    }

    private static function assertProviderCampaign(array $notification): void
    {
        if (!in_array((string) ($notification['audience_type'] ?? ''), ['providers', 'provider_category'], true)) {
            throw new RuntimeException('Recipient controls apply only to provider campaigns.');
        }
        if (!in_array((string) ($notification['campaign_type'] ?? ''), ['provider_marketing', 'directory_accuracy'], true)) {
            throw new RuntimeException('Provider audiences require an explicit provider campaign type.');
        }
    }

    /** @return array{0:string,1:array<int,int>} */
    private static function scope(int $brandId, ?int $categoryId, ?int $brandCategoryId): array
    {
        $joins = " INNER JOIN provider_brand_listings bl ON bl.provider_id=p.id AND bl.brand_id=? AND bl.status='active' AND bl.deleted_at IS NULL";
        $params = [$brandId];
        if ($brandCategoryId !== null) {
            $joins .= ' INNER JOIN provider_brand_category_assignments pbca ON pbca.listing_id=bl.id AND pbca.category_id=?';
            $params[] = $brandCategoryId;
        } elseif ($categoryId !== null) {
            $joins .= ' INNER JOIN provider_services ps ON ps.provider_id=p.id AND ps.category_id=?';
            $params[] = $categoryId;
        }
        return [$joins, $params];
    }

    private static function categoryId(array $notification): ?int
    {
        return $notification['category_id'] !== null ? (int) $notification['category_id'] : null;
    }

    private static function brandCategoryId(array $notification): ?int
    {
        return !empty($notification['provider_brand_category_id']) ? (int)$notification['provider_brand_category_id'] : null;
    }

    private static function emailWhere(): string
    {
        return " AND COALESCE(NULLIF(TRIM(p.email),''),NULLIF(TRIM(p.public_email),'')) IS NOT NULL";
    }

    private static function hasDocumentedConsent(array $provider): bool
    {
        return (int) ($provider['marketing_opt_in'] ?? 0) === 1
            && !empty($provider['marketing_consented_at'])
            && isset(self::CONSENT_BASES[(string) ($provider['marketing_consent_source'] ?? '')])
            && trim((string) ($provider['marketing_consent_evidence'] ?? '')) !== '';
    }

    private static function status(array $provider, array $notification): string
    {
        if (isset($provider['valid_email']) && !$provider['valid_email']) {
            return 'held';
        }
        if (trim((string) ($provider['suppression_reason'] ?? '')) !== '') {
            return 'suppressed';
        }
        if (trim((string) ($provider['exclusion_reason'] ?? '')) !== '') {
            return 'excluded';
        }
        return self::isDirectoryAccuracy($notification)
            ? (self::hasDirectoryEvidence($provider) ? 'eligible' : 'held')
            : (self::hasDocumentedConsent($provider) ? 'eligible' : 'held');
    }

    private static function assertNotSuppressed(string $email, string $messageType): void
    {
        if (EmailSuppression::isSuppressed($email, $messageType)) {
            throw new RuntimeException('This email is globally suppressed because of an opt-out, complaint or delivery failure and cannot be added.');
        }
    }

    private static function isDirectoryAccuracy(array $notification): bool
    {
        return (string) ($notification['campaign_type'] ?? '') === 'directory_accuracy';
    }

    private static function hasDirectoryEvidence(array $provider): bool
    {
        return (int) ($provider['is_unclaimed'] ?? 0) === 1
            && trim((string) ($provider['source_evidence'] ?? '')) !== '';
    }

    /** @return array{with_email:int,eligible:int,held:int,excluded:int,suppressed:int} */
    private static function directorySummary(array $notification): array
    {
        [$joins, $params] = self::scope((int) $notification['brand_id'], self::categoryId($notification), self::brandCategoryId($notification));
        $params[] = (int) $notification['id'];
        $rows = Database::select(
            "SELECT DISTINCT p.id,p.is_unclaimed,LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email,npe.reason AS exclusion_reason,"
            . "COALESCE(NULLIF(TRIM(p.source_url),''),(SELECT NULLIF(TRIM(psr.source_url),'') FROM provider_source_records psr WHERE psr.provider_id=p.id AND NULLIF(TRIM(psr.source_url),'') IS NOT NULL ORDER BY psr.confidence DESC,psr.id LIMIT 1)) AS source_evidence,"
            . "(SELECT COUNT(*) FROM email_suppressions es WHERE LOWER(es.email)=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AND es.scope IN ('directory_accuracy','all')) AS suppressed "
            . "FROM providers p{$joins} LEFT JOIN notification_provider_exclusions npe ON npe.provider_id=p.id AND npe.notification_id=? "
            . "WHERE p.status='active' AND p.deleted_at IS NULL" . self::emailWhere(),
            $params
        );
        $statusByEmail = [];
        $priority = ['held' => 1, 'eligible' => 2, 'excluded' => 3, 'suppressed' => 4];
        foreach ($rows as $row) {
            if ((int) $row['suppressed'] > 0) {
                $status = 'suppressed';
            } elseif (trim((string) ($row['exclusion_reason'] ?? '')) !== '') {
                $status = 'excluded';
            } elseif (filter_var((string)$row['email'],FILTER_VALIDATE_EMAIL) === false) {
                $status = 'held';
            } elseif (self::hasDirectoryEvidence($row)) {
                $status = 'eligible';
            } else {
                $status = 'held';
            }
            $email = strtolower(trim((string) $row['email']));
            if (!isset($statusByEmail[$email]) || $priority[$status] > $priority[$statusByEmail[$email]]) {
                $statusByEmail[$email] = $status;
            }
        }
        $summary = ['with_email' => count($statusByEmail), 'eligible' => 0, 'held' => 0, 'excluded' => 0, 'suppressed' => 0];
        foreach ($statusByEmail as $status) {
            $summary[$status]++;
        }
        return $summary;
    }

    /** @return array{with_email:int,eligible:int,held:int,excluded:int,suppressed:int} */
    private static function marketingSummary(array $notification): array
    {
        [$joins,$params]=self::scope((int)$notification['brand_id'],self::categoryId($notification),self::brandCategoryId($notification));
        $params[]=(int)$notification['id'];
        $rows=Database::select(
            "SELECT LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AS email,p.marketing_opt_in,p.marketing_consented_at,p.marketing_consent_source,p.marketing_consent_evidence,npe.reason AS exclusion_reason,"
            . "(SELECT COUNT(*) FROM email_suppressions es WHERE LOWER(es.email)=LOWER(COALESCE(NULLIF(p.email,''),NULLIF(p.public_email,''))) AND es.scope IN ('marketing','all')) AS suppressed "
            . "FROM providers p{$joins} LEFT JOIN notification_provider_exclusions npe ON npe.provider_id=p.id AND npe.notification_id=? "
            . "WHERE p.status='active' AND p.deleted_at IS NULL" . self::emailWhere(),
            $params
        );
        $statusByEmail=[];$priority=['held'=>1,'eligible'=>2,'excluded'=>3,'suppressed'=>4];
        foreach($rows as $row){
            $email=strtolower(trim((string)$row['email']));
            if((int)$row['suppressed']>0){$status='suppressed';}
            elseif(trim((string)($row['exclusion_reason']??''))!==''){$status='excluded';}
            elseif(filter_var($email,FILTER_VALIDATE_EMAIL)===false){$status='held';}
            elseif(self::hasDocumentedConsent($row)){$status='eligible';}
            else{$status='held';}
            if(!isset($statusByEmail[$email])||$priority[$status]>$priority[$statusByEmail[$email]]){$statusByEmail[$email]=$status;}
        }
        $summary=['with_email'=>count($statusByEmail),'eligible'=>0,'held'=>0,'excluded'=>0,'suppressed'=>0];
        foreach($statusByEmail as $status){$summary[$status]++;}
        return $summary;
    }

    private static function clearCancelledRecipient(int $notificationId, string $email): void
    {
        Database::query(
            "DELETE nr FROM notification_recipients nr LEFT JOIN email_queue eq ON eq.id=nr.queue_id "
            . "WHERE nr.notification_id=? AND LOWER(nr.email)=? AND nr.status='suppressed' AND (nr.queue_id IS NULL OR eq.status='cancelled')",
            [$notificationId, strtolower(trim($email))]
        );
    }
}
