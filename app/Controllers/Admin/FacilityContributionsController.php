<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\StayFacilityService;

final class FacilityContributionsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $status = (string) $request->query('status', 'pending');
        $allowed = ['pending','under_review','approved','partially_approved','rejected','duplicate'];
        $status = in_array($status, $allowed, true) ? $status : 'pending';
        $rows = Database::select(
            'SELECT fc.*,cp.name AS park_name,cp.slug AS park_slug,COUNT(DISTINCT fci.id) AS item_count,COUNT(DISTINCT fcc.id) AS confirmations
             FROM facility_contributions fc JOIN caravan_parks cp ON cp.id=fc.park_id
             LEFT JOIN facility_contribution_items fci ON fci.contribution_id=fc.id
             LEFT JOIN facility_contribution_confirmations fcc ON fcc.contribution_id=fc.id
             WHERE fc.status=? GROUP BY fc.id ORDER BY fc.created_at ASC LIMIT 200', [$status]
        );
        $counts = Database::select('SELECT status,COUNT(*) AS total FROM facility_contributions GROUP BY status');
        $quality = [
            'no_facilities'=>(int)Database::scalar('SELECT COUNT(*) FROM caravan_parks cp WHERE cp.status=\'active\' AND cp.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM stay_facility_claims sfc WHERE sfc.park_id=cp.id AND sfc.superseded_at IS NULL)'),
            'conflicts'=>(int)Database::scalar('SELECT COUNT(*) FROM (SELECT park_id,facility_type FROM stay_facility_claims WHERE superseded_at IS NULL GROUP BY park_id,facility_type HAVING COUNT(DISTINCT facility_status)>1) conflicts'),
            'stale'=>(int)Database::scalar("SELECT COUNT(*) FROM stay_facility_claims WHERE superseded_at IS NULL AND COALESCE(last_seen_at,verified_at,updated_at)<DATE_SUB(NOW(),INTERVAL 18 MONTH)"),
            'low_confidence'=>(int)Database::scalar("SELECT COUNT(*) FROM stay_facility_claims WHERE superseded_at IS NULL AND source_confidence<60"),
        ];
        return $this->view('admin.facility-contributions.index', ['title'=>'Facility contributions','rows'=>$rows,'counts'=>$counts,'status'=>$status,'quality'=>$quality]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $id = (int) $request->query('id', 0);
        $row = Database::selectOne('SELECT fc.*,cp.name AS park_name,cp.slug AS park_slug FROM facility_contributions fc JOIN caravan_parks cp ON cp.id=fc.park_id WHERE fc.id=?', [$id]);
        if ($row === null) $this->abort(404, 'Contribution not found.');
        $items = Database::select('SELECT * FROM facility_contribution_items WHERE contribution_id=? ORDER BY id', [$id]);
        $history = Database::select('SELECT fma.*,u.name AS moderator_name FROM facility_moderation_actions fma LEFT JOIN users u ON u.id=fma.moderator_user_id WHERE contribution_id=? ORDER BY created_at DESC', [$id]);
        $claims=Database::select('SELECT * FROM stay_facility_claims WHERE park_id=? AND superseded_at IS NULL ORDER BY facility_type,verified_at DESC,id DESC',[(int)$row['park_id']]);
        return $this->view('admin.facility-contributions.show', ['title'=>'Review facility contribution','contribution'=>$row,'items'=>$items,'history'=>$history,'facilities'=>(new StayFacilityService())->forPark((int)$row['park_id']),'claims'=>$claims]);
    }

    public function moderate(Request $request): Response
    {
        $this->requirePermission('parks.manage');
        $id = (int) $request->input('id', 0);
        $edits = [];
        foreach ((array) $request->input('item', []) as $itemId => $item) {
            if (!is_array($item)) continue;
            $status = (string)($item['status'] ?? '');
            if (!in_array($status, ['yes','no','unknown','conditional'], true)) $status = 'unknown';
            $edits[(int)$itemId] = ['approve'=>(string)($item['approve']??''),'status'=>$status,
                'value'=>substr(trim((string)($item['value']??'')),0,120),'details'=>substr(trim((string)($item['details']??'')),0,1000)];
        }
        (new StayFacilityService())->moderate($id, (string)$request->input('action'), $edits, substr(trim((string)$request->input('notes','')),0,2000), (int)auth()->id());
        Session::flash('success', 'Facility contribution moderated and audit history recorded.');
        return $this->redirect('admin/facility-contributions/show?id='.$id);
    }
}
