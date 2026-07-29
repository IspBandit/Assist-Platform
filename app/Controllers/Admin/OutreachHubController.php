<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\OrganisationOutreach;
use App\Services\OrganisationOutreachImporter;
use RuntimeException;

final class OutreachHubController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $query = trim((string) $request->input('q'));
        $status = (string) $request->input('status');
        $type = (string) $request->input('type');
        $state = strtoupper(trim((string) $request->input('state')));
        return $this->view('admin.outreach-hub.index', [
            'title' => 'PR & Outreach Hub',
            'summary' => OrganisationOutreach::summary(),
            'contacts' => OrganisationOutreach::search($query, $status, $type, $state),
            'campaigns' => Database::select(
                "SELECT id,title,status,delivery_stage,recipient_count,created_at FROM notifications WHERE brand_id=? AND campaign_type='organisation_outreach' ORDER BY id DESC LIMIT 20",
                [current_brand()->databaseId()]
            ),
            'types' => OrganisationOutreach::TYPES,
            'statuses' => OrganisationOutreach::STATUSES,
            'outcomes' => OrganisationOutreach::OUTCOMES,
            'filters' => ['q' => $query, 'status' => $status, 'type' => $type, 'state' => $state],
        ]);
    }

    public function import(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $file = $request->file('csv');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return $this->redirectWith('/admin/outreach-hub', 'error', 'Choose a valid organisation outreach CSV file.');
        }
        try {
            $result = OrganisationOutreachImporter::importFile((string) $file['tmp_name']);
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/outreach-hub', 'error', $error->getMessage());
        }
        AuditLog::record('outreach.organisations.import', 'organisation_outreach', '', null, "imported={$result['imported']};updated={$result['updated']};held={$result['held']}");
        return $this->redirectWith('/admin/outreach-hub', 'success', "Imported {$result['imported']}, updated {$result['updated']}; {$result['held']} invalid rows were held out.");
    }

    public function review(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        try {
            OrganisationOutreach::review(
                $id,
                (string) $request->input('review_status'),
                isset(current_user()['id']) ? (int) current_user()['id'] : null,
                (string) $request->input('consent_basis'),
                (string) $request->input('consent_evidence')
            );
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/outreach-hub', 'error', $error->getMessage());
        }
        AuditLog::record('outreach.organisation.review', 'organisation_outreach', (string) $id, null, (string) $request->input('review_status'));
        return $this->redirectWith('/admin/outreach-hub', 'success', 'Organisation outreach status updated.');
    }

    public function template(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $headers = ['organisation_name','organisation_type','coverage','state_code','website_url','contact_role','email','source_url','source_checked_at','publication_context','relevance_reason','no_unsolicited_warning','personal_or_ambiguous','notes'];
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers, ',', '"', '');
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return (new Response($csv, 200))
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="organisation-outreach-template.csv"');
    }

    public function outcome(Request $request): Response
    {
        $this->requirePermission('notifications.send');
        $id = (int) $request->input('id');
        try {
            OrganisationOutreach::recordOutcome(
                $id,
                (string) $request->input('outcome_status'),
                (string) $request->input('outcome_notes'),
                trim((string) $request->input('next_follow_up')) ?: null
            );
        } catch (RuntimeException $error) {
            return $this->redirectWith('/admin/outreach-hub', 'error', $error->getMessage());
        }
        AuditLog::record('outreach.organisation.outcome', 'organisation_outreach', (string) $id, null, (string) $request->input('outcome_status'));
        return $this->redirectWith('/admin/outreach-hub', 'success', 'Organisation outreach outcome recorded.');
    }

}
