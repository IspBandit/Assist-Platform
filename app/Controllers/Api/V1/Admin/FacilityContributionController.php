<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\AdminApiException;
use App\Services\Api\AdminApiEnvelope;
use App\Services\StayFacilityService;

final class FacilityContributionController extends Controller
{
    public function index(Request $request): Response
    {
        $status=(string)$request->query('status','pending');
        $rows=Database::select('SELECT fc.*,cp.name AS park_name,(SELECT COUNT(*) FROM facility_contribution_confirmations x WHERE x.contribution_id=fc.id) AS confirmations FROM facility_contributions fc JOIN caravan_parks cp ON cp.id=fc.park_id WHERE fc.status=? ORDER BY fc.created_at LIMIT 200',[$status]);
        return AdminApiEnvelope::collection($rows,['count'=>count($rows),'status'=>$status],['next'=>null]);
    }
    public function show(Request $request): Response
    {
        $id=(int)$request->route('id',0); $row=Database::selectOne('SELECT fc.*,cp.name AS park_name FROM facility_contributions fc JOIN caravan_parks cp ON cp.id=fc.park_id WHERE fc.id=?',[$id]);
        if($row===null) throw new AdminApiException(404,'not_found','Facility contribution not found.');
        $row['items']=Database::select('SELECT * FROM facility_contribution_items WHERE contribution_id=? ORDER BY id',[$id]);
        $row['current_facilities']=(new StayFacilityService())->forPark((int)$row['park_id']);
        return AdminApiEnvelope::data($row);
    }
    public function moderate(Request $request): Response
    {
        $id=(int)$request->route('id',0); $action=(string)$request->route('action','');
        $map=['approve'=>'approve','approve-with-edit'=>'approve_edit','partial-approve'=>'partial','reject'=>'reject','duplicate'=>'duplicate'];
        if(!isset($map[$action])) throw new AdminApiException(422,'invalid_action','Invalid moderation action.');
        (new StayFacilityService())->moderate($id,$map[$action],(array)$request->input('items',[]),substr(trim((string)$request->input('notes','')),0,2000),(int)auth()->id());
        return AdminApiEnvelope::data(['id'=>(string)$id,'status'=>$map[$action]]);
    }
}
