<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;

final class TrailerListingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $status = trim((string)$request->query('status',''));
        $params=[]; $where=['l.deleted_at IS NULL'];
        if ($status!=='') { $where[]='l.status=?'; $params[]=$status; }
        $rows=Database::select('SELECT l.*,p.business_name FROM trailer_listings l JOIN providers p ON p.id=l.provider_id WHERE '.implode(' AND ',$where).' ORDER BY l.created_at DESC LIMIT 200',$params);
        return $this->view('admin.trailer-listings.index',['title'=>'Trailer listings','rows'=>$rows,'status'=>$status]);
    }

    public function status(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id=(int)$request->input('id'); $status=(string)$request->input('status');
        if (!in_array($status,['pending','active','sold','suspended','archived'],true)) { $this->abort(422,'Invalid status.'); }
        $before=Database::selectOne('SELECT * FROM trailer_listings WHERE id=? AND deleted_at IS NULL',[$id]);
        if ($before===null) { $this->abort(404); }
        Database::affecting('UPDATE trailer_listings SET status=?,updated_at=NOW() WHERE id=?',[$status,$id]);
        AuditLog::record(
            'trailer_listing.status_changed',
            'trailer_listing',
            (string) $id,
            json_encode(['status' => $before['status']], JSON_THROW_ON_ERROR),
            json_encode(['status' => $status], JSON_THROW_ON_ERROR)
        );
        return $this->redirectWith('/admin/trailer-listings','success','Trailer listing status updated.');
    }
}
