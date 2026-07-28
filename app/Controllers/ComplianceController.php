<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\GarageAsset;
use App\Services\ComplianceGuide;

final class ComplianceController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $this->userId();
        return $this->view('account.compliance', [
            'title' => 'Compliance centre',
            'journeys' => Database::select(
                'SELECT j.*, a.nickname AS asset_name, b.name AS brand_name FROM regulatory_journeys j '
                . 'LEFT JOIN garage_assets a ON a.id=j.garage_asset_id AND a.user_id=j.user_id AND a.deleted_at IS NULL '
                . 'INNER JOIN brands b ON b.id=j.brand_id WHERE j.user_id=? ORDER BY j.created_at DESC',
                [$userId]
            ),
            'subscriptions' => Database::select(
                "SELECT s.*, b.name AS brand_name FROM regulatory_alert_subscriptions s INNER JOIN brands b ON b.id=s.brand_id "
                . "WHERE s.user_id=? AND s.status<>'unsubscribed' ORDER BY s.created_at DESC",
                [$userId]
            ),
        ]);
    }

    public function save(Request $request): Response
    {
        $selection = ComplianceGuide::selections(
            strtoupper(trim((string) $request->input('jurisdiction', ''))),
            trim((string) $request->input('vehicle', '')),
            trim((string) $request->input('intention', '')),
            current_brand()->id()
        );
        if ($selection === null) {
            return $this->redirectWith('/rules/guided', 'error', 'Choose a valid jurisdiction, vehicle and job.');
        }
        $assetId = max(0, (int) $request->input('garage_asset_id', 0));
        if ($assetId > 0 && GarageAsset::owned($assetId, $this->userId()) === null) {
            $this->abort(404);
        }
        $title = ComplianceGuide::INTENTIONS[$selection['intention']] . ' — '
            . ComplianceGuide::VEHICLES[$selection['vehicle']] . ' in ' . $selection['jurisdiction'];
        $id = Database::insert(
            'INSERT INTO regulatory_journeys (user_id,garage_asset_id,brand_id,jurisdiction_code,vehicle_class,document_kind,intention,title,limitation_text,created_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,NOW())',
            [$this->userId(), $assetId ?: null, current_brand()->databaseId(), $selection['jurisdiction'], $selection['vehicle'],
                $selection['kind'], $selection['intention'], $title, ComplianceGuide::limitation()]
        );
        return $this->redirectWith('/account/compliance?journey=' . $id, 'success', 'Compliance pathway saved. Choose whether you want source-change alerts.');
    }

    public function subscribe(Request $request): Response
    {
        $journey = $this->ownedJourney((int) $request->input('journey_id'));
        if ((string) $request->input('consent', '') !== 'yes') {
            return $this->redirectWith('/account/compliance', 'error', 'Confirm that you want email alerts for this source scope.');
        }
        Database::query(
            'INSERT INTO regulatory_alert_subscriptions (user_id,brand_id,jurisdiction_code,vehicle_class,document_kind,status,email_enabled,consented_at,consent_source,created_at) '
            . "VALUES (?,?,?,?,?,'active',1,NOW(),'saved_journey',NOW()) ON DUPLICATE KEY UPDATE status='active',email_enabled=1,consented_at=NOW(),updated_at=NOW()",
            [$this->userId(), (int) $journey['brand_id'], $journey['jurisdiction_code'], $journey['vehicle_class'], $journey['document_kind']]
        );
        return $this->redirectWith('/account/compliance', 'success', 'Official-source change alerts enabled. You can stop them here at any time.');
    }

    public function unsubscribe(Request $request): Response
    {
        Database::affecting(
            "UPDATE regulatory_alert_subscriptions SET status='unsubscribed',email_enabled=0,updated_at=NOW() WHERE id=? AND user_id=?",
            [(int) $request->input('subscription_id'), $this->userId()]
        );
        return $this->redirectWith('/account/compliance', 'success', 'That alert has been stopped.');
    }

    public function handoff(Request $request): Response
    {
        $journey = $this->ownedJourney((int) $request->input('journey_id'));
        if ((string) $request->input('consent', '') !== 'yes') {
            return $this->redirectWith('/account/compliance', 'error', 'Confirm the limited context you want carried into provider search.');
        }
        [$brandKey, $destinationBrandId, $query] = $this->destination((string) $journey['intention'], (string) $journey['vehicle_class']);
        $context = [
            'jurisdiction' => (string) $journey['jurisdiction_code'],
            'vehicle_class' => (string) $journey['vehicle_class'],
            'document_kind' => (string) $journey['document_kind'],
            'intention' => (string) $journey['intention'],
        ];
        Database::insert(
            'INSERT INTO regulatory_provider_handoffs (user_id,journey_id,garage_asset_id,brand_id,destination_brand_id,context_json,disclosed_fields_json,consent_text,consented_at,created_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())',
            [$this->userId(), (int) $journey['id'], $journey['garage_asset_id'], (int) $journey['brand_id'], $destinationBrandId,
                json_encode($context, JSON_THROW_ON_ERROR), json_encode(array_keys($context), JSON_THROW_ON_ERROR),
                'Share only the selected compliance context with the destination search; do not disclose private Garage documents or account data.']
        );
        $base = rtrim((string) config('brands.registry.' . $brandKey . '.url', ''), '/');
        return $this->redirect($base . '/providers?' . http_build_query(['q' => $query]));
    }

    /** @return array<string,mixed> */
    private function ownedJourney(int $id): array
    {
        $journey = Database::selectOne('SELECT * FROM regulatory_journeys WHERE id=? AND user_id=?', [$id, $this->userId()]);
        if ($journey === null) {
            $this->abort(404);
        }
        return $journey;
    }

    /** @return array{string,int,string} */
    private function destination(string $intention, string $vehicle): array
    {
        if ($intention === 'tow') {
            return ['towsmart', 2, 'towing compliance'];
        }
        if ($vehicle === 'trailer' || $intention === 'register') {
            return ['trailerwise', 3, 'inspection compliance'];
        }
        if ($intention === 'travel') {
            return ['vanassist', 1, 'travel inspection'];
        }
        if ($vehicle === 'street-rod') {
            return ['localtorque', 4, 'street rod certification'];
        }
        return ['localtorque', 4, $intention === 'modify' ? 'vehicle engineering' : 'roadworthy inspection'];
    }

    private function userId(): int
    {
        $id = (int) (current_user()['id'] ?? 0);
        if ($id < 1) {
            $this->abort(401);
        }
        return $id;
    }
}
