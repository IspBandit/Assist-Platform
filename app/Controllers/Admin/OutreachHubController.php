<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\FacebookPagePublisher;
use App\Services\OrganisationOutreach;
use App\Services\OrganisationOutreachImporter;
use App\Services\Settings;
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
        $brand = current_brand();
        $brandId = $brand->databaseId();
        $brandName = $brand->name();
        $resourceDescription = match ($brand->id()) {
            'vanassist' => 'nearby caravan and RV services, fuel, EV charging and caravan-friendly places to stay across Australia',
            'towsmart' => 'practical towing calculations, safety guidance and towing specialists',
            'trailerwise' => 'trailer ownership guidance, relevant services and trailer listings',
            default => 'useful local services and practical information',
        };
        $launchCampaign = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $brandName) ?? 'assist') . '-free-launch';
        $trackedUrl = static fn (string $source, string $medium): string => rtrim(url('/'), '/') . '/?' . http_build_query([
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $launchCampaign,
        ]);
        $communityUrl = $trackedUrl('facebook_group', 'community');
        $messengerUrl = $trackedUrl('messenger', 'personal_share');
        $newsletterUrl = $trackedUrl('club_newsletter', 'partner');
        $providerUrl = $trackedUrl('provider_share', 'partner');
        $signatureUrl = $trackedUrl('email_signature', 'owned');
        $campaignSegments = [
            ['type' => 'club', 'label' => 'Caravan and RV clubs', 'style' => 'club_member_resource'],
            ['type' => 'club_federation', 'label' => 'Club federations', 'style' => 'club_member_resource'],
            ['type' => 'touring_association', 'label' => '4WD and touring clubs', 'style' => 'club_member_resource'],
            ['type' => 'publication', 'label' => 'Publications and media', 'style' => 'editorial_story'],
            ['type' => 'tourism_body', 'label' => 'Tourism organisations', 'style' => 'tourism_visitor_resource'],
            ['type' => 'industry_association', 'label' => 'Industry associations', 'style' => 'industry_data_collaboration'],
            ['type' => 'manufacturer', 'label' => 'Manufacturers', 'style' => 'fleet_dealer_owner_support'],
            ['type' => 'dealer_network', 'label' => 'Dealers and sales networks', 'style' => 'fleet_dealer_owner_support'],
            ['type' => 'rental_fleet', 'label' => 'Rental fleets and marketplaces', 'style' => 'fleet_dealer_owner_support'],
            ['type' => 'park_network', 'label' => 'Caravan park networks', 'style' => 'tourism_visitor_resource'],
        ];
        foreach ($campaignSegments as &$segment) {
            $segment['eligible'] = count(OrganisationOutreach::eligibleRecipients($segment['type']));
            $segment['compose_url'] = url('admin/notifications/compose?' . http_build_query([
                'campaign_type' => 'organisation_outreach',
                'audience_type' => 'organisations',
                'organisation_type' => $segment['type'],
                'copy_style' => $segment['style'],
            ]));
            $segment['review_url'] = url('admin/outreach-hub?' . http_build_query([
                'status' => 'research',
                'type' => $segment['type'],
            ]) . '#target-register-heading');
        }
        unset($segment);

        return $this->view('admin.outreach-hub.index', [
            'title' => 'PR & Outreach Hub',
            'summary' => OrganisationOutreach::summary(),
            'contacts' => OrganisationOutreach::search($query, $status, $type, $state),
            'campaigns' => Database::select(
                "SELECT id,title,status,delivery_stage,recipient_count,created_at FROM notifications WHERE brand_id=? AND campaign_type='organisation_outreach' ORDER BY id DESC LIMIT 20",
                [$brandId]
            ),
            'recentEvents' => Database::select(
                'SELECT e.event_type,e.notes,e.created_at,o.organisation_name,n.title AS campaign_title '
                . 'FROM organisation_outreach_events e JOIN organisation_outreach_contacts o ON o.id=e.organisation_contact_id '
                . 'LEFT JOIN notifications n ON n.id=e.notification_id ORDER BY e.id DESC LIMIT 50'
            ),
            'types' => OrganisationOutreach::TYPES,
            'statuses' => OrganisationOutreach::STATUSES,
            'outcomes' => OrganisationOutreach::OUTCOMES,
            'filters' => ['q' => $query, 'status' => $status, 'type' => $type, 'state' => $state],
            'campaignSegments' => $campaignSegments,
            'freeChannelStatus' => [
                'indexing' => (string) Settings::get('seo_allow_indexing', '0') === '1',
                'facebook' => FacebookPagePublisher::configured($brand->id()),
                'factual_campaigns' => (int) Database::scalar(
                    "SELECT COUNT(*) FROM notifications WHERE brand_id=? AND campaign_type='directory_accuracy' AND status NOT IN ('sent','cancelled')",
                    [$brandId]
                ),
                'approved_social_assets' => (int) Database::scalar(
                    "SELECT COUNT(*) FROM social_media_assets WHERE brand_id=? AND status='approved'",
                    [$brandId]
                ),
            ],
            'shareKits' => [
                [
                    'key' => 'community',
                    'title' => 'Community or Facebook group',
                    'url' => $communityUrl,
                    'copy' => "Admin — hope this is okay to post. If not, please delete it gently and pretend I was never here.\n\nWe have launched {$brandName}, a free way to find {$resourceDescription}. We are asking users and relevant local businesses to try it and tell us what is missing or wrong.\n\n{$communityUrl}",
                ],
                [
                    'key' => 'messenger',
                    'title' => 'Messenger or personal share',
                    'url' => $messengerUrl,
                    'copy' => "{$brandName} is a free way to find {$resourceDescription}. Have a look and tell me what needs fixing: {$messengerUrl}",
                ],
                [
                    'key' => 'newsletter',
                    'title' => 'Club newsletter or member resource',
                    'url' => $newsletterUrl,
                    'copy' => "Free member resource: {$brandName} helps people find {$resourceDescription}. No account is required to browse the public information. Clubs and relevant organisations are welcome to share the link as a member resource and send corrections or coverage gaps to us: {$newsletterUrl}",
                ],
                [
                    'key' => 'provider',
                    'title' => 'Provider, park or dealer share',
                    'url' => $providerUrl,
                    'copy' => "Customers can use {$brandName} free to find {$resourceDescription}. If it is useful to your customers, you are welcome to share it: {$providerUrl}",
                ],
                [
                    'key' => 'signature',
                    'title' => 'Your everyday email signature',
                    'url' => $signatureUrl,
                    'copy' => "{$brandName} — free public access to {$resourceDescription}: {$signatureUrl}",
                ],
            ],
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
