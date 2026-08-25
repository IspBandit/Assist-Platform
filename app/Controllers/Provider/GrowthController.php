<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\RegulatorySponsor;

final class GrowthController extends Controller
{
    /** @var array<string,array<string,string>> */
    private const CAPABILITIES = [
        'vanassist' => ['mobile-rv-repair' => 'Mobile RV repair', 'roadworthy-inspection' => 'Roadworthy inspection', 'roadside-recovery' => 'Roadside recovery', 'licensed-roadworthy-inspection' => 'Licensed roadworthy inspection', 'approved-vehicle-engineer' => 'Approved vehicle engineering', 'motorcycle-inspection' => 'Motorcycle inspection', 'heavy-vehicle-inspection' => 'Heavy vehicle inspection', 'street-rod-certification' => 'Street rod certification'],
        'towsmart' => ['mobile-weighing' => 'Mobile weighing', 'towing-training' => 'Towing training', 'hitch-installation' => 'Towbar and hitch installation', 'towing-inspection' => 'Towing combination inspection'],
        'trailerwise' => ['trailer-inspection' => 'Trailer inspection', 'trailer-repair' => 'Trailer repair', 'fabrication-engineering' => 'Fabrication and engineering'],
    ];

    public function index(Request $request): Response
    {
        $provider = $this->provider();
        return $this->view('provider.growth', [
            'title' => 'Credentials & campaigns',
            'provider' => $provider,
            'capabilityOptions' => self::CAPABILITIES[current_brand()->id()] ?? [],
            'credentials' => Database::select(
                'SELECT c.*,d.original_name AS evidence_name FROM provider_capability_credentials c LEFT JOIN provider_documents d ON d.id=c.evidence_document_id '
                . 'WHERE c.provider_id=? AND c.brand_id=? ORDER BY c.created_at DESC',
                [(int) $provider['id'], current_brand()->databaseId()]
            ),
            'documents' => Database::select(
                "SELECT id,original_name,doc_type,verification_status FROM provider_documents WHERE provider_id=? AND verification_status<>'rejected' ORDER BY created_at DESC",
                [(int) $provider['id']]
            ),
            'categories' => Database::select('SELECT id,name FROM brand_provider_categories WHERE brand_id=? AND is_active=1 ORDER BY sort_order,name', [current_brand()->databaseId()]),
            'campaigns' => Database::select(
                'SELECT c.*,COALESCE(SUM(m.impressions),0) AS impressions,COALESCE(SUM(m.clicks),0) AS clicks,COALESCE(SUM(m.conversions),0) AS conversions,COALESCE(SUM(m.spend_cents),0) AS spend_cents '
                . 'FROM advertising_campaigns c LEFT JOIN advertising_campaign_daily_metrics m ON m.campaign_id=c.id '
                . 'WHERE c.advertiser_provider_id=? AND c.brand_id=? GROUP BY c.id ORDER BY c.created_at DESC',
                [(int) $provider['id'], current_brand()->databaseId()]
            ),
        ]);
    }

    public function saveCredential(Request $request): Response
    {
        $provider = $this->provider();
        $key = trim((string) $request->input('capability_key', ''));
        $options = self::CAPABILITIES[current_brand()->id()] ?? [];
        if (!isset($options[$key])) {
            return $this->redirectWith('/provider/growth', 'error', 'Choose a supported capability.');
        }
        $documentId = (int) $request->input('evidence_document_id', 0);
        $document = Database::selectOne('SELECT id FROM provider_documents WHERE id=? AND provider_id=?', [$documentId, (int) $provider['id']]);
        if ($document === null) {
            return $this->redirectWith('/provider/growth', 'error', 'Choose evidence from your private provider documents.');
        }
        $jurisdiction = strtoupper(trim((string) $request->input('jurisdiction_code', '')));
        if ($jurisdiction !== '' && !in_array($jurisdiction, ['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'], true)) {
            return $this->redirectWith('/provider/growth', 'error', 'Choose a valid jurisdiction.');
        }
        $validUntilInput = trim((string) $request->input('valid_until', ''));
        $validUntil = $this->dateOrNull($validUntilInput);
        if ($validUntilInput !== '' && $validUntil === null) {
            return $this->redirectWith('/provider/growth', 'error', 'Enter a valid evidence expiry date.');
        }
        Database::query(
            'INSERT INTO provider_capability_credentials (provider_id,brand_id,capability_key,capability_label,jurisdiction_code,evidence_document_id,verification_status,valid_until,created_at) '
            . "VALUES (?,?,?,?,?,?, 'pending',?,NOW()) ON DUPLICATE KEY UPDATE capability_label=VALUES(capability_label),evidence_document_id=VALUES(evidence_document_id),verification_status='pending',valid_until=VALUES(valid_until),reviewed_by=NULL,reviewed_at=NULL,review_notes=NULL,updated_at=NOW()",
            [(int) $provider['id'], current_brand()->databaseId(), $key, $options[$key], $jurisdiction ?: null, $documentId, $validUntil]
        );
        return $this->redirectWith('/provider/growth', 'success', 'Credential submitted for evidence review. It will not appear publicly until verified.');
    }

    public function withdrawCredential(Request $request): Response
    {
        $provider = $this->provider();
        Database::affecting(
            "UPDATE provider_capability_credentials SET verification_status='withdrawn',updated_at=NOW() WHERE id=? AND provider_id=?",
            [(int) $request->input('credential_id'), (int) $provider['id']]
        );
        return $this->redirectWith('/provider/growth', 'success', 'Credential withdrawn from public use.');
    }

    public function saveCampaign(Request $request): Response
    {
        $provider = $this->provider();
        $name = mb_substr(trim((string) $request->input('name', '')), 0, 190);
        $headline = mb_substr(trim((string) $request->input('headline', '')), 0, 120);
        $body = mb_substr(trim((string) $request->input('body', '')), 0, 300);
        $destination = trim((string) $request->input('destination_url', ''));
        if ($name === '' || $headline === '' || !RegulatorySponsor::safeDestination($destination) || strtolower((string) parse_url($destination, PHP_URL_SCHEME)) !== 'https') {
            return $this->redirectWith('/provider/growth', 'error', 'Campaign name, headline and a safe HTTPS destination are required.');
        }
        $categoryId = (int) $request->input('category_id', 0);
        if ($categoryId > 0 && Database::selectOne('SELECT id FROM brand_provider_categories WHERE id=? AND brand_id=? AND is_active=1', [$categoryId, current_brand()->databaseId()]) === null) {
            return $this->redirectWith('/provider/growth', 'error', 'Choose a valid service context.');
        }
        $daily = $this->moneyCents($request->input('daily_budget'));
        $total = $this->moneyCents($request->input('total_budget'));
        if ($daily === null || $total === null || $daily > $total) {
            return $this->redirectWith('/provider/growth', 'error', 'Enter valid budgets; the daily budget cannot exceed the total budget.');
        }
        Database::beginTransaction();
        try {
            $campaignId = Database::insert(
                'INSERT INTO advertising_campaigns (brand_id,advertiser_provider_id,name,objective,status,headline,body,destination_url,daily_budget_cents,total_budget_cents,starts_at,ends_at,created_at) '
                . "VALUES (?,?,?,'provider_profile','pending',?,?,?,?,?,?,?,NOW())",
                [current_brand()->databaseId(), (int) $provider['id'], $name, $headline, $body ?: null, $destination, $daily, $total,
                    $this->dateTimeOrNull($request->input('starts_at')), $this->dateTimeOrNull($request->input('ends_at'))]
            );
            Database::insert(
                "INSERT INTO advertising_campaign_targets (campaign_id,category_id,state_id,region_id,town_id,placement,created_at) VALUES (?,?,NULL,NULL,?,'regulatory_library',NOW())",
                [$campaignId, $categoryId ?: null, !empty($provider['base_town_id']) ? (int) $provider['base_town_id'] : null]
            );
            Database::commit();
        } catch (\Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
        return $this->redirectWith('/provider/growth', 'success', 'Campaign submitted for relevance and sponsorship review. No charge or delivery has started.');
    }

    /** @return array<string,mixed> */
    private function provider(): array
    {
        $provider = Database::selectOne('SELECT * FROM providers WHERE user_id=? AND deleted_at IS NULL', [(int) (current_user()['id'] ?? 0)]);
        if ($provider === null) {
            $this->abort(404, 'No provider profile is linked to this account.');
        }
        return $provider;
    }

    private function moneyCents(mixed $value): ?int
    {
        $amount = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $amount !== false && $amount >= 1 && $amount <= 100000 ? (int) round((float) $amount * 100) : null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $date = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1) {
            return null;
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : null;
    }

    private function dateTimeOrNull(mixed $value): ?string
    {
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }
        $time = strtotime($date);
        return $time === false ? null : date('Y-m-d H:i:s', $time);
    }
}
