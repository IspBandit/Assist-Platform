<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\RegulatorySourceMonitor;
use App\Services\RegulatorySponsor;
use App\Services\AuditLog;

final class TrustGrowthController extends Controller
{
    public function index(Request $request): Response
    {
        if (!can('regulatory.manage') && !can('campaigns.manage')) {
            $this->abort(403, 'You do not have permission to view trust and growth operations.');
        }
        return $this->view('admin.trust-growth.index', [
            'title' => 'Trust, rules & growth',
            'health' => [
                'public' => (int) Database::scalar("SELECT COUNT(*) FROM regulatory_documents WHERE is_public=1 AND publication_status IN ('current','upcoming')"),
                'review' => (int) Database::scalar("SELECT COUNT(*) FROM regulatory_documents WHERE publication_status='review'"),
                'overdue' => (int) Database::scalar("SELECT COUNT(*) FROM regulatory_documents WHERE is_public=1 AND next_check_at<NOW()"),
                'failed' => (int) Database::scalar("SELECT COUNT(*) FROM regulatory_source_checks WHERE result='failed' AND checked_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)"),
                'subscribers' => (int) Database::scalar("SELECT COUNT(*) FROM regulatory_alert_subscriptions WHERE status='active'"),
            ],
            'coverage' => Database::select("SELECT jurisdiction_code,COUNT(*) AS total,SUM(publication_status='review') AS review_count FROM regulatory_documents GROUP BY jurisdiction_code ORDER BY jurisdiction_code"),
            'reviewSources' => Database::select(
                "SELECT d.*,a.name AS authority_name FROM regulatory_documents d INNER JOIN regulatory_authorities a ON a.id=d.authority_id "
                . "WHERE d.publication_status='review' OR d.next_check_at<NOW() ORDER BY FIELD(d.publication_status,'review','current','upcoming'),d.next_check_at LIMIT 100"
            ),
            'credentials' => Database::select(
                "SELECT c.*,p.business_name,d.original_name,d.verification_status AS evidence_status,b.name AS brand_name FROM provider_capability_credentials c "
                . "INNER JOIN providers p ON p.id=c.provider_id INNER JOIN brands b ON b.id=c.brand_id LEFT JOIN provider_documents d ON d.id=c.evidence_document_id "
                . "WHERE c.verification_status='pending' ORDER BY c.created_at LIMIT 100"
            ),
            'campaigns' => Database::select(
                "SELECT c.*,p.business_name,b.name AS brand_name,(SELECT COUNT(*) FROM advertising_campaign_targets t WHERE t.campaign_id=c.id) AS target_count "
                . "FROM advertising_campaigns c LEFT JOIN providers p ON p.id=c.advertiser_provider_id INNER JOIN brands b ON b.id=c.brand_id "
                . "WHERE c.status='pending' ORDER BY c.created_at LIMIT 100"
            ),
            'deliveries' => Database::select(
                'SELECT ad.*,d.title,u.email FROM regulatory_alert_deliveries ad INNER JOIN regulatory_documents d ON d.id=ad.document_id '
                . 'INNER JOIN regulatory_alert_subscriptions s ON s.id=ad.subscription_id INNER JOIN users u ON u.id=s.user_id ORDER BY ad.created_at DESC LIMIT 30'
            ),
        ]);
    }

    public function checkSources(Request $request): Response
    {
        $this->requirePermission('regulatory.manage');
        $result = (new RegulatorySourceMonitor())->checkDue(20);
        AuditLog::record('regulatory.sources_checked', 'regulatory_document', null, null, json_encode($result));
        return $this->redirectWith('/admin/trust-growth', 'success', 'Source check complete: ' . $result['checked'] . ' checked, ' . $result['changed'] . ' changed, ' . $result['failed'] . ' failed.');
    }

    public function reviewSource(Request $request): Response
    {
        $this->requirePermission('regulatory.manage');
        $id = (int) $request->input('document_id');
        $action = (string) $request->input('review_action', '');
        if (!in_array($action, ['confirm','retire'], true) || Database::selectOne('SELECT id FROM regulatory_documents WHERE id=?', [$id]) === null) {
            $this->abort(404);
        }
        if ($action === 'confirm') {
            Database::query(
                "UPDATE regulatory_documents SET publication_status='current',change_detected_at=NULL,reviewed_by=?,reviewed_at=NOW(),next_check_at=DATE_ADD(NOW(),INTERVAL check_interval_hours HOUR),updated_at=NOW() WHERE id=?",
                [(int) (current_user()['id'] ?? 0), $id]
            );
        } else {
            Database::query("UPDATE regulatory_documents SET publication_status='retired',is_public=0,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?", [(int) (current_user()['id'] ?? 0), $id]);
        }
        AuditLog::record('regulatory.source_reviewed', 'regulatory_document', (string) $id, null, $action);
        return $this->redirectWith('/admin/trust-growth', 'success', 'Source review recorded.');
    }

    public function reviewCredential(Request $request): Response
    {
        $this->requirePermission('regulatory.manage');
        $id = (int) $request->input('credential_id');
        $action = (string) $request->input('review_action');
        $credential = Database::selectOne(
            'SELECT c.*,d.verification_status AS evidence_status FROM provider_capability_credentials c LEFT JOIN provider_documents d ON d.id=c.evidence_document_id WHERE c.id=?',
            [$id]
        );
        if ($credential === null || !in_array($action, ['verify','reject'], true)) {
            $this->abort(404);
        }
        if ($action === 'verify' && (string) ($credential['evidence_status'] ?? '') !== 'verified') {
            return $this->redirectWith('/admin/trust-growth', 'error', 'Verify the underlying provider document before approving this public capability.');
        }
        $status = $action === 'verify' ? 'verified' : 'rejected';
        Database::query(
            'UPDATE provider_capability_credentials SET verification_status=?,reviewed_by=?,reviewed_at=NOW(),review_notes=?,updated_at=NOW() WHERE id=?',
            [$status, (int) (current_user()['id'] ?? 0), mb_substr(trim((string) $request->input('review_notes')), 0, 500) ?: null, $id]
        );
        AuditLog::record('provider.capability_reviewed', 'provider_capability_credential', (string) $id, null, $status);
        return $this->redirectWith('/admin/trust-growth', 'success', 'Capability review recorded.');
    }

    public function reviewCampaign(Request $request): Response
    {
        $this->requirePermission('campaigns.manage');
        $id = (int) $request->input('campaign_id');
        $action = (string) $request->input('review_action');
        $campaign = Database::selectOne('SELECT * FROM advertising_campaigns WHERE id=?', [$id]);
        if ($campaign === null || !in_array($action, ['activate','reject','pause'], true)) {
            $this->abort(404);
        }
        if ($action === 'activate') {
            $targetCount = (int) Database::scalar('SELECT COUNT(*) FROM advertising_campaign_targets WHERE campaign_id=?', [$id]);
            $unitPrice = (int) round((float) $request->input('unit_price') * 100);
            if ($targetCount < 1 || empty($campaign['daily_budget_cents']) || empty($campaign['total_budget_cents']) || $unitPrice < 10 || $unitPrice > 10000 || !RegulatorySponsor::safeDestination((string) $campaign['destination_url'])) {
                return $this->redirectWith('/admin/trust-growth', 'error', 'Campaign cannot activate until targeting, budget and destination checks pass.');
            }
            Database::query("UPDATE advertising_campaigns SET billing_model='cpc',unit_price_cents=? WHERE id=?", [$unitPrice, $id]);
        }
        $status = ['activate' => 'active', 'reject' => 'rejected', 'pause' => 'paused'][$action];
        Database::query('UPDATE advertising_campaigns SET status=?,updated_at=NOW() WHERE id=?', [$status, $id]);
        AuditLog::record('campaign.reviewed', 'advertising_campaign', (string) $id, null, $status);
        return $this->redirectWith('/admin/trust-growth', 'success', 'Campaign status updated to ' . $status . '.');
    }
}
